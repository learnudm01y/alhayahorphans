<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$s = App\Models\SponsorOrphan::find(15);
$job = new App\Jobs\GenerateOrphanReportPdf($s);
$job->handle();
echo 'Done!';
