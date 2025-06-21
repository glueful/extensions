<?php

namespace Glueful\Extensions\BeanstalkdQueue;

use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;
use Pheanstalk\Exception\ClientException;
use Glueful\Queue\Contracts\QueueDriverInterface;
use Glueful\Queue\Contracts\JobInterface;
use Glueful\Queue\Contracts\DriverInfo;
use Glueful\Queue\Contracts\HealthStatus;
use Glueful\Helpers\Utils;

/**
 * Beanstalkd Queue Driver
 *
 * High-performance queue driver using Pheanstalk library for Beanstalkd integration.
 * Provides priority queues, tube management, and job lifecycle features.
 *
 * @package Glueful\Extensions\BeanstalkdQueue
 */
class BeanstalkdDriver implements QueueDriverInterface
{
    private Pheanstalk $pheanstalk;
    private array $connectionPool = [];
    private string $defaultTube;
    private int $retryAfter;
    private int $blockFor;
    private int $maxConnections;
    private int $currentConnection = 0;
    private array $metrics = [];
    private bool $useOptimizedSerialization;
    private int $batchSize;

    /**
     * Get driver information
     */
    public function getDriverInfo(): DriverInfo
    {
        return new DriverInfo(
            name: 'beanstalkd',
            version: '2.0.0',
            author: 'Glueful Framework',
            description: 'High-performance Beanstalkd queue driver with connection pooling and optimization',
            supportedFeatures: [
                'priority',
                'delay',
                'retry',
                'failed',
                'bulk',
                'tubes',
                'burial',
                'stats',
                'connection_pooling',
                'performance_monitoring',
                'optimized_serialization',
                'batch_operations'
            ],
            requiredDependencies: ['pda/pheanstalk' => '^5.0']
        );
    }

    /**
     * Get driver features
     */
    public function getFeatures(): array
    {
        return [
            'priority' => true,
            'delay' => true,
            'retry' => true,
            'failed' => true,
            'bulk' => true,
            'tubes' => true,
            'burial' => true,
            'stats' => true,
            'persistent' => true
        ];
    }

    /**
     * Get configuration schema
     */
    public function getConfigSchema(): array
    {
        return [
            'host' => ['type' => 'string', 'required' => true, 'default' => '127.0.0.1'],
            'port' => ['type' => 'integer', 'required' => true, 'default' => 11300],
            'timeout' => ['type' => 'integer', 'default' => 10],
            'default_tube' => ['type' => 'string', 'default' => 'default'],
            'retry_after' => ['type' => 'integer', 'default' => 90],
            'block_for' => ['type' => 'integer', 'default' => 0],
            'max_connections' => ['type' => 'integer', 'default' => 5],
            'persistent' => ['type' => 'boolean', 'default' => true],
            'optimized_serialization' => ['type' => 'boolean', 'default' => true],
            'batch_size' => ['type' => 'integer', 'default' => 100]
        ];
    }

    /**
     * Initialize the driver
     */
    public function initialize(array $config): void
    {
        $this->defaultTube = $config['default_tube'] ?? 'default';
        $this->retryAfter = $config['retry_after'] ?? 90;
        $this->blockFor = $config['block_for'] ?? 0;
        $this->maxConnections = $config['max_connections'] ?? 5;
        $this->useOptimizedSerialization = $config['optimized_serialization'] ?? true;
        $this->batchSize = $config['batch_size'] ?? 100;

        // Initialize connection pool
        $this->initializeConnectionPool($config);

        // Set primary connection
        $this->pheanstalk = $this->connectionPool[0];

        // Initialize performance metrics
        $this->metrics = [
            'jobs_pushed' => 0,
            'jobs_popped' => 0,
            'jobs_buried' => 0,
            'jobs_kicked' => 0,
            'connection_errors' => 0,
            'serialization_time' => 0.0,
            'network_time' => 0.0
        ];
    }

    /**
     * Initialize connection pool for better performance
     */
    private function initializeConnectionPool(array $config): void
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 11300;
        $timeout = $config['timeout'] ?? 10;

