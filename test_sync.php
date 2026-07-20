<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\Api\SponsorshipSyncController();
for ($i=1; $i<=5; $i++) {
    try {
        $request = Illuminate\Http\Request::create("/api/mobile/sync/full", "GET", ["page" => $i, "per_page" => 200]);
        $response = $controller->getFullSync($request);
        $data = $response->getData();
        if (isset($data->success) && $data->success === false) {
            echo "Page $i FAILED: ".$data->message."\n";
        } else {
            echo "Page $i OK: ".json_encode($data->pagination)."\n";
        }
    } catch (\Exception $e) {
        echo "Page $i THREW EXCEPTION: ".$e->getMessage()."\n";
    }
}

