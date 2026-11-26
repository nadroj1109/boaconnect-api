<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('barangay-officials:terminate-expired')
    ->dailyAt('00:05')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('app:reconcile-blockchain-anchors --limit=100')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Schedule::command('app:release-stale-blockchain-submission-claims --minutes=15 --limit=100')
    ->everyFiveMinutes()
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

    
/*
On your production server, make sure Laravel's scheduler is enabled through cron:

* * * * * cd /path/to/your/boaconnect-api && php artisan schedule:run >> /dev/null 2>&1
*/