<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Webhook;
use App\Models\Tenant\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebhookController extends Controller
{
    /**
     * GET /api/v1/webhooks
     */
    public function index(Request $request): JsonResponse
    {
        $query = Webhook::query();

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/webhooks/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $webhook = Webhook::where('uuid', $uuid)->first();

        if (! $webhook) {
            return $this->notFound('Webhook not found');
        }

        return $this->success($webhook);
    }

    /**
     * POST /api/v1/webhooks
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'url'        => 'required|url|max:500',
            'events'     => 'required|array|min:1',
            'events.*'   => 'string',
            'secret'     => 'nullable|string|max:255',
            'is_active'  => 'nullable|boolean',
            'headers'    => 'nullable|array',
        ]);

        $webhook = Webhook::create([
            'name'      => $request->input('name'),
            'url'       => $request->input('url'),
            'events'    => $request->input('events'),
            'secret'    => $request->input('secret'),
            'is_active' => $request->boolean('is_active', true),
            'headers'   => $request->input('headers', []),
        ]);

        return $this->created($webhook, 'Webhook created');
    }

    /**
     * PUT /api/v1/webhooks/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $webhook = Webhook::where('uuid', $uuid)->first();

        if (! $webhook) {
            return $this->notFound('Webhook not found');
        }

        $request->validate([
            'name'      => 'sometimes|string|max:255',
            'url'       => 'sometimes|url|max:500',
            'events'    => 'sometimes|array|min:1',
            'events.*'  => 'string',
            'secret'    => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'headers'   => 'nullable|array',
        ]);

        $webhook->update($request->only(['name', 'url', 'events', 'secret', 'is_active', 'headers']));

        return $this->success($webhook->fresh(), 'Webhook updated');
    }

    /**
     * DELETE /api/v1/webhooks/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $webhook = Webhook::where('uuid', $uuid)->first();

        if (! $webhook) {
            return $this->notFound('Webhook not found');
        }

        $webhook->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/webhooks/{uuid}/test
     */
    public function test(string $uuid): JsonResponse
    {
        $webhook = Webhook::where('uuid', $uuid)->first();

        if (! $webhook) {
            return $this->notFound('Webhook not found');
        }

        $payload = [
            'event'     => 'webhook.test',
            'timestamp' => now()->toIso8601String(),
            'data'      => ['message' => 'This is a test webhook delivery'],
        ];

        try {
            $headers = array_merge(
                ['Content-Type' => 'application/json'],
                $webhook->headers ?? []
            );

            if ($webhook->secret) {
                $headers['X-Cyclos-Signature'] = 'sha256=' . hash_hmac('sha256', json_encode($payload), $webhook->secret);
            }

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($webhook->url, $payload);

            $log = $webhook->logs()->create([
                'event'       => 'webhook.test',
                'payload'     => $payload,
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'status'      => $response->successful() ? 'delivered' : 'failed',
                'delivered_at'=> now(),
            ]);

            return $this->success([
                'status'        => $response->successful() ? 'delivered' : 'failed',
                'response_code' => $response->status(),
                'log_uuid'      => $log->uuid,
            ]);
        } catch (\Exception $e) {
            return $this->error('Webhook test failed: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/webhooks/{uuid}/logs
     */
    public function logs(string $uuid, Request $request): JsonResponse
    {
        $webhook = Webhook::where('uuid', $uuid)->first();

        if (! $webhook) {
            return $this->notFound('Webhook not found');
        }

        $query = $webhook->logs()->orderBy('created_at', 'desc');

        return $this->paginate($query, $request);
    }
}
