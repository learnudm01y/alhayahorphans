<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\Api\SponsorshipSyncController();
$request = Illuminate\Http\Request::create("/api/mobile/sync/initial", "GET");
$response = $controller->getInitialSync($request);
echo json_encode(array_keys((array)$response->getData()->data))."\n";

