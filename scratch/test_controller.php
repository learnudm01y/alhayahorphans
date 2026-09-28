<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Users\FaceRestoreController;

$request = Request::create('/api/face/restore', 'POST');
$real = file_get_contents(base_path('storage/app/public/uploads/000208/12_000208_438396335.jpg'));
$request->files->set('image', UploadedFile::fake()->createWithContent('test.jpg', $real));
$request->request->set('file_type', '12');

$controller = new FaceRestoreController();
$start = microtime(true);
$response = $controller->restore($request);
$elapsed = round((microtime(true) - $start) * 1000);

$payload = $response->getData(true);
echo "HTTP {$response->getStatusCode()} in {$elapsed}ms\n";
echo 'keys: ' . implode(',', array_keys($payload)) . "\n";
echo 'ok: ' . var_export($payload['ok'] ?? null, true) . "\n";
echo 'applied: ' . var_export($payload['applied'] ?? null, true) . "\n";
if (!empty($payload['image'])) {
    $raw = base64_decode(preg_replace('/^data:image\/jpeg;base64,/', '', $payload['image']));
    file_put_contents('scratch/api_result.jpg', $raw);
    $info = @getimagesize('scratch/api_result.jpg');
    echo 'decoded bytes: ' . strlen($raw) . ' size: ' . ($info ? $info[0] . 'x' . $info[1] : 'FAIL')
        . (($info && $info[0] === 400 && $info[1] === 600) ? ' TARGET OK' : ' SIZE MISMATCH') . "\n";
}
if (!empty($payload['check'])) echo 'check: ' . json_encode($payload['check'], JSON_UNESCAPED_UNICODE) . "\n";
if (!empty($payload['reason'])) echo 'reason: ' . $payload['reason'] . "\n";

// حالة نوع غير 12: يُرمَّم دون تغيير المقاس (المدخل 150x225 فيجب أن يخرج 150x225)
$other = file_get_contents(base_path('scratch/t_v3.jpg'));
$request2 = Request::create('/api/face/restore', 'POST');
$request2->files->set('image', UploadedFile::fake()->createWithContent('test2.jpg', $other));
$request2->request->set('file_type', '5');
$payload2 = (new FaceRestoreController())->restore($request2)->getData(true);
echo "other-type => ok=" . var_export($payload2['ok'] ?? null, true)
    . ' applied=' . var_export($payload2['applied'] ?? null, true)
    . ' w=' . ($payload2['width'] ?? '-') . ' h=' . ($payload2['height'] ?? '-')
    . ' reason=' . ($payload2['reason'] ?? '-') . "\n";
if (!empty($payload2['image'])) {
    $raw2 = base64_decode(preg_replace('/^data:image\/jpeg;base64,/', '', $payload2['image']));
    file_put_contents('scratch/api_result_type5.jpg', $raw2);
    $info2 = @getimagesize('scratch/api_result_type5.jpg');
    echo 'decoded size: ' . ($info2 ? $info2[0] . 'x' . $info2[1] : 'FAIL')
        . (($info2 && $info2[0] === 150 && $info2[1] === 225) ? ' PRESERVE OK' : ' SIZE MISMATCH') . "\n";
}
