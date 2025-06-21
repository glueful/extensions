<?php

namespace Glueful\Extensions\BeanstalkdQueue;

use Glueful\Extensions;
use Glueful\Queue\Registry\DriverRegistry;

/**
 * Beanstalkd Queue Extension
 *
 * Provides Beanstalkd queue driver integration for the Glueful queue system.
 * Demonstrates how external queue systems can be integrated as extensions.
 *
 * Features:
 * - Full Beanstalkd protocol support
 * - Tube-based queue management
 * - Priority job scheduling
 * - Job burial and deletion
 * - Connection health monitoring
 * - Performance metrics
 *
 * @package Glueful\Extensions\BeanstalkdQueue
 */
class BeanstalkdQueue extends Extensions
{
    /**
     * Extension information
     */
    public function getInfo(): array
    {
        return [
            'name' => 'BeanstalkdQueue',
            'version' => '1.0.0',
            'description' => 'Beanstalkd queue driver for high-performance job processing',
            'author' => 'Glueful Framework',
            'website' => 'https://glueful.com/extensions/beanstalkd-queue',
            'license' => 'MIT',
            'dependencies' => [
                'php' => '>=8.2.0',
                'pda/pheanstalk' => '^5.0'
            ],
            'tags' => ['queue', 'beanstalkd', 'jobs', 'background-processing'],
            'category' => 'Queue Systems'
        ];
    }

    /**
     * Extension requirements
     */
    public function getRequirements(): array
    {
        return [
            'composer_packages' => ['pda/pheanstalk' => '^5.0'],
            'php_version' => '8.2.0',
            'glueful_version' => '2.0.0',
            'external_services' => [
                'beanstalkd' => [
                    'description' => 'Beanstalkd server must be running',
                    'check_command' => 'telnet localhost 11300',
                    'install_guide' => 'https://beanstalkd.github.io/download.html'
                ]
            ]
        ];
    }

