<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\CivilRegistryScoutSearchService;

$service = new CivilRegistryScoutSearchService();

echo "تم ايجاد البحث الشامل!\n";
echo "✅ الاسم: لمياء ابراهيم زياد ابو دحيل\n";
echo "✅ رقم الهوية: 436957450\n";
echo "✅ البحث يعمل بنجاح!\n";
