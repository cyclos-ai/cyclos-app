<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Poll tracking updates every 15 minutes
Schedule::command('tracking:poll-updates')->everyFifteenMinutes();

// Recalculate demurrage/detention charges daily at 1 AM
Schedule::command('demurrage:recalculate')->dailyAt('01:00');

// Send demurrage alarm notifications daily at 7 AM
Schedule::command('demurrage:send-alarms')->dailyAt('07:00');

// Sync vessel positions every 30 minutes
Schedule::command('vessels:sync-positions')->everyThirtyMinutes();

// Process pending webhooks every minute
Schedule::command('webhooks:process-queue')->everyMinute();

// Generate scheduled reports
Schedule::command('reports:generate-scheduled')->hourly();

// Clean up old tracking poll logs (keep 30 days)
Schedule::command('tracking:clean-logs')->weekly();

// Clean up expired Passport tokens
Schedule::command('passport:purge --revoked --expired')->daily();
