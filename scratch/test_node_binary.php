<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Process;

$nodeBinary = 'node';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    if (file_exists('C:\\Program Files\\nodejs\\node.exe')) {
        $nodeBinary = 'C:\\Program Files\\nodejs\\node.exe';
    } elseif (file_exists(base_path('node20.exe'))) {
        $nodeBinary = base_path('node20.exe');
    } elseif (file_exists(base_path('node18.exe'))) {
        $nodeBinary = base_path('node18.exe');
    }
}

echo "Selected Node Binary: " . $nodeBinary . PHP_EOL;

$testImagePath = 'C:\Users\mfarr\.gemini\antigravity\brain\fd9625de-dd53-4f03-bb05-86b6a1f6f586\test_person_face_1789640586411.jpg';

$process = Process::timeout(30)->run([
    $nodeBinary,
    base_path('scripts/face-check.js'),
    $testImagePath,
]);

echo "Process exit code: " . $process->exitCode() . PHP_EOL;
echo "Process stdout: " . trim($process->output()) . PHP_EOL;
echo "Process stderr: " . trim($process->errorOutput()) . PHP_EOL;
