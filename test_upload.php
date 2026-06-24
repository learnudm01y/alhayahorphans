<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(\App\Http\Controllers\Users\GeneralRegistrationController::class);

// Create a dummy file for the chunk
$dummyFilePath = __DIR__.'/dummy_chunk.txt';
file_put_contents($dummyFilePath, 'dummy data chunk');

// Mock request
$request = \Illuminate\Http\Request::create('/users/generalRegistration/upload-chunk', 'POST', [
    'file_name' => 'test_file.jpg',
    'file_id_number' => '123456',
    'chunk_index' => 0,
    'total_chunks' => 2
]);

$request->files->set('chunk', new \Illuminate\Http\UploadedFile($dummyFilePath, 'dummy_chunk.txt', 'text/plain', null, true));

$response = $controller->uploadChunk($request);
echo "Response status: " . $response->getStatusCode() . "\n";
echo "Response body: " . $response->getContent() . "\n";
