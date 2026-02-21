<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== فحص جدول category_of_relations ===\n\n";

$relations = DB::table('category_of_relations')->orderBy('id')->get();

foreach ($relations as $relation) {
    echo "ID: {$relation->id} - {$relation->attribute}\n";
}

echo "\n\nاختبار getRelation(1):\n";
$relation = DB::table('category_of_relations')->where('id', 1)->value('attribute');
echo "النتيجة: " . ($relation ?? 'NULL') . "\n";
