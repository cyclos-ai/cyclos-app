<?php

namespace App\Services\Webhook;

use App\Exceptions\WebhookDeliveryException;
use App\Models\Tenant\Webhook;
use App\Models\Tenant\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    private const TIMEOUT_SECONDS    = 10;
    private const SIGNATURE_HEADER   = 'X-Cyclos-Signature';
    private const TIMESTAMP_HEADER   = 'X-Cyclos-Timestamp';
    private const EVENT_TYPE_HEADER  = 'X-Cyclos-Event';

    public function dispatch(Webhook $webhook, string $eventType, array $payload): void
    {
        if (!$webhook->is_active) {
            return;
        }

        $timestamp = (string) time();
        $signature = $this->verifySignature($webhook, $payload, $timestamp);

        $responseStatus = null;
        $responseBody   = null;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'Content-Type'            => 'application/json',
                    self::SIGNATURE_HEADER    => $signature,
                    self::TIMESTAMP_HEADER    => $timestamp,
                    self::EVENT_TYPE_HEADER   => $eventType,
                    'User-Agent'              => 'Cyclos-Webhook/1.0',
                ])
                ->post($webhook->url, $payload);

            $responseStatus = $response->status();
            $responseBody   = substr($response->body(), 0, 2000);

            if ($response->failed()) {
                $webhook->increment('failure_count');
                throw new WebhookDeliveryException(
                    "Webhook delivery failed with HTTP {$responseStatus} for webhook {$webhook->id}"
                );
            }

            $webhook->update([
                'last_triggered_at' => now(),
                'failure_count'     => 0,
            ]);

        } catch (WebhookDeliveryException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $webhook->increment('failure_count');
            Log::error('WebhookDispatcher: delivery exception', [
                'webhook_id' => $webhook->id,
                'url'        => $webhook->url,
                'error'      => $e->getMessage(),
            ]);
            throw new WebhookDeliveryException($e->getMessage(), 0, $e);
        } finally {
            $this->logAttempt($webhook, $eventType, $payload, $responseStatus, $responseBody);
        }
    }

    public function dispatchForEvent(string $eventType, array $payload): void
    {
        $webhooks = Webhook::where('is_active', true)
            ->whereJsonContains('events', $eventType)
            ->get();

        foreach ($webhooks as $webhook) {
            try {
                $this->dispatch($webhook, $eventType, $payload);
            } catch (\Throwable $e) {
                Log::error('WebhookDispatcher: dispatchForEvent failed for one webhook', [
                    'webhook_id'  => $webhook->id,
                    'event_type'  => $eventType,
                    'error'       => $e->getMessage(),
                ]);
                // Continue dispatching to remaining webhooks
            }
        }
    }

    public function verifySignature(Webhook $webhook, array $payload, ?string $timestamp = null): string
    {
        $timestamp ??= (string) time();
        $body       = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sigPayload = $timestamp . '.' . $body;

        return hash_hmac('sha256', $sigPayload, $webhook->secret);
    }

    public function logAttempt(
        Webhook $webhook,
        string $eventType,
        array $payload,
        ?int $responseStatus,
        ?string $responseBody
    ): void {
        WebhookLog::create([
            'webhook_id'      => $webhook->id,
            'event_type'      => $eventType,
            'payload'         => $payload,
            'response_status' => $responseStatus,
            'response_body'   => $responseBody,
            'success'         => $responseStatus !== null && $responseStatus >= 200 && $responseStatus < 300,
            'triggered_at'    => now(),
        ]);
    }
}
