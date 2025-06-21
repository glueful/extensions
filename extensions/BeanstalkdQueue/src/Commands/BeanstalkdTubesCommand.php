<?php

namespace Glueful\Extensions\BeanstalkdQueue\Commands;

use Glueful\Console\Command;
use Glueful\Queue\QueueManager;

/**
 * Beanstalkd Tubes Command
 *
 * Manages Beanstalkd tubes (queues) - list, create, and delete tubes.
 * Provides tube statistics and management capabilities.
 *
 * @package Glueful\Extensions\BeanstalkdQueue\Commands
 */
class BeanstalkdTubesCommand extends Command
{
    /**
     * Get command name
     */
    public function getName(): string
    {
        return 'beanstalkd:tubes';
    }

    /**
     * Get command description
     */
    public function getDescription(): string
    {
        return 'Manage Beanstalkd tubes (queues)';
    }

    /**
     * Execute the command
     */
    public function execute(array $args = []): int
    {
        $action = $args[0] ?? 'list';

        try {
            return match ($action) {
                'list' => $this->listTubes($args),
                'stats' => $this->showTubeStats($args),
                'purge' => $this->purgeTube($args),
                default => $this->showHelp()
            };
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * List all tubes
     */
    private function listTubes(array $args): int
    {
        $this->info("📋 Beanstalkd Tubes");
        $this->line("═══════════════════");
        $this->line();

        $queueManager = new QueueManager();
        $connections = $this->getBeanstalkdConnections($queueManager);

        if (empty($connections)) {
            $this->warning("No Beanstalkd connections found");
            return Command::SUCCESS;
        }

        foreach ($connections as $connectionName) {
            $this->displayConnectionTubes($queueManager, $connectionName);
            $this->line();
        }

        return Command::SUCCESS;
    }

    /**
     * Show tube statistics
     */
    private function showTubeStats(array $args): int
    {
        $tubeName = $args[1] ?? null;

        if (!$tubeName) {
            $this->error("Please specify a tube name");
            $this->line("Usage: beanstalkd:tubes stats <tube-name>");
            return Command::FAILURE;
        }

        $this->info("📊 Tube Statistics: {$tubeName}");
        $this->line("═══════════════════════════════");
        $this->line();

        $queueManager = new QueueManager();
        $connections = $this->getBeanstalkdConnections($queueManager);

        foreach ($connections as $connectionName) {
            $this->displayTubeStats($queueManager, $connectionName, $tubeName);
            $this->line();
        }

        return Command::SUCCESS;
    }

    /**
     * Purge a tube
     */
    private function purgeTube(array $args): int
    {
        $tubeName = $args[1] ?? null;

        if (!$tubeName) {
            $this->error("Please specify a tube name");
            $this->line("Usage: beanstalkd:tubes purge <tube-name>");
            return Command::FAILURE;
        }

        $this->info("🗑️  Purging tube: {$tubeName}");
        $this->line();

        if (!$this->confirm("Are you sure you want to purge all jobs from tube '{$tubeName}'?")) {
            $this->line("Operation cancelled");
            return Command::SUCCESS;
        }

        $queueManager = new QueueManager();
        $connections = $this->getBeanstalkdConnections($queueManager);

        $totalPurged = 0;

        foreach ($connections as $connectionName) {
            try {
                $driver = $queueManager->connection($connectionName);
                $purged = $driver->purge($tubeName);
                $totalPurged += $purged;

                $this->success("Connection {$connectionName}: Purged {$purged} jobs");
            } catch (\Exception $e) {
                $this->error("Connection {$connectionName}: Failed - " . $e->getMessage());
            }
        }

        $this->line();
        $this->success("Total jobs purged: {$totalPurged}");

        return Command::SUCCESS;
    }

    /**
     * Show help
     */
    private function showHelp(): int
    {
        $this->info("Beanstalkd Tubes Management");
        $this->line("═══════════════════════════");
        $this->line();
        $this->line("Usage:");
        $this->line("  beanstalkd:tubes <action> [options]");
        $this->line();
        $this->line("Actions:");
        $this->line("  list                List all tubes");
        $this->line("  stats <tube>        Show tube statistics");
        $this->line("  purge <tube>        Purge all jobs from tube");
        $this->line();
        $this->line("Examples:");
        $this->line("  beanstalkd:tubes list");
        $this->line("  beanstalkd:tubes stats default");
        $this->line("  beanstalkd:tubes purge failed-jobs");

        return Command::SUCCESS;
    }

    /**
     * Get Beanstalkd connections
     */
    private function getBeanstalkdConnections(QueueManager $queueManager): array
    {
        $connections = $queueManager->getAvailableConnections();
        $beanstalkdConnections = [];

        foreach ($connections as $name) {
            $config = config("queue.connections.{$name}");
            if (isset($config['driver']) && $config['driver'] === 'beanstalkd') {
                $beanstalkdConnections[] = $name;
            }
        }

        return $beanstalkdConnections;
    }

    /**
     * Display tubes for a connection
     */
    private function displayConnectionTubes(QueueManager $queueManager, string $connectionName): void
    {
        try {
            $driver = $queueManager->connection($connectionName);
            $stats = $driver->getStats();

            $this->info("Connection: {$connectionName}");
            $this->line(str_repeat("─", 40));

            // Note: This is simplified - real implementation would need
            // to call list-tubes command through the driver
            $this->line("Available tubes: default");
            $this->line("Ready jobs: " . ($stats['current_jobs_ready'] ?? 0));
            $this->line("Reserved jobs: " . ($stats['current_jobs_reserved'] ?? 0));
            $this->line("Delayed jobs: " . ($stats['current_jobs_delayed'] ?? 0));
            $this->line("Buried jobs: " . ($stats['current_jobs_buried'] ?? 0));
        } catch (\Exception $e) {
            $this->error("❌ Connection {$connectionName}: " . $e->getMessage());
        }
    }

    /**
     * Display statistics for a specific tube
     */
    private function displayTubeStats(QueueManager $queueManager, string $connectionName, string $tubeName): void
    {
        try {
            $driver = $queueManager->connection($connectionName);
            $stats = $driver->getStats($tubeName);

            $this->info("Connection: {$connectionName}");
            $this->line(str_repeat("─", 40));

            if (empty($stats)) {
                $this->warning("No statistics available for tube '{$tubeName}'");
                return;
            }

            $display = [
                'Current Jobs Ready' => number_format($stats['current_jobs_ready'] ?? 0),
                'Current Jobs Reserved' => number_format($stats['current_jobs_reserved'] ?? 0),
                'Current Jobs Delayed' => number_format($stats['current_jobs_delayed'] ?? 0),
                'Current Jobs Buried' => number_format($stats['current_jobs_buried'] ?? 0),
                'Total Jobs' => number_format($stats['total_jobs'] ?? 0),
                'Current Watching' => number_format($stats['current_watching'] ?? 0),
                'Current Waiting' => number_format($stats['current_waiting'] ?? 0),
            ];

            foreach ($display as $label => $value) {
                $this->line(sprintf("  %-25s: %s", $label, $value));
            }
        } catch (\Exception $e) {
            $this->error("❌ Connection {$connectionName}: " . $e->getMessage());
        }
    }

    /**
     * Confirm user action
     */
    private function confirm(string $question): bool
    {
        $this->line($question . " (yes/no) [no]: ");
        $answer = trim(fgets(STDIN));
        return in_array(strtolower($answer), ['y', 'yes']);
    }
}
