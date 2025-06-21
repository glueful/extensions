<?php

namespace Glueful\Extensions\BeanstalkdQueue;

use Glueful\DI\Interfaces\ContainerInterface;
use Glueful\DI\Interfaces\ServiceProviderInterface;

/**
 * Beanstalkd Service Provider
 *
 * Registers Beanstalkd-specific services with the DI container.
 * Handles driver registration and configuration validation.
 *
 * @package Glueful\Extensions\BeanstalkdQueue
 */
class BeanstalkdServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services
     */
    public function register(ContainerInterface $container): void
    {
        // Load extension configuration
        $config = $this->loadConfig();

        // Register the Beanstalkd driver with configuration
        $container->singleton(BeanstalkdDriver::class, function ($container) use ($config) {
            $driver = new BeanstalkdDriver();
            // Driver will be initialized with config when needed by QueueManager
            return $driver;
        });

        // Register configuration as a service
        $container->singleton('beanstalkd.config', function () use ($config) {
            return $config;
        });

        // Register commands
        $container->singleton(Commands\BeanstalkdStatusCommand::class, function ($container) {
            return new Commands\BeanstalkdStatusCommand();
        });

        $container->singleton(Commands\BeanstalkdTubesCommand::class, function ($container) {
            return new Commands\BeanstalkdTubesCommand();
        });
    }

    /**
     * Boot services
     */
    public function boot(ContainerInterface $container): void
    {
        // Register with queue driver registry if available
        if (class_exists(\Glueful\Queue\Registry\DriverRegistry::class)) {
            try {
                $registry = $container->get(\Glueful\Queue\Registry\DriverRegistry::class);
                $registry->registerDriver('beanstalkd', BeanstalkdDriver::class);
            } catch (\Exception $e) {
                // Registry may not be available during early boot
                error_log('Could not register Beanstalkd driver: ' . $e->getMessage());
            }
        }
    }

    /**
     * Load extension configuration
     */
    private function loadConfig(): array
    {
        $configPath = __DIR__ . '/config.php';

        if (!file_exists($configPath)) {
            return [];
        }

        $config = require $configPath;

        // Merge with application config if available
        if (function_exists('config')) {
            $appConfig = config('extensions.beanstalkd', []);
            $config = array_merge_recursive($config, $appConfig);
        }

        return $config;
    }

    /**
     * Get configuration value
     */
    public static function getConfig(?string $key = null, $default = null)
    {
        if (function_exists('app') && app()->has('beanstalkd.config')) {
            $config = app()->get('beanstalkd.config');

            if ($key === null) {
                return $config;
            }

            return data_get($config, $key, $default);
        }

        // Fallback to loading config directly
        $configPath = __DIR__ . '/config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            return $key ? data_get($config, $key, $default) : $config;
        }

        return $default;
    }
}
