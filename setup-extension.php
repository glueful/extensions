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