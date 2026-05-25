<?php

namespace App\Jobs\Webhook;

use App\Models\Tenant\Webhook;
use App\Services\Webhook\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $eventType,
        public readonly array $payload,
        public readonly ?string $webhookId = null,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(WebhookDispatcher $dispatcher): void
    {
        if ($this->webhookId !== null) {
            $webhook = Webhook::find($this->webhookId);

            if ($webhook === null) {
                Log::warning('DispatchWebhookJob: webhook not found', ['webhook_id' => $this->webhookId]);
                return;
            }

            $dispatcher->dispatch($webhook, $this->eventType, $this->payload);
        } else {
            $dispatcher->dispatchForEvent($this->eventType, $this->payload);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DispatchWebhookJob: all retries exhausted', [
            'event_type' => $this->eventType,
            'webhook_id' => $this->webhookId,
            'error'      => $exception->getMessage(),
        ]);
    }

    public function backoff(): array
    {
        return [60, 300, 900]; // 1min, 5min, 15min
    }
}
