<?php

namespace App\Notifications;

use App\Models\Tenant\Container;
use App\Services\Demurrage\DemurrageCalculation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemurrageAlarmNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Container $container,
        public readonly DemurrageCalculation $calculation,
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
            ? "Demurrage Accruing: Container {$container} — {$days} day(s) / \${$cost}"
            : "Demurrage Alert: Container {$container} LFD approaching ({$lfd})";

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Demurrage Alert')
            ->line("Container **{$container}** requires attention.")
            ->line("Last Free Day (Demurrage): **{$lfd}**")
            ->line("Days Accrued: **{$days}**")
            ->line("Estimated Cost: **\${$cost}**")
            ->action('View Container', url("/containers/{$this->container->id}"))
            ->line('Please take action to avoid further demurrage charges.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'demurrage_alarm',
            'container_id' => $this->container->id,
            'container_number' => $this->container->container_number,
            'last_free_day'=> $this->calculation->lastFreeDay?->toDateString(),
            'days_accrued' => $this->calculation->daysAccrued,
            'total_cost'   => $this->calculation->totalCost,
            'is_alarm'     => $this->calculation->isAlarm,
        ];
    }
}
