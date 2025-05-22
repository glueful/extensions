#!/usr/bin/env php
<?php

/**
 * Glueful Extensions Development Setup Script
 *
 * This script helps configure the extensions monorepo development environment
 */

// Welcome message
echo "\n";
echo "========================================\n";
echo "Glueful Extensions Monorepo Setup\n";
echo "========================================\n\n";

// Ask for Glueful path
echo "Please enter the absolute path to your Glueful installation:\n";
$gluefulPath = trim(fgets(STDIN));

// Validate the path
if (!is_dir($gluefulPath)) {
    echo "Error: The specified path does not exist or is not a directory.\n";
    exit(1);
}

// Check for Glueful composer.json to verify this is a Glueful installation
if (!file_exists($gluefulPath . '/composer.json')) {
    echo "Error: This doesn't appear to be a Glueful installation (composer.json not found).\n";
    exit(1);
}

// Set environment variable
$envFile = __DIR__ . '/.env';
file_put_contents($envFile, "GLUEFUL_PATH=\"$gluefulPath\"\n");
echo "Created monorepo-level .env file with Glueful path.\n";

// Update repositories path in composer.json
$composerJsonPath = __DIR__ . '/composer.json';
$composerJson = json_decode(file_get_contents($composerJsonPath), true);

// Make sure we have a repositories section
if (!isset($composerJson['repositories'])) {
    $composerJson['repositories'] = [];
}

// Add or update path repository
$foundRepo = false;
foreach ($composerJson['repositories'] as &$repo) {
    if ($repo['type'] === 'path') {
        $repo['url'] = '${GLUEFUL_PATH}';
        $foundRepo = true;
        break;
    }
}

if (!$foundRepo) {
    $composerJson['repositories'][] = [
        'type' => 'path',
        'url' => '${GLUEFUL_PATH}'
    ];
}

// Add vlucas/phpdotenv if not already present
if (!isset($composerJson['require']['vlucas/phpdotenv'])) {
    $composerJson['require']['vlucas/phpdotenv'] = "^5.6";
}

// Write updated composer.json
file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Updated composer.json with repository path and dotenv dependency.\n";

// Create a .gitignore file if it doesn't exist
$gitignorePath = __DIR__ . '/.gitignore';
if (!file_exists($gitignorePath)) {
    file_put_contents($gitignorePath, "vendor/\n.env\n");
    echo "Created .gitignore file.\n";
} else {
    // Make sure .env is in .gitignore
    $gitignore = file_get_contents($gitignorePath);
    if (strpos($gitignore, '.env') === false) {
        file_put_contents($gitignorePath, $gitignore . "\n.env\n");
        echo "Updated .gitignore file.\n";
    }
}

// Create a setup-extension.php script
$setupExtensionScript = <<<'EOT'
#!/usr/bin/env php
<?php

/**
 * Individual Extension Setup Script
 *
 * This script configures an individual extension to use the monorepo environment
 */

if ($argc < 2) {
    echo "Usage: php setup-extension.php <extension-name>\n";
    echo "Example: php setup-extension.php EmailNotification\n";
    exit(1);
}

$extensionName = $argv[1];
$extensionDir = __DIR__ . '/extensions/' . $extensionName;

if (!is_dir($extensionDir)) {
    echo "Error: Extension directory not found: $extensionDir\n";
    exit(1);
}

// Load the monorepo .env file
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if (isset($env['GLUEFUL_PATH'])) {
        $gluefulPath = $env['GLUEFUL_PATH'];
    } else {
        echo "Error: GLUEFUL_PATH not found in .env file\n";
        exit(1);
    }
} else {
    echo "Error: Monorepo .env file not found. Please run setup.php first.\n";
    exit(1);
}

// Update extension's composer.json
$composerJsonPath = "$extensionDir/composer.json";
if (!file_exists($composerJsonPath)) {
    echo "Error: composer.json not found in extension directory\n";
    exit(1);
}

$composerJson = json_decode(file_get_contents($composerJsonPath), true);

// Update repositories section
if (!isset($composerJson['repositories'])) {
    $composerJson['repositories'] = [];
}

$foundRepo = false;
foreach ($composerJson['repositories'] as &$repo) {
    if ($repo['type'] === 'path') {
        $repo['url'] = '${GLUEFUL_PATH}';
        $foundRepo = true;
        break;
    }
}

if (!$foundRepo) {
    $composerJson['repositories'][] = [
        'type' => 'path',
        'url' => '${GLUEFUL_PATH}'
    ];
}

// Write updated composer.json
file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Updated $extensionName's composer.json with repository path.\n";

// Prompt to run composer install
echo "\nSetup complete! Would you like to run 'composer install' for $extensionName now? (y/n): ";
$answer = strtolower(trim(fgets(STDIN)));
if ($answer === 'y' || $answer === 'yes') {
    echo "\nRunning composer install...\n";
    chdir($extensionDir);
    passthru('composer install');
    echo "\nExtension development environment is ready!\n";
} else {
    echo "\nPlease run 'composer install' manually in the extension directory.\n";
}

echo "\nThank you for contributing to the $extensionName extension!\n";
EOT;

file_put_contents(__DIR__ . '/setup-extension.php', $setupExtensionScript);
chmod(__DIR__ . '/setup-extension.php', 0755);
echo "Created setup-extension.php script for configuring individual extensions.\n";

// Create bootstrap.php for autoloading environment variables
$bootstrapScript = <<<'EOT'
<?php

/**
 * Bootstrap file for the extensions monorepo
 * Loads environment variables and sets up autoloading
 */

// Load .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}
EOT;

file_put_contents(__DIR__ . '/bootstrap.php', $bootstrapScript);
echo "Created bootstrap.php for environment variable loading.\n";

// Update composer scripts
$composerJson['scripts']['setup'] = 'php setup.php';
$composerJson['scripts']['setup:extension'] = 'php setup-extension.php';
$composerJson['autoload']['files'][] = 'bootstrap.php';
file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Prompt to run composer install for the monorepo
echo "\nMonorepo setup complete! Would you like to run 'composer install' for the monorepo now? (y/n): ";
$answer = strtolower(trim(fgets(STDIN)));
if ($answer === 'y' || $answer === 'yes') {
    echo "\nRunning composer install...\n";
    passthru('composer install');
    echo "\nMonorepo development environment is ready!\n";
} else {
    echo "\nPlease run 'composer install' manually to complete the setup.\n";
}

echo "\nTo set up individual extensions, run:\n";
echo "php setup-extension.php <extension-name>\n";
echo "\nThank you for contributing to Glueful Extensions!\n";
