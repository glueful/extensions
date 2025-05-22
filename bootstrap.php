<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Glueful Extensions Bootstrap File
 *
 * This bootstrap file is loaded by Composer's autoloader during initialization.
 * It sets up the environment for the extensions monorepo.
 */

// Load .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Define constants and common functions used across extensions
define('GLUEFUL_EXTENSIONS_ROOT', __DIR__);

// Add additional setup logic here if needed
