<?php

namespace Glueful\Extensions\BeanstalkdQueue;

use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\Job as PheanstalkJob;
use Glueful\Queue\Contracts\JobInterface;
use Glueful\Queue\Contracts\QueueDriverInterface;
use Glueful\Helpers\Utils;

/**
 * Beanstalkd Job Implementation
 *
 * Wrapper for Pheanstalk jobs that implements Glueful's JobInterface.
 * Provides access to Beanstalkd-specific features while maintaining
 * compatibility with the queue system.
 *
 * @package Glueful\Extensions\BeanstalkdQueue
 */
class BeanstalkdJob implements JobInterface
{
    private Pheanstalk $pheanstalk;
    private PheanstalkJob $pheanstalkJob;
    private string $queue;
    private array $payload;
    private int $attempts = 0;

    /**
     * Constructor
     */
    public function __construct(Pheanstalk $pheanstalk, PheanstalkJob $job, string $queue)
    {
        $this->pheanstalk = $pheanstalk;
        $this->pheanstalkJob = $job;
        $this->queue = $queue;

        $this->payload = json_decode($job->getData(), true) ?? [];
        $this->attempts = $this->payload['attempts'] ?? 0;
    }

    /**
     * Execute the job
     */
    public function fire(): void
    {
        $jobClass = $this->payload['job'] ?? null;

        if (!$jobClass || !class_exists($jobClass)) {
            throw new \Exception("Job class '{$jobClass}' not found");
        }

        $jobInstance = new $jobClass($this->payload['data'] ?? []);

        if (!method_exists($jobInstance, 'handle')) {
            throw new \Exception("Job class '{$jobClass}' does not have a handle method");
        }

        $jobInstance->handle();
    }

    /**
     * Get job name
     */
    public function getName(): string
    {
        return $this->payload['job'] ?? 'unknown';
    }

    /**
     * Get job data
     */
    public function getData(): array
    {
        return $this->payload['data'] ?? [];
    }

    /**
     * Get job attempts
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Set job attempts
     */
    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
        $this->payload['attempts'] = $attempts;
    }

    /**
     * Get job description
     */
    public function getDescription(): string
    {
        return $this->payload['description'] ?? $this->getName();
    }

    /**
     * Get queue driver (returns null as not needed for this implementation)
     */
    public function getDriver(): ?QueueDriverInterface
    {
        return null;
    }

    /**
     * Set queue driver (not used in this implementation)
     */
    public function setDriver(QueueDriverInterface $driver): void
    {
        // Not needed for Pheanstalk-based implementation
    }

    /**
     * Get the underlying Pheanstalk job
     */
    public function getBeanstalkdJob(): PheanstalkJob
    {
        return $this->pheanstalkJob;
    }

    /**
     * Get job ID (Beanstalkd job ID)
     */
    public function getId(): string
    {
        return $this->pheanstalkJob->getId();
    }

    /**
     * Get job priority
     */
    public function getPriority(): int
    {
        return $this->payload['priority'] ?? 1024;
    }

    /**
     * Get job queue name
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Get job payload
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Get raw job data from Beanstalkd
     */
    public function getRawBody(): string
    {
        return $this->pheanstalkJob->getData();
    }

    /**
     * Check if job has exceeded max attempts
     */
    public function hasFailed(): bool
    {
        $maxAttempts = $this->payload['maxAttempts'] ?? 3;
        return $this->attempts >= $maxAttempts;
    }

    /**
     * Get job creation timestamp
     */
    public function getCreatedAt(): int
    {
        return $this->payload['pushedAt'] ?? time();
    }

    /**
     * Get job UUID
     */
    public function getUuid(): string
    {
        if (!isset($this->payload['uuid'])) {
            $this->payload['uuid'] = Utils::generateNanoID();
        }
        return $this->payload['uuid'];
    }

    /**
     * Convert job to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'name' => $this->getName(),
            'queue' => $this->getQueue(),
            'attempts' => $this->getAttempts(),
            'priority' => $this->getPriority(),
            'data' => $this->getData(),
            'payload' => $this->getPayload(),
            'created_at' => $this->getCreatedAt(),
        ];
    }

    /**
     * Release job back to queue for retry
     */
    public function release(int $delay = 0): void
    {
        $this->pheanstalk->release(
            $this->pheanstalkJob,
            $this->getPriority(),
            $delay
        );
    }

    /**
     * Delete job from queue (mark as completed)
     */
    public function delete(): void
    {
        $this->pheanstalk->delete($this->pheanstalkJob);
    }

    /**
     * Handle job failure
     */
    public function failed(\Exception $exception): void
    {
        // Bury the job for later inspection
        $this->pheanstalk->bury($this->pheanstalkJob, $this->getPriority());

        // Log the failure
        error_log("Beanstalkd job failed: " . $exception->getMessage());
    }

    /**
     * Get maximum number of attempts allowed
     */
    public function getMaxAttempts(): int
    {
        return $this->payload['maxAttempts'] ?? 3;
    }

    /**
     * Get job timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->payload['timeout'] ?? 60;
    }

    /**
     * Get batch UUID if job is part of a batch
     */
    public function getBatchUuid(): ?string
    {
        return $this->payload['batchUuid'] ?? null;
    }

    /**
     * Check if job should be retried after failure
     */
    public function shouldRetry(): bool
    {
        return $this->getAttempts() < $this->getMaxAttempts();
    }
}
