<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "📋 Field Categories:\n";
echo "====================\n\n";

$categories = DB::table('sponsor_field_categories')->get();

foreach ($categories as $cat) {
    echo "ID: {$cat->id} => {$cat->name}\n";
}
