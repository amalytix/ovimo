<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monitor sources every minute (the command checks which sources actually need monitoring)
Schedule::command('sources:monitor')->everyMinute()->withoutOverlapping();

// Prune old activity logs daily at midnight
Schedule::job(new \App\Jobs\PruneOldActivityLogs)->dailyAt('00:00');

// Process scheduled publishing every minute
Schedule::job(new \App\Jobs\ProcessScheduledPublishing)->everyMinute();
