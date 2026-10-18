<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publish each company's daily aggregate numbers to Dot.Analytics (nothing happens until ANALYTICS_INGEST_URL and
// ANALYTICS_INGEST_SECRET are both set). Quiet hour, UTC, because the snapshot is dated by its UTC day.
Schedule::command('farms:publish-metrics')->dailyAt('02:10')->timezone('UTC')->withoutOverlapping();

// Delete for good what has been in the Removed screen longer than REMOVED_RETENTION_DAYS (default 90).
// `php artisan farms:purge-removed --dry-run` reports without deleting.
Schedule::command('farms:purge-removed')->dailyAt('03:10')->timezone('UTC')->withoutOverlapping();
