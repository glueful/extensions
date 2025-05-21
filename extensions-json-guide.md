# Using an extensions.json File for Tracking Extensions

Yes, having an `extensions.json` file to track installed extensions in a Glueful project is an excellent practice. This approach offers several benefits:

## Benefits of an extensions.json File

1. **Single Source of Truth**: Creates one authoritative record of all installed extensions
2. **Version Management**: Easily track which version of each extension is installed
3. **Dependency Tracking**: Can record dependencies between extensions
4. **Configuration Storage**: Store extension-specific settings
5. **Environment Support**: Enable different extensions in different environments (dev/staging/production)
6. **Git-Friendly**: Text-based format makes it easy to track changes in version control

## Suggested Structure

Here's a suggested structure for your `extensions.json` file:

```json
{
  "extensions": {
    "pdf-generator": {
      "version": "1.2.0",
      "enabled": true,
      "installPath": "extensions/pdf-generator",
      "config": {
        "defaultPaperSize": "A4"
      }
    },
    "data-import": {
      "version": "0.9.1",
      "enabled": false,
      "installPath": "extensions/data-import",
      "config": {
        "allowedFormats": ["csv", "xlsx"]
      }
    }
  },
  "environments": {
    "development": {
      "enabledExtensions": ["pdf-generator", "data-import"]
    },
    "production": {
      "enabledExtensions": ["pdf-generator"]
    }
  }
}
```

## Integration with ExtensionsManager

Your `ExtensionsManager` class can use this file as its primary data source:

```php
public static function getEnabledExtensions(): array
{
    $config = self::loadExtensionsConfig();
    $enabled = [];
    
    foreach ($config['extensions'] as $name => $ext) {
        if ($ext['enabled'] === true) {
            $enabled[] = $name;
        }
    }
    
    return $enabled;
}

private static function loadExtensionsConfig(): array
{
    $configFile = self::getConfigPath();
    
    if (!file_exists($configFile)) {
        // Create default config if it doesn't exist
        return self::createDefaultConfig();
    }
    
    return json_decode(file_get_contents($configFile), true) ?: [];
}
```

## Best Practices

1. **Location**: Store the file at the root of your project or in a config directory
2. **Schema Validation**: Validate the file structure when loading
3. **Auto-update**: Update the file automatically when extensions are installed/uninstalled
4. **Environment Awareness**: Support different configurations per environment
5. **Backup**: Keep backups before making changes
6. **Metadata**: Include additional metadata like author, description, and requirements

This approach is similar to `package.json` in Node.js projects or `composer.json` in PHP projects, which have proven to be effective for managing dependencies and configurations.

## Using Both extensions.json and extensions.php

It's recommended to use both files for complementary purposes:

### 1. extensions.json
- Serves as the **record of installed extensions** and their metadata
- Acts as a **manifest of what's available** in the system
- Tracks **version information** and **installation paths**
- Stores extension-specific **configuration options**

### 2. api/extensions.php
- Contains **system-level configuration** for the extensions subsystem
- Defines **global settings** like directory paths, security policies
- Houses **environment detection logic** 
- Provides **fallback/default configuration**

## Recommended Implementation Pattern

```php
// api/extensions.php
return [
    // System-level configuration
    'paths' => [
        'extensions_dir' => base_path('extensions'),
        'cache_dir' => storage_path('extensions/cache'),
    ],
    
    // Default settings (used if extensions.json is missing)
    'defaults' => [
        'enabled' => ['core-tools'], // Core extensions always enabled
    ],
    
    // Security settings
    'security' => [
        'allow_remote_installation' => env('ALLOW_REMOTE_EXTENSIONS', false),
        'verify_signatures' => env('VERIFY_EXTENSION_SIGNATURES', true),
    ],
    
    // Extensions.json location (can be customized)
    'config_file' => base_path('extensions.json'),
    
    // Environment mappings
    'environments' => [
        'local' => 'development',
        'dev' => 'development',
        'staging' => 'staging',
        'prod' => 'production',
    ]
];
```

## Benefits of This Dual Approach

1. **Separation of concerns**: System configuration vs. extension inventory
2. **Flexibility**: Change system behavior without affecting extension list
3. **Portability**: Move `extensions.json` between installations
4. **Security**: Keep sensitive configuration in PHP (not exposed in JSON)
5. **Compatibility**: Works with both simple and complex setups
