<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "=== فحص بنية جدول attachments ===\n\n";

$columns = Schema::getColumnListing('attachments');
echo "الأعمدة المتوفرة في جدول attachments:\n";
foreach($columns as $col) {
    echo "- $col\n";
}
