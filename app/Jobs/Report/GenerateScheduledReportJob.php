<?php

namespace App\Jobs\Report;

use App\Models\Tenant\ReportSchedule;
use App\Notifications\ReportReadyNotification;
use App\Services\Report\ReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly ReportSchedule $reportSchedule,
    ) {
        $this->onQueue('reports');
    }

    public function handle(ReportGenerator $generator): void
    {
        $schedule = $this->reportSchedule;
        $report   = $schedule->report;

        Log::info('GenerateScheduledReportJob: generating report', [
            'report_id'          => $report->id,
            'report_schedule_id' => $schedule->id,
        ]);

        $data   = $generator->generate($report);
        $format = $schedule->format ?? 'csv';

        $filePath = match ($format) {
            'xlsx' => $generator->exportToExcel($report, $data),
            'pdf'  => $generator->exportToPdf($report, $data),
            default => $generator->exportToCsv($report, $data),
        };

        $schedule->update(['last_run_at' => now()]);

        $recipients = $schedule->recipients ?? [];

        foreach ($recipients as $recipientEmail) {
            try {
                \Illuminate\Support\Facades\Notification::route('mail', $recipientEmail)
                    ->notify(new ReportReadyNotification($report, $schedule, $filePath));
            } catch (\Throwable $e) {
                Log::warning('GenerateScheduledReportJob: failed to notify recipient', [
                    'email' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GenerateScheduledReportJob: completed', [
            'report_id'   => $report->id,
            'file_path'   => $filePath,
            'recipients'  => count($recipients),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateScheduledReportJob: failed', [
            'report_schedule_id' => $this->reportSchedule->id,
            'error'              => $exception->getMessage(),
        ]);
    }
}
