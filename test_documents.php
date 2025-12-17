<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// عرض أنواع الوثائق
echo "Document Types in database:\n";
echo "============================\n\n";

$documents = App\Models\DocumentType::all(['id', 'description', 'pref', 'basic_enabled', 'family_enabled', 'deceased_enabled']);

foreach ($documents as $doc) {
    echo "ID: {$doc->id}\n";
    echo "Description: {$doc->description}\n";
    echo "Prefix: {$doc->pref}\n";
    echo "Basic: " . ($doc->basic_enabled ? 'Yes' : 'No') . "\n";
    echo "Family: " . ($doc->family_enabled ? 'Yes' : 'No') . "\n";
    echo "Deceased: " . ($doc->deceased_enabled ? 'Yes' : 'No') . "\n";
    echo "----------------------------\n";
}

echo "\nTotal: " . $documents->count() . " document types\n";
