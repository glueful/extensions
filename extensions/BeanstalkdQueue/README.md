# Beanstalkd Queue Extension

A comprehensive Beanstalkd queue driver extension for the Glueful Framework. This extension provides full integration with Beanstalkd's powerful work queue system, enabling priority-based job processing, tube management, and advanced queue operations.

## Features

- ✅ **Full Beanstalkd Protocol Support** - Complete implementation of Beanstalkd commands
- ✅ **Priority-Based Scheduling** - Jobs can be prioritized for execution order
- ✅ **Tube Management** - Multiple named queues (tubes) support
- ✅ **Job Burial & Resurrection** - Advanced failed job handling
- ✅ **Health Monitoring** - Real-time server and tube statistics
- ✅ **Connection Persistence** - Optional persistent connections
- ✅ **Bulk Operations** - Efficient batch job processing
- ✅ **Management Commands** - CLI tools for monitoring and management

## Requirements

- PHP 8.2 or higher
- Glueful Framework 2.0+
- Socket extension (`ext-sockets`)
- Beanstalkd server 1.10+

## Installation

### 1. Install Beanstalkd Server

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install beanstalkd
sudo systemctl start beanstalkd
sudo systemctl enable beanstalkd
```

**macOS (with Homebrew):**
```bash
brew install beanstalkd
brew services start beanstalkd
```

**CentOS/RHEL:**
```bash
sudo yum install epel-release
sudo yum install beanstalkd
sudo systemctl start beanstalkd
sudo systemctl enable beanstalkd
```

### 2. Enable Extension

Add the extension to your Glueful configuration:

```php
// config/extensions.php
return [
    'enabled' => [
        'BeanstalkdQueue',
        // ... other extensions
    ]
];
```

### 3. Configure Queue Connection

Add Beanstalkd connection to your queue configuration:

```php
// config/queue.php
return [
    'default' => 'beanstalkd',
    
    'connections' => [
        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_HOST', '127.0.0.1'),
            'port' => env('BEANSTALKD_PORT', 11300),
            'timeout' => env('BEANSTALKD_TIMEOUT', 5),
            'persistent' => env('BEANSTALKD_PERSISTENT', false),
            'default_tube' => env('BEANSTALKD_DEFAULT_TUBE', 'default'),
            'retry_after' => env('BEANSTALKD_RETRY_AFTER', 90),
            'max_priority' => env('BEANSTALKD_MAX_PRIORITY', 1024),
        ],
    ],
];
```

### 4. Environment Variables

Add these to your `.env` file:

```env
# Beanstalkd Configuration
BEANSTALKD_HOST=127.0.0.1
BEANSTALKD_PORT=11300
BEANSTALKD_TIMEOUT=5
BEANSTALKD_PERSISTENT=false
BEANSTALKD_DEFAULT_TUBE=default
BEANSTALKD_RETRY_AFTER=90
BEANSTALKD_MAX_PRIORITY=1024
```

## Usage

### Basic Job Dispatching

```php
use Glueful\Queue\QueueManager;

$queueManager = new QueueManager();

// Simple job
$queueManager->push('App\\Jobs\\SendEmailJob', [
    'to' => 'user@example.com',
    'subject' => 'Welcome!'
]);

// Priority job (lower number = higher priority)
$queueManager->push('App\\Jobs\\UrgentJob', [
    'data' => 'important'
], 'urgent', [
    'priority' => 10
]);

// Delayed job
$queueManager->later(300, 'App\\Jobs\\ReminderJob', [
    'user_id' => 123
]);
```

### Tube-Specific Operations

```php
// Push to specific tube
$queueManager->push('App\\Jobs\\EmailJob', $data, 'emails');

// Push high-priority job
$queueManager->push('App\\Jobs\\CriticalJob', $data, 'critical', [
    'priority' => 1,    // Highest priority
    'ttr' => 120        // 2 minutes to run
]);
```

### Worker Processing

Start a worker to process jobs:

```bash
# Process default tube
php glueful queue work --connection=beanstalkd

