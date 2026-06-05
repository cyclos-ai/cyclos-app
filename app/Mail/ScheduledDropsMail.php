<?php

namespace App\Mail;

use App\Exports\ScheduledDropsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ScheduledDropsMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $tenantName;

    public function __construct(
        private readonly Collection $drops,
        private readonly string $exportDate,
    ) {
        $this->tenantName = tenancy()->tenant?->name ?? 'Cyclos.ai';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->tenantName} Scheduled Drops",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-drops',
            with: [
                'tenantName' => $this->tenantName,
            ],
        );
    }

    public function attachments(): array
    {
        $xlsx = Excel::raw(new ScheduledDropsExport($this->drops), \Maatwebsite\Excel\Excel::XLSX);

        return [
            Attachment::fromData(fn () => $xlsx, "Scheduled_Drops_{$this->exportDate}.xlsx")
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
