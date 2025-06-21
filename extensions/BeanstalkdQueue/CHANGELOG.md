# Changelog

All notable changes to the Beanstalkd Queue extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Web-based tube management dashboard
- Advanced job statistics and analytics
- Job scheduling with cron-like syntax
- Multi-server Beanstalkd cluster support
- Real-time job monitoring with WebSocket updates

## [1.0.0] - 2024-06-21

### Added
- **Complete Beanstalkd Protocol Support**
  - Full implementation of all Beanstalkd commands and features
  - Native socket communication with optimized performance
  - Support for all job states (ready, reserved, delayed, buried)
  - Complete tube management and job lifecycle control
- **Priority-Based Job Scheduling**
  - Configurable priority levels (0-1024) with lower numbers = higher priority
  - Automatic job ordering based on priority and creation time
  - Priority inheritance and job promotion capabilities
  - Advanced scheduling with delay and TTR (time-to-run) support
- **Advanced Tube Management**
  - Multiple named queues (tubes) for job organization
  - Tube-specific configuration and monitoring
  - Worker tube watching with priority-based processing
  - Tube statistics and health monitoring
- **Enterprise Job Processing**
  - Job burial and resurrection for failed job handling
  - Automatic retry mechanisms with exponential backoff
  - Dead letter queue support for permanently failed jobs
  - Job timeout handling and recovery
- **Performance Optimization**
  - Connection persistence and pooling for high throughput
  - Bulk job operations for efficient batch processing
  - Memory-efficient job payload handling
  - Optimized protocol communication

### Enhanced
- **Health Monitoring and Diagnostics**
  - Real-time server statistics and performance metrics
  - Tube-specific statistics and job counts
  - Connection health monitoring and alerting
  - Performance profiling and bottleneck detection
- **Management Commands**
  - CLI tools for server status and monitoring
  - Tube management commands (list, stats, purge)
  - Job inspection and manipulation tools
  - Queue health checks and diagnostics
- **Configuration Management**
  - Comprehensive environment variable configuration
  - Runtime configuration updates and validation
  - Connection string support for easy setup
  - Failover and redundancy configuration

### Security
- Secure connection handling with timeout management
- Input validation for all job data and commands
- Protection against malformed job payloads
- Secure tube name validation and sanitization

### Performance
- Connection pooling reduces overhead for high-volume processing
- Efficient binary protocol implementation
- Memory optimization for large job queues
- Intelligent connection reuse and management

### Developer Experience
- Comprehensive API documentation with examples
- Advanced debugging tools and job tracing
- Health monitoring endpoints for system diagnostics
- Extensive configuration options for fine-tuning

---

## Release Notes

### Version 1.0.0 Highlights

This initial production release establishes the BeanstalkdQueue extension as a comprehensive, high-performance queue driver for the Glueful Framework. Key features include:

- **Complete Protocol Implementation**: Full support for all Beanstalkd features and commands
- **Enterprise Performance**: Connection pooling, bulk operations, and optimized processing
- **Advanced Job Management**: Priority scheduling, tube management, and comprehensive monitoring
- **Production Reliability**: Health monitoring, error recovery, and diagnostic tools
- **Developer Experience**: Comprehensive CLI tools and management commands

### Installation and Setup

#### Beanstalkd Server Installation

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

#### Extension Configuration

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

#### Environment Variables

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

### Usage Examples

#### Basic Job Dispatching

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

#### Tube-Specific Operations

```php
// Push to specific tube
$queueManager->push('App\\Jobs\\EmailJob', $data, 'emails');

// Push high-priority job
$queueManager->push('App\\Jobs\\CriticalJob', $data, 'critical', [
    'priority' => 1,    // Highest priority
    'ttr' => 120        // 2 minutes to run
]);
```

#### Worker Processing

```bash
# Process default tube
php glueful queue work --connection=beanstalkd

# Process specific tube
php glueful queue work --connection=beanstalkd --queue=emails

# Process multiple tubes with priorities
php glueful queue work --connection=beanstalkd --queue=urgent,emails,default
```

### Management Commands

#### Check Beanstalkd Status

```bash
# Show server status and statistics
php glueful beanstalkd:status
```

#### Manage Tubes

```bash
# List all tubes
php glueful beanstalkd:tubes list

# Show tube statistics
php glueful beanstalkd:tubes stats emails

# Purge tube (remove all jobs)
php glueful beanstalkd:tubes purge failed-jobs
```

#### Queue Management

```bash
# Monitor queue in real-time
php glueful queue monitor --connection=beanstalkd

# Check queue status
php glueful queue status --connection=beanstalkd

# Manage failed jobs
php glueful queue failed list
php glueful queue failed retry all
```

### Job Priority System

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

### Advanced Features

#### Bulk Processing

```php
// Push multiple jobs efficiently
$jobs = [
    ['job' => 'EmailJob', 'data' => ['to' => 'user1@example.com']],
    ['job' => 'EmailJob', 'data' => ['to' => 'user2@example.com']],
    ['job' => 'EmailJob', 'data' => ['to' => 'user3@example.com']],
];

$jobIds = $queueManager->bulk($jobs, 'emails');
```

#### Tube Watching

Workers can watch multiple tubes with different priorities:

```php
// In your worker configuration
$worker->daemon('beanstalkd', 'urgent,emails,default', $options);
```

### Performance Characteristics

- **Throughput**: Handles thousands of jobs per second with proper configuration
- **Latency**: Sub-millisecond job dispatch and reservation
- **Memory**: Efficient memory usage with configurable job size limits
- **Reliability**: Built-in job persistence and recovery mechanisms

### Production Considerations

- **High Availability**: Use Beanstalkd clustering for production deployments
- **Monitoring**: Set up alerts for queue depth and worker health
- **Scaling**: Multiple workers can process jobs from the same tubes
- **Backup**: Consider job persistence and backup strategies for critical jobs

### Troubleshooting

#### Common Issues

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

#### Health Checks

```bash
# Check if Beanstalkd is responding
php glueful beanstalkd:status

# Validate queue configuration
php glueful queue config validate
```

#### Performance Monitoring

```bash
# Real-time monitoring
php glueful queue monitor --connection=beanstalkd --refresh=5

# Get detailed statistics
php glueful beanstalkd:tubes stats default
```

---

**Full Changelog**: https://github.com/glueful/extensions/compare/beanstalkd-queue-v0.1.0...beanstalkd-queue-v1.0.0