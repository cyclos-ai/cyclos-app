<?php

namespace App\Notifications;

use App\Domain\Container\Enums\ContainerStatus;
use App\Models\Tenant\Container;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContainerStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Container $container,
        public readonly ContainerStatus $previousStatus,
        public readonly ContainerStatus $newStatus,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $containerNumber = $this->container->container_number;
        $newLabel        = $this->newStatus->label();
        $prevLabel       = $this->previousStatus->label();

        return (new MailMessage)
            ->subject("Container Status Update: {$containerNumber} is now {$newLabel}")
            ->greeting('Container Status Update')
            ->line("Container **{$containerNumber}** status has changed.")
            ->line("Previous Status: **{$prevLabel}**")
            ->line("New Status: **{$newLabel}**")
            ->when($this->container->eta, fn($mail) => $mail->line(
                "Estimated Arrival: **{$this->container->eta->toDateString()}**"
            ))
            ->action('Track Container', url("/containers/{$this->container->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'container_status_change',
            'container_id'     => $this->container->id,
            'container_number' => $this->container->container_number,
            'previous_status'  => $this->previousStatus->value,
            'new_status'       => $this->newStatus->value,
            'eta'              => $this->container->eta?->toDateString(),
        ];
    }
}
