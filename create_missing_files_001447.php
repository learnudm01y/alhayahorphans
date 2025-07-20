<?php
/**
 * Script to create missing files for record 001447
 * Based on Laravel log entries showing missing files
 */

// Files that need to be created (from the log)
$missingFiles = [
    'TE102_001447_82369962.png',
    'TES-11_001447_2944445.png',
    'TES-11_001447_44444424.png',
    'TES-11_001447_82055565.jpg',
    'TES-11_001447_939512036.jpg',
    '001447_1752637121_0d77Eg.png',
    '001447_1752637121_CU28Wf.jpg',
    '001447_1752637121_KDK7w3.png',
    '001447_1752637121_Zp6Fib.jpg',
    '001447_1752637121_fSanRJ.png',
    '001447_1752637121_pxBhA4.jpg'
];

// Target directory
$uploadDir = __DIR__ . '/storage/app/public/uploads/001447/';

// Ensure directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "✅ Created directory: {$uploadDir}\n";
}

// Create placeholder files
foreach ($missingFiles as $fileName) {
    $filePath = $uploadDir . $fileName;

    if (file_exists($filePath)) {
        echo "⚠️  File already exists: {$fileName}\n";
        continue;
    }

    // Determine file type by extension
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($extension === 'png') {
        // Create a simple PNG placeholder
        $content = createPngPlaceholder($fileName);
    } elseif ($extension === 'jpg' || $extension === 'jpeg') {
        // Create a simple JPEG placeholder
        $content = createJpegPlaceholder($fileName);
    } else {
        // Create a text file for unknown types
        $content = "Placeholder file for: {$fileName}\nCreated: " . date('Y-m-d H:i:s');
    }

    if (file_put_contents($filePath, $content)) {
        echo "✅ Created: {$fileName} (" . formatBytes(strlen($content)) . ")\n";
    } else {
        echo "❌ Failed to create: {$fileName}\n";
    }
}

echo "\n🎉 File creation completed!\n";
echo "📂 Files location: {$uploadDir}\n";
echo "🌐 Accessible via: http://localhost:8000/storage/uploads/001447/\n";

/**
 * Create a minimal PNG placeholder
 */
function createPngPlaceholder($filename) {
    // Simple 1x1 transparent PNG
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
}

/**
 * Create a minimal JPEG placeholder
 */
function createJpegPlaceholder($filename) {
    // Simple 1x1 JPEG
    return base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
}

/**
 * Format file size in human readable format
 */
function formatBytes($size, $precision = 2) {
    $units = array('B','KB','MB','GB','TB','PB','EB','ZB','YB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}
