#!/usr/bin/env php
<?php

/**
 * Install Git hooks to prevent committing local paths in composer.json
 */

$hooksDir = __DIR__ . '/../.git/hooks';

if (!is_dir($hooksDir)) {
    echo "Error: Git hooks directory not found. Make sure you're in a git repository.\n";
    exit(1);
}

$preCommitHook = <<<'EOT'
#!/bin/sh
#
# Pre-commit hook to prevent committing local paths in composer.json files
#

echo "Checking for local paths in composer.json files..."

# Find all composer.json files that are staged for commit
for file in $(git diff --cached --name-only | grep composer.json); do
    if [ -f "$file" ]; then
        # Check if the file contains local absolute paths
        if grep -q '"url": "/[^"]*"' "$file"; then
            echo "ERROR: Found local absolute path in $file"
            echo "Please use \${GLUEFUL_PATH} placeholder instead of absolute paths."
            echo ""
            echo "Found:"
            grep '"url": "/[^"]*"' "$file"
            echo ""
            echo "Should be:"
            echo '        "url": "${GLUEFUL_PATH}"'
            echo ""
            exit 1
        fi
        
        # Check for other common local path patterns
        if grep -qE '"url": ".*(localhost|Users|home)/.*"' "$file"; then
            echo "ERROR: Found local path in $file"
            echo "Please use \${GLUEFUL_PATH} placeholder instead."
            exit 1
        fi
    fi
done

echo "✓ No local paths found in composer.json files"
exit 0
EOT;

$preCommitPath = $hooksDir . '/pre-commit';

// Install pre-commit hook
file_put_contents($preCommitPath, $preCommitHook);
chmod($preCommitPath, 0755);

echo "✓ Installed pre-commit hook to prevent committing local paths\n";

// Also create a script to fix composer.json files
$fixComposerScript = <<<'EOT'
#!/usr/bin/env php
<?php

/**
 * Fix composer.json files to use placeholder instead of local paths
 */

function fixComposerJson($file) {
    if (!file_exists($file)) {
        return false;
    }
    
    $content = file_get_contents($file);
    $original = $content;
    
    // Replace absolute paths with placeholder
    $content = preg_replace('/"url": "\/[^"]*"/', '"url": "${GLUEFUL_PATH}"', $content);
    
    // Replace other local path patterns
    $content = preg_replace('/"url": "[^"]*localhost[^"]*"/', '"url": "${GLUEFUL_PATH}"', $content);
    $content = preg_replace('/"url": "[^"]*Users[^"]*"/', '"url": "${GLUEFUL_PATH}"', $content);
    $content = preg_replace('/"url": "[^"]*home[^"]*"/', '"url": "${GLUEFUL_PATH}"', $content);
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        return true;
    }
    
    return false;
}

// Find all composer.json files in extensions
$extensionsDir = __DIR__ . '/../extensions';
$fixed = [];

if (is_dir($extensionsDir)) {
    foreach (glob($extensionsDir . '/*/composer.json') as $composerFile) {
        if (fixComposerJson($composerFile)) {
            $fixed[] = $composerFile;
        }
    }
}

if (!empty($fixed)) {
    echo "Fixed the following composer.json files:\n";
    foreach ($fixed as $file) {
        echo "  - $file\n";
    }
    echo "\nPlease review the changes and commit them.\n";
} else {
    echo "No composer.json files needed fixing.\n";
}
EOT;

file_put_contents(__DIR__ . '/../fix-composer-paths.php', $fixComposerScript);
chmod(__DIR__ . '/../fix-composer-paths.php', 0755);

echo "✓ Created fix-composer-paths.php script\n";
echo "\nTo fix existing composer.json files with local paths, run:\n";
echo "php fix-composer-paths.php\n";