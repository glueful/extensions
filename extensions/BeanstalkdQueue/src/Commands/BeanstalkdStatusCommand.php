<?php

namespace Glueful\Extensions\BeanstalkdQueue\Commands;

use Glueful\Console\Command;
use Glueful\Queue\QueueManager;

/**
 * Beanstalkd Status Command
 *
 * Shows detailed status information for Beanstalkd queues.
 * Provides real-time monitoring of tubes, jobs, and server stats.
 *
 * @package Glueful\Extensions\BeanstalkdQueue\Commands
 */
class BeanstalkdStatusCommand extends Command
{
    /**
     * Get command name
     */
    public function getName(): string
    {
        return 'beanstalkd:status';
    }

    /**
     * Get command description
     */
    public function getDescription(): string
    {
        return 'Show Beanstalkd server and tube status';
    }

    /**
     * Execute the command
     */
    public function execute(array $args = []): int
    {
        try {
            $this->info("📊 Beanstalkd Status");
            $this->line("═══════════════════");
            $this->line();

            $queueManager = new QueueManager();

            // Find Beanstalkd connections
            $connections = $queueManager->getAvailableConnections();
            $beanstalkdConnections = [];

            foreach ($connections as $name) {
                $config = config("queue.connections.{$name}");
                if (isset($config['driver']) && $config['driver'] === 'beanstalkd') {
                    $beanstalkdConnections[] = $name;
                }
            }

            if (empty($beanstalkdConnections)) {
                $this->warning("No Beanstalkd connections found in configuration");
                return Command::SUCCESS;
            }

            foreach ($beanstalkdConnections as $connectionName) {
                $this->displayConnectionStatus($queueManager, $connectionName);
                $this->line();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to get Beanstalkd status: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display status for a specific connection
     */
    private function displayConnectionStatus(QueueManager $queueManager, string $connectionName): void
    {
        try {
            $driver = $queueManager->connection($connectionName);
            $health = $driver->healthCheck();

            $this->info("Connection: {$connectionName}");
            $this->line(str_repeat("─", 40));

            if ($health->isHealthy()) {
                $this->success("✅ Status: Healthy");
                $this->line("Response Time: " . number_format($health->responseTime, 2) . "ms");

                $metrics = $health->metrics;
                if (!empty($metrics)) {
                    $this->line();
                    $this->info("Server Statistics:");
                    $this->displayServerStats($metrics);
                }
            } else {
                $this->error("❌ Status: Unhealthy");
                $this->error("Error: " . $health->message);
            }
        } catch (\Exception $e) {
            $this->error("❌ Connection failed: " . $e->getMessage());
        }
    }

    /**
     * Display server statistics
     */
    private function displayServerStats(array $stats): void
    {
        $display = [
            'Version' => $stats['version'] ?? 'Unknown',
            'Uptime' => $this->formatUptime($stats['uptime'] ?? 0),
            'Total Connections' => number_format($stats['total_connections'] ?? 0),
            'Jobs Ready' => number_format($stats['current_jobs_ready'] ?? 0),
            'Jobs Reserved' => number_format($stats['current_jobs_reserved'] ?? 0),
            'Jobs Delayed' => number_format($stats['current_jobs_delayed'] ?? 0),
            'Jobs Buried' => number_format($stats['current_jobs_buried'] ?? 0),
            'Jobs Urgent' => number_format($stats['current_jobs_urgent'] ?? 0),
            'Commands Put' => number_format($stats['cmd_put'] ?? 0),
            'Commands Reserve' => number_format($stats['cmd_reserve'] ?? 0),
        ];

        foreach ($display as $label => $value) {
            $this->line(sprintf("  %-20s: %s", $label, $value));
        }
    }

    /**
     * Format uptime seconds to human readable
     */
    private function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes} minutes";
        } elseif ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return "{$hours}h {$minutes}m";
        } else {
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            return "{$days}d {$hours}h";
        }
    }
}
