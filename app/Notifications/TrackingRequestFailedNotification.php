<?php

namespace App\Notifications;

use App\Models\Tenant\TrackingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrackingRequestFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TrackingRequest $trackingRequest,
        public readonly string $reason,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reference = $this->trackingRequest->reference_value;
        $carrier   = $this->trackingRequest->carrier_scac ?? 'Unknown';

        return (new MailMessage)
            ->subject("Tracking Failed: {$reference}")
            ->greeting('Tracking Request Failed')
            ->line("A tracking request for **{$reference}** (Carrier: {$carrier}) has failed after all retry attempts.")
            ->line("Reason: {$this->reason}")
            ->action('View Tracking Request', url("/tracking/{$this->trackingRequest->id}"))
            ->line('Please check the reference number and carrier, then retry tracking manually.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                => 'tracking_request_failed',
            'tracking_request_id' => $this->trackingRequest->id,
            'reference_type'      => $this->trackingRequest->reference_type,
            'reference_value'     => $this->trackingRequest->reference_value,
            'carrier_scac'        => $this->trackingRequest->carrier_scac,
            'reason'              => $this->reason,
        ];
    }
}
