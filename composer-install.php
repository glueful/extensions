#!/usr/bin/env php
<?php

/**
 * Composer Install Script with Dynamic Path Replacement
 *
 * This script loads the .env file, replaces ${GLUEFUL_PATH} in composer.json,
 * runs composer install, then restores the original composer.json
 */

function loadEnvFile($envPath)
{
    if (!file_exists($envPath)) {
        echo "Error: .env file not found at $envPath\n";
        exit(1);
    }

    $env = [];
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $env[$key] = $value;
        }
    }

    return $env;
}

// Get the extension directory from command line argument or current directory
$extensionDir = $argc > 1 ? $argv[1] : getcwd();

if (!is_dir($extensionDir)) {
    echo "Error: Directory not found: $extensionDir\n";
    exit(1);
}

// Check if composer.json exists in the extension directory
$composerJsonPath = $extensionDir . '/composer.json';
if (!file_exists($composerJsonPath)) {
    echo "Error: composer.json not found in $extensionDir\n";
    exit(1);
}

// Look for .env file in the extensions root directory
$envFile = __DIR__ . '/extensions/.env';
if (!file_exists($envFile)) {
    // Try alternative location
    $envFile = __DIR__ . '/.env';
}

if (!file_exists($envFile)) {
    echo "Error: .env file not found. Please run setup.php first.\n";
    exit(1);
}

// Load environment variables
$env = loadEnvFile($envFile);

if (!isset($env['GLUEFUL_PATH'])) {
    echo "Error: GLUEFUL_PATH not found in .env file\n";
    exit(1);
}

$gluefulPath = $env['GLUEFUL_PATH'];

// Verify the Glueful path exists
if (!is_dir($gluefulPath)) {
    echo "Error: Glueful path does not exist: $gluefulPath\n";
    exit(1);
}

echo "Loading environment variables...\n";
echo "GLUEFUL_PATH: $gluefulPath\n";
echo "Extension directory: $extensionDir\n\n";

// Read the original composer.json
$originalComposerJson = file_get_contents($composerJsonPath);
$backupPath = $composerJsonPath . '.backup';

// Create a backup
file_put_contents($backupPath, $originalComposerJson);

// Replace ${GLUEFUL_PATH} with the actual path
$updatedComposerJson = str_replace('${GLUEFUL_PATH}', $gluefulPath, $originalComposerJson);

// Write the updated composer.json
file_put_contents($composerJsonPath, $updatedComposerJson);

echo "Temporarily updated composer.json with actual path...\n";

// Change to extension directory
chdir($extensionDir);

// Run composer install
echo "Running composer install...\n";
$result = passthru('composer install', $exitCode);

// Restore the original composer.json with placeholder
$restoredContent = str_replace($gluefulPath, '${GLUEFUL_PATH}', $originalComposerJson);
file_put_contents($composerJsonPath, $restoredContent);
unlink($backupPath);

echo "Restored original composer.json with placeholder\n";

if ($exitCode === 0) {
    echo "\nComposer install completed successfully!\n";
    echo "The ExtensionServiceProvider should now be available.\n";
} else {
    echo "\nComposer install failed with exit code: $exitCode\n";
    exit($exitCode);
}