        for ($i = 0; $i < $this->maxConnections; $i++) {
            try {
                $connection = Pheanstalk::create($host, $port, $timeout);
                $connection->useTube(new TubeName($this->defaultTube));
                $this->connectionPool[] = $connection;
            } catch (\Exception $e) {
                error_log("Failed to create Beanstalkd connection {$i}: " . $e->getMessage());

                if ($i === 0) {
                    throw $e;
                }
                break;
            }
        }

        if (empty($this->connectionPool)) {
            throw new \Exception('Failed to create any Beanstalkd connections');
        }
    }

    /**
     * Get next available connection from pool (round-robin)
     */
    private function getConnection(): Pheanstalk
    {
        $connection = $this->connectionPool[$this->currentConnection];
        $this->currentConnection = ($this->currentConnection + 1) % count($this->connectionPool);
        return $connection;
    }

    /**
     * Push a job to the queue
     */
    public function push(string $job, array $data = [], ?string $queue = null): string
    {
        return $this->pushRaw($this->createOptimizedPayload($job, $data), $queue);
    }

    /**
     * Push a delayed job to the queue
     */
    public function later(int $delay, string $job, array $data = [], ?string $queue = null): string
    {
        return $this->pushRaw($this->createOptimizedPayload($job, $data), $queue, $delay);
    }

    /**
     * Pop a job from the queue
     */
    public function pop(?string $queue = null): ?JobInterface
    {
        $tube = new TubeName($queue ?? $this->defaultTube);
        $startTime = microtime(true);

        try {
            $connection = $this->getConnection();
            $connection->watch($tube);

            if ($tube->value !== 'default') {
                $connection->ignore(new TubeName('default'));
            }

            $job = $this->blockFor > 0
                ? $connection->reserveWithTimeout($this->blockFor)
                : $connection->reserve();

            if ($job) {
                $this->metrics['jobs_popped']++;
                $this->metrics['network_time'] += microtime(true) - $startTime;

                return new BeanstalkdJob(
                    $connection,
                    $job,
                    $queue ?? $this->defaultTube
                );
            }
        } catch (ClientException $e) {
            if (str_contains($e->getMessage(), 'TIMED_OUT')) {
                return null;
            }
            $this->metrics['connection_errors']++;

            // Try with fallback connection
            try {
                $this->pheanstalk->watch($tube);
                if ($tube->value !== 'default') {
                    $this->pheanstalk->ignore(new TubeName('default'));
                }

                $job = $this->blockFor > 0
                    ? $this->pheanstalk->reserveWithTimeout($this->blockFor)
                    : $this->pheanstalk->reserve();

                if ($job) {
                    $this->metrics['jobs_popped']++;
                    return new BeanstalkdJob($this->pheanstalk, $job, $queue ?? $this->defaultTube);
                }
            } catch (\Exception) {
                // Both primary and fallback failed
                error_log("Beanstalkd error: " . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->metrics['connection_errors']++;
        }

        return null;
    }

    /**
     * Get the number of jobs in queue
     */
    public function size(?string $queue = null): int
    {
        try {
            $stats = $this->pheanstalk->statsTube(
                new TubeName($queue ?? $this->defaultTube)
            );
            return $stats['current-jobs-ready'] ?? 0;
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Delete a job
     */
    public function delete(JobInterface $job): void
    {
        if ($job instanceof BeanstalkdJob) {
            try {
                $this->pheanstalk->delete($job->getBeanstalkdJob());
            } catch (\Exception) {
                // Job might already be deleted
            }
        }
    }

    /**
     * Mark job as failed
     */
    public function failed(JobInterface $job, \Exception $exception): void
    {
        if ($job instanceof BeanstalkdJob) {
            try {
                // Bury the job with higher priority for later inspection
                $this->pheanstalk->bury($job->getBeanstalkdJob(), 0);
            } catch (\Exception) {
                // If bury fails, try to delete
                try {
                    $this->pheanstalk->delete($job->getBeanstalkdJob());
                } catch (\Exception) {
                    // Job might already be deleted
                }
            }
        }
    }

    /**
     * Release a job back to the queue
     */
    public function release(JobInterface $job, int $delay = 0): void
    {
        if ($job instanceof BeanstalkdJob) {
            try {
                $this->pheanstalk->release(
                    $job->getBeanstalkdJob(),
                    Pheanstalk::DEFAULT_PRIORITY,
                    $delay
                );
            } catch (\Exception) {
                // Job might already be released or deleted
            }
        }
    }

    /**
     * Purge all jobs from a queue
     */
    public function purge(?string $queue = null): int
    {
        $tube = new TubeName($queue ?? $this->defaultTube);
        $purged = 0;

        try {
            $this->pheanstalk->useTube($tube);
            $this->pheanstalk->watch($tube);

            // Purge ready jobs
            while ($job = $this->pheanstalk->reserve()) {
                $this->pheanstalk->delete($job);
                $purged++;
            }

            // Kick buried jobs and delete them
            $kicked = $this->pheanstalk->kick(1000);
            for ($i = 0; $i < $kicked; $i++) {
                if ($job = $this->pheanstalk->reserve()) {
                    $this->pheanstalk->delete($job);
                    $purged++;
                }
            }
        } catch (\Exception) {
            // Queue might be empty
        }

        return $purged;
    }

    /**
     * Get queue statistics
     */
    public function getStats(?string $queue = null): array
    {
        try {
            if ($queue) {
                $stats = $this->pheanstalk->statsTube(new TubeName($queue));
            } else {
                $stats = $this->pheanstalk->stats();
            }

            return [
                'ready' => $stats['current-jobs-ready'] ?? 0,
                'reserved' => $stats['current-jobs-reserved'] ?? 0,
                'delayed' => $stats['current-jobs-delayed'] ?? 0,
                'buried' => $stats['current-jobs-buried'] ?? 0,
                'total' => $stats['total-jobs'] ?? 0,
                'waiting' => $stats['current-waiting'] ?? 0,
                'watching' => $stats['current-watching'] ?? 0,
            ];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Check driver health
     */
    public function healthCheck(): HealthStatus
    {
        $startTime = microtime(true);

        try {
            $stats = $this->pheanstalk->stats();
            $responseTime = (microtime(true) - $startTime) * 1000;

            // Convert ServerStats object to array
            $metricsArray = [];
            foreach ($stats as $key => $value) {
                $metricsArray[$key] = $value;
            }

            // Add performance metrics to health status
            $metricsArray['performance_metrics'] = $this->metrics;
            $metricsArray['connection_pool_size'] = count($this->connectionPool);

            return HealthStatus::healthy(
                metrics: $metricsArray,
                message: 'Beanstalkd connection healthy',
                responseTime: $responseTime
            );
        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            return HealthStatus::unhealthy(
                message: 'Beanstalkd connection failed: ' . $e->getMessage(),
                metrics: [],
                responseTime: $responseTime
            );
        }
    }

    /**
     * Push bulk jobs
     */
    public function bulk(array $jobs, ?string $queue = null): array
    {
        $jobIds = [];
        $tube = new TubeName($queue ?? $this->defaultTube);

        // Process jobs in batches for optimal performance
        $batches = array_chunk($jobs, $this->batchSize);

        foreach ($batches as $batch) {
            $connection = $this->getConnection();
            $connection->useTube($tube);

            foreach ($batch as $jobData) {
                $job = $jobData['job'] ?? '';
                $data = $jobData['data'] ?? [];
                $priority = $jobData['priority'] ?? Pheanstalk::DEFAULT_PRIORITY;
                $delay = $jobData['delay'] ?? 0;
                $ttr = $jobData['ttr'] ?? $this->retryAfter;

                try {
                    $jobId = $connection->put(
                        data: $this->createOptimizedPayload($job, $data),
                        priority: $priority,
                        delay: $delay,
                        timeToRelease: $ttr
                    );

                    $jobIds[] = (string) $jobId;
                    $this->metrics['jobs_pushed']++;
                } catch (\Exception $e) {
                    // Log error but continue with other jobs
                    error_log("Failed to push bulk job: " . $e->getMessage());
                    $this->metrics['connection_errors']++;
                }
            }
        }

        return $jobIds;
    }

    /**
     * Bury a job (Beanstalkd-specific)
     */
    public function bury(JobInterface $job, int $priority = Pheanstalk::DEFAULT_PRIORITY): void
    {
        if ($job instanceof BeanstalkdJob) {
            try {
                $this->pheanstalk->bury($job->getBeanstalkdJob(), $priority);
                $this->metrics['jobs_buried']++;
            } catch (\Exception $e) {
                $this->metrics['connection_errors']++;
                throw $e;
            }
        }
    }

    /**
     * Kick buried jobs back to ready state (Beanstalkd-specific)
     */
    public function kick(int $max = 100, ?string $queue = null): int
    {
        try {
            $this->pheanstalk->useTube(new TubeName($queue ?? $this->defaultTube));
            $kicked = $this->pheanstalk->kick($max);
            $this->metrics['jobs_kicked'] += $kicked;
            return $kicked;
        } catch (\Exception) {
            $this->metrics['connection_errors']++;
            return 0;
        }
    }

    /**
     * Get tube list (Beanstalkd-specific)
     */
    public function listTubes(): array
    {
        try {
            $tubeList = $this->pheanstalk->listTubes();
            // Convert TubeList object to array
            return iterator_to_array($tubeList);
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Push raw payload to queue
     */
    protected function pushRaw(
        string $payload,
        ?string $queue = null,
        int $delay = 0,
        int $priority = Pheanstalk::DEFAULT_PRIORITY
    ): string {
        $startTime = microtime(true);
        $tube = new TubeName($queue ?? $this->defaultTube);

        try {
            $connection = $this->getConnection();
            $connection->useTube($tube);

            $jobId = $connection->put(
                data: $payload,
                priority: $priority,
                delay: $delay,
                timeToRelease: $this->retryAfter
            );

            $this->metrics['jobs_pushed']++;
            $this->metrics['network_time'] += microtime(true) - $startTime;

            return (string) $jobId;
        } catch (\Exception $e) {
            $this->metrics['connection_errors']++;

            // Try with fallback connection
            try {
                $this->pheanstalk->useTube($tube);
                $jobId = $this->pheanstalk->put(
                    data: $payload,
                    priority: $priority,
                    delay: $delay,
                    timeToRelease: $this->retryAfter
                );

                $this->metrics['jobs_pushed']++;
                return (string) $jobId;
            } catch (\Exception) {
                throw $e; // Throw original exception
            }
        }
    }

    /**
     * Create job payload
     */
    protected function createOptimizedPayload(string $job, array $data): string
    {
        $startTime = microtime(true);

        $payload = [
            'uuid' => Utils::generateNanoID(),
            'displayName' => $job,
            'job' => $job,
            'data' => $data,
            'attempts' => 0,
            'pushedAt' => time(),
        ];

        if ($this->useOptimizedSerialization && extension_loaded('msgpack') && function_exists('msgpack_pack')) {
            // Use MessagePack for better performance
            $serialized = \msgpack_pack($payload);
            $this->metrics['serialization_time'] += microtime(true) - $startTime;
            return base64_encode($serialized);
        } else {
            // Fallback to JSON
            $serialized = json_encode($payload, JSON_UNESCAPED_SLASHES);
            $this->metrics['serialization_time'] += microtime(true) - $startTime;
            return $serialized;
        }
    }

    /**
     * Legacy method for backward compatibility
     */
    protected function createPayload(string $job, array $data): string
    {
        return $this->createOptimizedPayload($job, $data);
    }

    /**
     * Get performance metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Reset performance metrics
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'jobs_pushed' => 0,
            'jobs_popped' => 0,
            'jobs_buried' => 0,
            'jobs_kicked' => 0,
            'connection_errors' => 0,
            'serialization_time' => 0.0,
            'network_time' => 0.0
        ];
    }
}