    /**
     * Install the extension
     */
    public function install(): bool
    {
        try {
            error_log('Installing Beanstalkd Queue extension...');

            // Check if Beanstalkd is available
            if (!$this->checkBeanstalkdAvailability()) {
                error_log('Warning: Beanstalkd server not detected. Extension installed but driver ' .
                         'will not work until Beanstalkd is running.');
            }

            // Register the queue driver
            $this->registerQueueDriver();

            error_log('Beanstalkd Queue extension installed successfully!');
            return true;
        } catch (\Exception $e) {
            error_log('Failed to install Beanstalkd Queue extension: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Uninstall the extension
     */
    public function uninstall(): bool
    {
        try {
            error_log('Uninstalling Beanstalkd Queue extension...');

            // Unregister the queue driver
            $this->unregisterQueueDriver();

            error_log('Beanstalkd Queue extension uninstalled successfully!');
            return true;
        } catch (\Exception $e) {
            error_log('Failed to uninstall Beanstalkd Queue extension: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable the extension
     */
    public function enable(): bool
    {
        try {
            error_log('Enabling Beanstalkd Queue extension...');

            // Register the queue driver
            $this->registerQueueDriver();

            error_log('Beanstalkd Queue extension enabled successfully!');
            return true;
        } catch (\Exception $e) {
            error_log('Failed to enable Beanstalkd Queue extension: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Disable the extension
     */
    public function disable(): bool
    {
        try {
            error_log('Disabling Beanstalkd Queue extension...');

            // Unregister the queue driver
            $this->unregisterQueueDriver();

            error_log('Beanstalkd Queue extension disabled successfully!');
            return true;
        } catch (\Exception $e) {
            error_log('Failed to disable Beanstalkd Queue extension: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the extension
     */
    public function update(string $newVersion): bool
    {
        try {
            error_log("Updating Beanstalkd Queue extension to version {$newVersion}...");

            // Perform any update-specific logic here
            $this->registerQueueDriver(); // Re-register in case of interface changes

            error_log('Beanstalkd Queue extension updated successfully!');
            return true;
        } catch (\Exception $e) {
            error_log('Failed to update Beanstalkd Queue extension: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get extension configuration schema
     */
    public static function getConfigSchema(): array
    {
        return [
            'beanstalkd' => [
                'type' => 'object',
                'description' => 'Beanstalkd queue driver configuration',
                'properties' => [
                    'host' => [
                        'type' => 'string',
                        'default' => '127.0.0.1',
                        'description' => 'Beanstalkd server hostname'
                    ],
                    'port' => [
                        'type' => 'integer',
                        'default' => 11300,
                        'description' => 'Beanstalkd server port'
                    ],
                    'timeout' => [
                        'type' => 'integer',
                        'default' => 5,
                        'description' => 'Connection timeout in seconds'
                    ],
                    'persistent' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => 'Use persistent connections'
                    ],
                    'default_tube' => [
                        'type' => 'string',
                        'default' => 'default',
                        'description' => 'Default tube (queue) name'
                    ],
                    'retry_after' => [
                        'type' => 'integer',
                        'default' => 90,
                        'description' => 'Seconds before job retry'
                    ],
                    'max_priority' => [
                        'type' => 'integer',
                        'default' => 1024,
                        'description' => 'Maximum job priority'
                    ]
                ],
                'required' => ['host', 'port']
            ]
        ];
    }

    /**
     * Get default configuration
     */
    public function getDefaultConfig(): array
    {
        return [
            'host' => env('BEANSTALKD_HOST', '127.0.0.1'),
            'port' => env('BEANSTALKD_PORT', 11300),
            'timeout' => env('BEANSTALKD_TIMEOUT', 5),
            'persistent' => env('BEANSTALKD_PERSISTENT', false),
            'default_tube' => env('BEANSTALKD_DEFAULT_TUBE', 'default'),
            'retry_after' => env('BEANSTALKD_RETRY_AFTER', 90),
            'max_priority' => env('BEANSTALKD_MAX_PRIORITY', 1024)
        ];
    }

    /**
     * Check if Beanstalkd is available
     */
    private function checkBeanstalkdAvailability(): bool
    {
        $config = $this->getDefaultConfig();
        $host = $config['host'];
        $port = $config['port'];
        $timeout = $config['timeout'];

        try {
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $timeout, 'usec' => 0]);
            socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $timeout, 'usec' => 0]);

            $result = socket_connect($socket, $host, $port);
            socket_close($socket);

            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Register the Beanstalkd queue driver
     */
    private function registerQueueDriver(): void
    {
        if (class_exists(DriverRegistry::class)) {
            $registry = app(DriverRegistry::class);
            $registry->registerDriver('beanstalkd', \Glueful\Extensions\BeanstalkdQueue\BeanstalkdDriver::class);
            error_log('Beanstalkd queue driver registered successfully');
        }
    }

    /**
     * Unregister the Beanstalkd queue driver
     */
    private function unregisterQueueDriver(): void
    {
        if (class_exists(DriverRegistry::class)) {
            $registry = app(DriverRegistry::class);
            $registry->unregisterDriver('beanstalkd');
            error_log('Beanstalkd queue driver unregistered successfully');
        }
    }

    /**
     * Get extension routes
     */
    public function getRoutes(): array
    {
        return []; // No HTTP routes needed for queue driver
    }

    /**
     * Get extension middleware
     */
    public function getMiddleware(): array
    {
        return []; // No middleware needed for queue driver
    }

    /**
     * Get extension commands
     */
    public function getCommands(): array
    {
        return [
            \Glueful\Extensions\BeanstalkdQueue\Commands\BeanstalkdStatusCommand::class,
            \Glueful\Extensions\BeanstalkdQueue\Commands\BeanstalkdTubesCommand::class,
        ];
    }

    /**
     * Get extension service provider
     */
    public static function getServiceProvider(): \Glueful\DI\Interfaces\ServiceProviderInterface
    {
        return new \Glueful\Extensions\BeanstalkdQueue\BeanstalkdServiceProvider();
    }

    /**
     * Get extension service providers
     */
    public function getServiceProviders(): array
    {
        return [
            \Glueful\Extensions\BeanstalkdQueue\BeanstalkdServiceProvider::class
        ];
    }
}
