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