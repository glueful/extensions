#!/usr/bin/env php
<?php

/**
 * Add .gitignore files to all extension directories
 */

$extensionsDir = __DIR__ . '/extensions';

if (!is_dir($extensionsDir)) {
    echo "Error: Extensions directory not found: $extensionsDir\n";
    exit(1);
}

$gitignoreContent = "vendor/\n.env\ncomposer.lock\n";

// Get all extension directories
$extensions = array_filter(scandir($extensionsDir), function($item) use ($extensionsDir) {
    return $item !== '.' && $item !== '..' && is_dir($extensionsDir . '/' . $item);
});

foreach ($extensions as $extension) {
    $extensionPath = $extensionsDir . '/' . $extension;
    $gitignorePath = $extensionPath . '/.gitignore';
    
    if (!file_exists($gitignorePath)) {
        file_put_contents($gitignorePath, $gitignoreContent);
        echo "Created .gitignore in $extension\n";
    } else {
        // Check if vendor/ is already in .gitignore
        $existingContent = file_get_contents($gitignorePath);
        
        $linesToAdd = [];
        if (strpos($existingContent, 'vendor/') === false) {
            $linesToAdd[] = 'vendor/';
        }
        if (strpos($existingContent, '.env') === false) {
            $linesToAdd[] = '.env';
        }
        if (strpos($existingContent, 'composer.lock') === false) {
            $linesToAdd[] = 'composer.lock';
        }
        
        if (!empty($linesToAdd)) {
            $existingContent = rtrim($existingContent, "\n") . "\n" . implode("\n", $linesToAdd) . "\n";
            file_put_contents($gitignorePath, $existingContent);
            echo "Updated .gitignore in $extension (added: " . implode(', ', $linesToAdd) . ")\n";
        } else {
            echo "No changes needed for .gitignore in $extension\n";
        }
    }
}

echo "\nDone! All extensions now have proper .gitignore files.\n";