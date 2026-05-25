<?php

namespace App\Services\N8n;

use App\Models\Tenant\N8nIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class N8nApiClient
{
    private PendingRequest $http;

    public function __construct(
        private readonly string $hostUrl,
        private readonly string $apiKey,
    ) {
        $this->http = Http::baseUrl(rtrim($this->hostUrl, '/') . '/api/v1')
            ->withHeaders(['X-N8N-API-KEY' => $this->apiKey])
            ->timeout(15)
            ->acceptJson();
    }

    /**
     * Create from a tenant's N8nIntegration model.
     */
    public static function fromIntegration(N8nIntegration $integration): self
    {
        return new self($integration->host_url, $integration->api_key);
    }

    /**
     * Create from global config (non-tenant context).
     */
    public static function fromConfig(): self
    {
        return new self(
            config('n8n.host'),
            config('n8n.api_key'),
        );
    }

    // ─── Health ──────────────────────────────────────────

    public function healthCheck(): array
    {
        try {
            $response = $this->http->get('/workflows', ['limit' => 1]);

            if ($response->successful()) {
                return ['status' => 'healthy', 'version' => $response->header('x-n8n-version')];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return ['status' => 'auth_failed', 'message' => 'Invalid API key'];
            }

            return ['status' => 'error', 'message' => "HTTP {$response->status()}"];
        } catch (\Exception $e) {
            return ['status' => 'unreachable', 'message' => $e->getMessage()];
        }
    }

    // ─── Workflows ───────────────────────────────────────

    public function listWorkflows(int $limit = 50, ?string $cursor = null): array
    {
        $params = ['limit' => $limit];
        if ($cursor) {
            $params['cursor'] = $cursor;
        }

        return $this->http->get('/workflows', $params)->json();
    }

    public function getWorkflow(string $id): array
    {
        return $this->http->get("/workflows/{$id}")->json();
    }

    public function createWorkflow(array $data): array
    {
        return $this->http->post('/workflows', $data)->json();
    }

    public function updateWorkflow(string $id, array $data): array
    {
        return $this->http->put("/workflows/{$id}", $data)->json();
    }

    public function deleteWorkflow(string $id): bool
    {
        return $this->http->delete("/workflows/{$id}")->successful();
    }

    public function activateWorkflow(string $id): array
    {
        return $this->http->patch("/workflows/{$id}", ['active' => true])->json();
    }

    public function deactivateWorkflow(string $id): array
    {
        return $this->http->patch("/workflows/{$id}", ['active' => false])->json();
    }

    // ─── Executions ──────────────────────────────────────

    public function listExecutions(int $limit = 20, ?string $workflowId = null): array
    {
        $params = ['limit' => $limit];
        if ($workflowId) {
            $params['workflowId'] = $workflowId;
        }

        return $this->http->get('/executions', $params)->json();
    }

    public function getExecution(string $id): array
    {
        return $this->http->get("/executions/{$id}")->json();
    }

    // ─── Credentials ─────────────────────────────────────

    public function listCredentials(): array
    {
        return $this->http->get('/credentials')->json();
    }

    // ─── Webhook Trigger ─────────────────────────────────

    /**
     * Fire a payload to an n8n webhook trigger URL.
     * This bypasses the n8n API and hits the webhook endpoint directly.
     */
    public function triggerWebhook(string $webhookUrl, array $payload): Response
    {
        return Http::timeout(10)
            ->withHeaders([
                'Content-Type'       => 'application/json',
                'X-Cyclos-Event'     => $payload['event'] ?? 'unknown',
                'X-Cyclos-Timestamp' => (string) time(),
            ])
            ->post($webhookUrl, $payload);
    }
}
