<?php

namespace App\Console\Commands\Reports;

use App\Jobs\Report\GenerateScheduledReportJob;
use App\Models\Tenant\ReportSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessScheduledReportsCommand extends Command
{
    protected $signature = 'reports:process-scheduled';

    protected $description = 'Find due report schedules and dispatch GenerateScheduledReportJob for each';

    public function handle(): int
    {
        $now = now();

        $dueSchedules = ReportSchedule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_send_at')
                  ->orWhere('next_send_at', '<=', $now);
            })
            ->with('report')
            ->get();

        if ($dueSchedules->isEmpty()) {
            $this->info('No scheduled reports are due.');
            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($dueSchedules as $schedule) {
            if ($schedule->report === null) {
                $this->warn("Schedule {$schedule->id} has no associated report — skipping.");
                continue;
            }

            GenerateScheduledReportJob::dispatch($schedule);

            // Calculate next run based on frequency
            $nextRun = $this->calculateNextRun($schedule->frequency, $now);
            $schedule->update(['next_send_at' => $nextRun]);

            $this->line("  Dispatched report schedule: {$schedule->id} (next run: {$nextRun})");
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} scheduled report job(s).");

        return self::SUCCESS;
    }

    private function calculateNextRun(string $frequency, Carbon $from): Carbon
    {
        return match ($frequency) {
            'hourly'  => $from->copy()->addHour(),
            'daily'   => $from->copy()->addDay(),
            'weekly'  => $from->copy()->addWeek(),
            'monthly' => $from->copy()->addMonth(),
            default   => $from->copy()->addDay(),
        };
    }
}