# Process specific tube
php glueful queue work --connection=beanstalkd --queue=emails

# Process multiple tubes with priorities
php glueful queue work --connection=beanstalkd --queue=urgent,emails,default
```

## Management Commands

### Check Beanstalkd Status

```bash
# Show server status and statistics
php glueful beanstalkd:status
```

### Manage Tubes

```bash
# List all tubes
php glueful beanstalkd:tubes list

# Show tube statistics
php glueful beanstalkd:tubes stats emails

# Purge tube (remove all jobs)
php glueful beanstalkd:tubes purge failed-jobs
```

### Queue Management

```bash
# Monitor queue in real-time
php glueful queue monitor --connection=beanstalkd

# Check queue status
php glueful queue status --connection=beanstalkd

# Manage failed jobs
php glueful queue failed list
php glueful queue failed retry all
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `host` | string | `127.0.0.1` | Beanstalkd server hostname |
| `port` | integer | `11300` | Beanstalkd server port |
| `timeout` | integer | `5` | Connection timeout in seconds |
| `persistent` | boolean | `false` | Use persistent connections |
| `default_tube` | string | `default` | Default tube name |
| `retry_after` | integer | `90` | Seconds before job retry |
| `max_priority` | integer | `1024` | Maximum job priority |

## Job Priority System

Beanstalkd uses numeric priorities where **lower numbers = higher priority**:

- `0` - Highest priority (most urgent)
- `1-99` - High priority
- `100-999` - Normal priority
- `1000-1023` - Low priority
- `1024` - Lowest priority (default)

```php
// Ultra high priority
$queueManager->push('CriticalJob', $data, 'urgent', ['priority' => 0]);

// Normal priority
$queueManager->push('RegularJob', $data, 'default', ['priority' => 512]);

// Low priority
$queueManager->push('CleanupJob', $data, 'maintenance', ['priority' => 1024]);
```

## Advanced Features

### Job Burial and Resurrection

When jobs fail repeatedly, they are "buried" instead of deleted:

```bash
# View buried jobs
php glueful beanstalkd:tubes stats default

# Jobs can be manually "kicked" back to ready state
# (This would require additional implementation)
```

### Tube Watching

Workers can watch multiple tubes with different priorities:

```php
// In your worker configuration
$worker->daemon('beanstalkd', 'urgent,emails,default', $options);
```

### Batch Processing

```php
// Push multiple jobs efficiently
$jobs = [
    ['job' => 'EmailJob', 'data' => ['to' => 'user1@example.com']],
    ['job' => 'EmailJob', 'data' => ['to' => 'user2@example.com']],
    ['job' => 'EmailJob', 'data' => ['to' => 'user3@example.com']],
];

$jobIds = $queueManager->bulk($jobs, 'emails');
```

## Monitoring and Troubleshooting

### Health Checks

```bash
# Check if Beanstalkd is responding
php glueful beanstalkd:status

# Validate queue configuration
php glueful queue config validate
```

### Performance Monitoring

```bash
# Real-time monitoring
php glueful queue monitor --connection=beanstalkd --refresh=5

# Get detailed statistics
php glueful beanstalkd:tubes stats default
```

### Common Issues

**Connection Refused:**
- Ensure Beanstalkd server is running: `sudo systemctl status beanstalkd`
- Check host/port configuration
- Verify firewall settings

**Jobs Not Processing:**
- Check worker is running: `php glueful queue work --connection=beanstalkd`
- Verify tube names match
- Check job class exists and has `handle()` method

**High Memory Usage:**
- Monitor job payload sizes
- Implement job chunking for large datasets
- Use appropriate TTR (time-to-run) values

## License

This extension is licensed under the MIT License. See the LICENSE file for details.

## Support

- Documentation: [https://glueful.com/docs/extensions/beanstalkd-queue](https://glueful.com/docs/extensions/beanstalkd-queue)
- Issues: [https://github.com/glueful/extensions/issues](https://github.com/glueful/extensions/issues)
- Community: [https://community.glueful.com](https://community.glueful.com)