<?php // phpcs:ignore PSR1.Files.SideEffects.FoundWithSymbols

/**
 * Extension build script
 *
 * Packages extensions into distributable format for the marketplace
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Extension to build, or 'all' for all extensions
$extensionName = $argv[1] ?? 'all';
$buildDir = __DIR__ . '/../build';

// Ensure build directory exists
if (!is_dir($buildDir)) {
    mkdir($buildDir, 0755, true);
}

/**
 * Build a single extension
 */
function buildExtension(string $name): void
{
    global $buildDir;

    echo "Building extension: $name\n";

    $extensionDir = __DIR__ . "/../extensions/$name";
    $outputFile = "$buildDir/$name.gluex";

    if (!is_dir($extensionDir)) {
        echo "Error: Extension directory not found: $extensionDir\n";
        exit(1);
    }

    // Read extension metadata
    $metadataFile = "$extensionDir/extension.json";
    if (!file_exists($metadataFile)) {
        echo "Error: extension.json not found in $extensionDir\n";
        exit(1);
    }

    $metadata = json_decode(file_get_contents($metadataFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Error: Invalid JSON in extension.json - " . json_last_error_msg() . "\n";
        exit(1);
    }

    // Validate required metadata fields
    $requiredFields = ['name', 'displayName', 'version', 'description'];
    foreach ($requiredFields as $field) {
        if (empty($metadata[$field])) {
            echo "Error: Missing required field '$field' in extension.json\n";
            exit(1);
        }
    }

    $version = $metadata['version'] ?? '1.0.0';

    // Validate that main PHP file exists
    $mainClass = $metadata['name'];
    $mainFile = "$extensionDir/$mainClass.php";
    if (!file_exists($mainFile)) {
        echo "Warning: Main extension file $mainClass.php not found in extension directory\n";
    }

    // Create a temporary directory for the build
    $tmpDir = sys_get_temp_dir() . '/glueful-extension-' . uniqid();
    mkdir($tmpDir, 0755, true);

    // Copy extension files
    // List all files and directories in the extension directory except for standard excludes
    $excludes = ['.', '..', '.git', '.github', '.vscode', 'node_modules', 'vendor', 'tests', '.DS_Store', '.gitignore'];
    $items = scandir($extensionDir);

    foreach ($items as $item) {
        if (in_array($item, $excludes)) {
            continue;
        }

        $itemPath = "$extensionDir/$item";

        if (is_dir($itemPath)) {
            // Copy directory
            shell_exec("cp -r $itemPath $tmpDir/");
            echo "  - Copied directory: $item\n";
        } elseif (is_file($itemPath) && $item !== 'extension.json' && $item !== 'README.md') {
            // Copy file (extension.json and README.md are handled separately)
            copy($itemPath, "$tmpDir/$item");
            echo "  - Copied file: $item\n";
        }
    }

    // Copy metadata files
    copy($metadataFile, "$tmpDir/extension.json");
    if (file_exists("$extensionDir/README.md")) {
        copy("$extensionDir/README.md", "$tmpDir/README.md");
    }

    // Create the archive
    $outputFile = "$buildDir/$name-$version.gluex";
    $currentDir = getcwd();
    chdir($tmpDir);

    echo "  - Creating package archive: $name-$version.gluex\n";
    shell_exec("zip -r $outputFile .");
    chdir($currentDir);

    // Clean up
    shell_exec("rm -rf $tmpDir");

    if (file_exists($outputFile)) {
        $fileSize = round(filesize($outputFile) / 1024, 2);
        echo "✅ Extension built successfully: $name-$version.gluex ($fileSize KB)\n";
    } else {
        echo "❌ Failed to build extension: $name-$version.gluex\n";
        exit(1);
    }
}

// Build all extensions or just the specified one
echo "\n📦 Starting build process...\n";

$successCount = 0;
$startTime = microtime(true);

if ($extensionName === 'all') {
    $extensions = array_filter(
        scandir(__DIR__ . '/../extensions'),
        fn($dir) => $dir !== '.' && $dir !== '..' && is_dir(__DIR__ . "/../extensions/$dir")
    );

    $totalExtensions = count($extensions);
    echo "Found $totalExtensions extensions to build\n\n";

    foreach ($extensions as $ext) {
        try {
            buildExtension($ext);
            $successCount++;
        } catch (Exception $e) {
            echo "❌ Error building extension $ext: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
} else {
    try {
        buildExtension($extensionName);
        $successCount++;
    } catch (Exception $e) {
        echo "❌ Error building extension $extensionName: " . $e->getMessage() . "\n";
    }
}

$endTime = microtime(true);
$elapsedTime = round($endTime - $startTime, 2);

echo "---------------------------------------------\n";
echo "🎉 Build process completed in {$elapsedTime}s.\n";
if ($extensionName === 'all') {
    echo "✅ Successfully built $successCount of $totalExtensions extensions.\n";
} else {
    echo $successCount ? "✅ Extension built successfully.\n" : "❌ Failed to build extension.\n";
}
echo "📁 Output location: $buildDir\n";
