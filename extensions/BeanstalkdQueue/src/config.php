<?php

/**
 * Beanstalkd Queue Extension Configuration
 *
 * Default configuration for the Beanstalkd Queue Extension.
 * These settings can be overridden in the application's config files.
 */

declare(strict_types=1);

return [
    /**
     * Extension Information
     */
    'extension' => [
        'name' => 'BeanstalkdQueue',
        'version' => '1.0.0',
        'description' => 'Beanstalkd queue driver for high-performance job processing',
        'author' => 'Glueful Framework',
        'namespace' => 'Glueful\\Extensions\\BeanstalkdQueue',
    ],

    /**
     * Beanstalkd Server Configuration
     */
    'server' => [
        'host' => env('BEANSTALKD_HOST', '127.0.0.1'),
        'port' => env('BEANSTALKD_PORT', 11300),
        'timeout' => env('BEANSTALKD_TIMEOUT', 10),
        'persistent' => env('BEANSTALKD_PERSISTENT', false),
    ],

    /**
     * Queue Configuration
     */
    'queue' => [
        'default_tube' => env('BEANSTALKD_DEFAULT_TUBE', 'default'),
        'retry_after' => env('BEANSTALKD_RETRY_AFTER', 90),
        'block_for' => env('BEANSTALKD_BLOCK_FOR', 0),
        'max_priority' => env('BEANSTALKD_MAX_PRIORITY', 1024),
    ],

    /**
     * Job Configuration
     */
    'jobs' => [
        'default_priority' => 1024,
        'default_delay' => 0,
        'default_ttr' => 60, // Time to run (seconds)
        'max_attempts' => 3,
        'retry_delay' => 60, // Delay between retries (seconds)
    ],

    /**
     * Tube Management
     */
    'tubes' => [
        'auto_create' => true,
        'default_tubes' => [
            'default',
            'emails',
            'notifications',
            'maintenance',
            'critical'
        ],
        'naming_convention' => 'snake_case', // snake_case, kebab-case, camelCase
        'max_tube_name_length' => 200,
    ],

    /**
     * Performance Configuration
     */
    'performance' => [
        'connection_pooling' => false,
        'max_connections' => 10,
        'batch_size' => 100,
        'bulk_operations' => true,
        'lazy_connections' => true,
    ],

    /**
     * Health Check Configuration
     */
    'health' => [
        'enabled' => true,
        'interval' => 30, // seconds
        'timeout' => 5, // seconds
        'retry_attempts' => 3,
        'metrics_collection' => true,
    ],

    /**
     * Monitoring Configuration
     */
    'monitoring' => [
        'enabled' => true,
        'collect_stats' => true,
        'stats_interval' => 60, // seconds
        'metrics_retention' => 86400, // 24 hours in seconds
        'alert_thresholds' => [
            'ready_jobs' => 1000,
            'buried_jobs' => 100,
            'delayed_jobs' => 500,
            'response_time' => 1000, // milliseconds
        ],
    ],

    /**
     * Security Configuration
     */
    'security' => [
        'require_authentication' => false,
        'allowed_job_classes' => [], // Empty array allows all classes
        'blocked_job_classes' => [],
        'validate_job_data' => true,
        'sanitize_payloads' => true,
    ],

    /**
     * Logging Configuration
     */
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'channels' => ['beanstalkd', 'queue'],
        'log_job_lifecycle' => false,
        'log_connection_events' => true,
        'log_tube_operations' => false,
        'log_failed_jobs' => true,
    ],

    /**
     * Error Handling Configuration
     */
    'error_handling' => [
        'auto_retry' => true,
        'max_retries' => 3,
        'retry_delay' => 60, // seconds
        'retry_backoff' => 'exponential', // linear, exponential, fixed
        'dead_letter_tube' => 'failed_jobs',
        'bury_failed_jobs' => true,
    ],

    /**
     * Commands Configuration
     */
    'commands' => [
        'status_refresh_interval' => 5, // seconds
        'tube_stats_cache_ttl' => 30, // seconds
        'default_tube_limit' => 50, // for listing operations
    ],

    /**
     * Driver Registration
     */
    'driver' => [
        'name' => 'beanstalkd',
        'class' => 'Glueful\\Extensions\\BeanstalkdQueue\\BeanstalkdDriver',
        'auto_register' => true,
        'priority' => 100,
    ],

    /**
     * Maintenance Configuration
     */
    'maintenance' => [
        'cleanup_enabled' => true,
        'cleanup_interval' => 3600, // 1 hour in seconds
        'max_buried_jobs' => 10000,
        'max_job_age' => 604800, // 7 days in seconds
        'auto_kick_buried' => false,
    ],

    /**
     * Development Configuration
     */
    'development' => [
        'debug_mode' => env('APP_DEBUG', false),
        'verbose_logging' => false,
        'simulate_failures' => false,
        'mock_beanstalkd' => false,
    ],

    /**
     * Integration Configuration
     */
    'integration' => [
        'queue_manager' => true,
        'worker_support' => true,
        'failed_job_provider' => true,
        'event_dispatcher' => true,
        'audit_logging' => true,
    ],

    /**
     * Environment-specific Overrides
     */
    'environments' => [
        'production' => [
            'logging.level' => 'warning',
            'development.debug_mode' => false,
            'development.verbose_logging' => false,
            'monitoring.enabled' => true,
        ],
        'testing' => [
            'server.host' => '127.0.0.1',
            'server.port' => 11300,
            'development.mock_beanstalkd' => true,
            'logging.level' => 'debug',
        ],
        'development' => [
            'development.debug_mode' => true,
            'development.verbose_logging' => true,
            'logging.level' => 'debug',
            'monitoring.enabled' => false,
        ],
    ],
];
