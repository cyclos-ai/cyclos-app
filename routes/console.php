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

// Poll active carrier tracking requests every 15 minutes
Schedule::command('tracking:poll-carriers')->everyFifteenMinutes();

// Refresh container tracking from JSONCargo every 2 hours (keeps status/ETA fresh)
Schedule::command('tracking:refresh-jsoncargo')->everyTwoHours();

// Sync vessel AIS positions every 30 minutes
Schedule::command('tracking:poll-ais')->everyThirtyMinutes();

// Calculate demurrage/detention charges daily at 1 AM
Schedule::command('billing:calculate-demurrage')->dailyAt('01:00');

// Generate due scheduled reports hourly
Schedule::command('reports:process-scheduled')->hourly();

// Clean up expired Passport tokens daily
Schedule::command('passport:purge --revoked --expired')->daily();

// Report billable usage to Stripe Billing Meters hourly
Schedule::command('billing:report-usage')->hourly();

// NOTE: the following were scheduled but never implemented; removed to stop the
// scheduler from erroring every run. Re-add when the commands exist:
//   webhooks:process-queue, demurrage:send-alarms, tracking:clean-logs
