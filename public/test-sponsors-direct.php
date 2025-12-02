<?php
// اختبار مباشر لعرض البيانات
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Sponsor;

header('Content-Type: application/json; charset=utf-8');

try {
    $sponsors = Sponsor::with('bankName')->orderBy('created_at', 'desc')->get();

    echo json_encode([
        'success' => true,
        'count' => $sponsors->count(),
        'data' => $sponsors->map(function($s) {
            return [
                'id' => $s->id,
                'file_id' => $s->file_id,
                'sponsor_name' => $s->sponsor_name,
                'sponsor_short_name' => $s->sponsor_short_name,
                'sponsor_phone_number' => $s->sponsor_phone_number,
                'sponsor_email' => $s->sponsor_email,
                'bank_name' => $s->bankName ? $s->bankName->description : '-',
                'sponsor_account_bank_number' => $s->sponsor_account_bank_number,
                'created_at' => $s->created_at->format('Y-m-d H:i:s')
            ];
        })
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
