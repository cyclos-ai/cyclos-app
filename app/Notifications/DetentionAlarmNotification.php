<?php

namespace App\Notifications;

use App\Models\Tenant\Container;
use App\Services\Demurrage\DetentionCalculation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DetentionAlarmNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Container $container,
        public readonly DetentionCalculation $calculation,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lfd       = $this->calculation->lastFreeDay?->toDateString() ?? 'N/A';
        $days      = $this->calculation->daysAccrued;
        $cost      = number_format($this->calculation->totalCost, 2);
        $container = $this->container->container_number;

        $subject = $days > 0
            ? "Detention Accruing: Container {$container} — {$days} day(s) / \${$cost}"
            : "Detention Alert: Container {$container} LFD approaching ({$lfd})";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Detention Alert')
            ->line("Container **{$container}** is approaching or has passed its detention free period.")
            ->line("Last Free Day (Detention): **{$lfd}**")
            ->line("Days Accrued: **{$days}**")
            ->line("Estimated Cost: **\${$cost}**")
            ->action('View Container', url("/containers/{$this->container->id}"))
            ->line('Please return the empty container to avoid further detention charges.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'             => 'detention_alarm',
            'container_id'     => $this->container->id,
            'container_number' => $this->container->container_number,
            'last_free_day'    => $this->calculation->lastFreeDay?->toDateString(),
            'days_accrued'     => $this->calculation->daysAccrued,
            'total_cost'       => $this->calculation->totalCost,
            'is_alarm'         => $this->calculation->isAlarm,
        ];
    }
}
