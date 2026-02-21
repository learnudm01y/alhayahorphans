<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Failed Jobs Analysis ===\n\n";

$failedJobs = DB::table('failed_jobs')
    ->latest('failed_at')
    ->take(5)
    ->get();

foreach ($failedJobs as $job) {
    $payload = json_decode($job->payload, true);

    echo "Failed at: {$job->failed_at}\n";
    echo "Job: {$payload['displayName']}\n";

    // استخراج أول 500 حرف من الخطأ
    $exception = substr($job->exception, 0, 800);
    echo "Error: {$exception}\n";
    echo str_repeat('-', 80) . "\n\n";
}

echo "\nTotal failed jobs: " . DB::table('failed_jobs')->count() . "\n";
echo "Jobs in queue: " . DB::table('jobs')->count() . "\n";
