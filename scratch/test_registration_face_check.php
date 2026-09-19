<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\DocumentType;
use Illuminate\Support\Facades\Process;

echo "===============================================\n";
echo "       Testing Face Detection Integration      \n";
echo "===============================================\n\n";

// 1. Check DocumentTypes in DB
$docTypes = DocumentType::all();
echo "Found " . $docTypes->count() . " DocumentTypes in DB:\n";
foreach ($docTypes as $dt) {
    echo "  - ID: {$dt->id} | Pref: {$dt->pref} | Description: {$dt->description} | Nature: {$dt->file_type}\n";
}
echo "\n";

// 2. Test images
$validFacePath = 'C:\Users\mfarr\.gemini\antigravity\brain\fd9625de-dd53-4f03-bb05-86b6a1f6f586\test_person_face_1789640586411.jpg';
$twoFacesPath = 'C:\Users\mfarr\.gemini\antigravity\brain\fd9625de-dd53-4f03-bb05-86b6a1f6f586\test_two_people_1789640613676.jpg';
$noFacePath = 'emulator_login.png';

$testCases = [
    ['name' => 'Valid Single Face', 'path' => $validFacePath, 'expected' => true],
    ['name' => 'Two Faces', 'path' => $twoFacesPath, 'expected' => false],
    ['name' => 'No Face', 'path' => $noFacePath, 'expected' => false],
];

foreach ($testCases as $case) {
    echo "Testing [{$case['name']}]: {$case['path']}\n";
    $process = Process::timeout(30)->run([
        'node',
        base_path('scripts/face-check.js'),
        $case['path'],
    ]);

    if ($process->successful()) {
        $output = trim($process->output());
        echo "  Output STDOUT: " . $output . "\n";
        $json = json_decode($output, true);
        if ($json && isset($json['valid'])) {
            $status = ($json['valid'] === $case['expected']) ? 'PASSED ✅' : 'FAILED ❌';
            echo "  Result: {$status} (valid=" . ($json['valid'] ? 'true' : 'false') . ", reason=" . ($json['reason'] ?? 'None') . ")\n";
        } else {
            echo "  Result: FAILED ❌ (Invalid JSON)\n";
        }
    } else {
        echo "  Result: FAILED ❌ (Process failed)\n";
        echo "  Stderr: " . $process->errorOutput() . "\n";
    }
    echo "-----------------------------------------------\n";
}
