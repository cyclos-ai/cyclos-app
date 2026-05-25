<?php

namespace App\Notifications;

use App\Models\Tenant\Report;
use App\Models\Tenant\ReportSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Report $report,
        public readonly ReportSchedule $schedule,
        public readonly string $filePath,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reportName = $this->report->name ?? 'Scheduled Report';
        $generatedAt = now()->toDateTimeString();

        $mail = (new MailMessage)
            ->subject("Report Ready: {$reportName}")
            ->greeting("Your report is ready")
            ->line("**{$reportName}** has been generated and is attached to this email.")
            ->line("Generated at: {$generatedAt}");

        if (file_exists($this->filePath)) {
            $mail->attach($this->filePath, [
                'as'   => basename($this->filePath),
                'mime' => $this->guessMime($this->filePath),
            ]);
        }

        return $mail->line('Thank you for using Cyclos.ai.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'               => 'report_ready',
            'report_id'          => $this->report->id,
            'report_schedule_id' => $this->schedule->id,
            'file_path'          => $this->filePath,
            'generated_at'       => now()->toIso8601String(),
        ];
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv'   => 'text/csv',
            'pdf'   => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
