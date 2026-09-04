<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==========================================
// ⏰ Scheduled Automated Tasks (Cron / Worker)
// ==========================================

// 1. Automatically warm up Redis POS cache every morning at 05:00 AM
\Illuminate\Support\Facades\Schedule::job(new \App\Jobs\WarmProductCacheJob)->dailyAt('05:00');

// 2. Automatically clean soft-deleted trash items older than 30 days
\Illuminate\Support\Facades\Schedule::job(new \App\Jobs\PruneOldTrashJob(30))->dailyAt('02:00');

// 3. Prune old failed jobs and completed batches
\Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=72')->daily();
\Illuminate\Support\Facades\Schedule::command('queue:prune-batches --hours=24')->daily();
