<?php

namespace App\Services\N8n;

use App\Models\Tenant\N8nIntegration;
use App\Models\Tenant\N8nWorkflowMapping;
use Illuminate\Support\Facades\Log;

class N8nWorkflowManager
{
    /**
     * Get or create the tenant's n8n integration record.
     */
    public function getIntegration(): ?N8nIntegration
    {
        return N8nIntegration::first();
    }

    /**
     * Connect to n8n — save config and verify connectivity.
     */
    public function connect(string $hostUrl, string $apiKey, ?string $webhookBaseUrl = null): array
    {
        $client = new N8nApiClient($hostUrl, $apiKey);
        $health = $client->healthCheck();

        $integration = N8nIntegration::updateOrCreate(
            [], // Single record per tenant
            [
                'host_url'             => $hostUrl,
                'api_key'              => $apiKey,
                'webhook_base_url'     => $webhookBaseUrl ?? $hostUrl,
                'is_active'            => $health['status'] === 'healthy',
                'is_connected'         => $health['status'] === 'healthy',
                'last_health_check_at' => now(),
                'last_health_status'   => $health['status'],
            ]
        );

        return [
            'integration' => $integration,
            'health'      => $health,
        ];
    }

    /**
     * Disconnect n8n.
     */
    public function disconnect(): void
    {
        N8nIntegration::query()->update([
            'is_active'          => false,
            'is_connected'       => false,
            'last_health_status' => 'disconnected',
        ]);
    }

    /**
     * Sync workflows from n8n into local mapping table.
     */
    public function syncWorkflows(N8nIntegration $integration): array
    {
        $client    = N8nApiClient::fromIntegration($integration);
        $response  = $client->listWorkflows(100);
        $workflows = $response['data'] ?? [];

        $synced = [];
        foreach ($workflows as $wf) {
            $mapping = N8nWorkflowMapping::updateOrCreate(
                [
                    'n8n_integration_id' => $integration->id,
                    'n8n_workflow_id'    => (string) $wf['id'],
                ],
                [
                    'name'      => $wf['name'],
                    'is_active' => $wf['active'] ?? false,
                    'metadata'  => [
                        'tags'       => $wf['tags'] ?? [],
                        'created_at' => $wf['createdAt'] ?? null,
                        'updated_at' => $wf['updatedAt'] ?? null,
                    ],
                ]
            );
            $synced[] = $mapping;
        }

        return $synced;
    }

    /**
     * Deploy a template workflow to n8n.
     */
    public function deployTemplate(N8nIntegration $integration, string $templateKey): ?N8nWorkflowMapping
    {
        $template = config("n8n.templates.{$templateKey}");
        if (!$template) {
            return null;
        }

        $client = N8nApiClient::fromIntegration($integration);

        // Build a simple webhook-triggered workflow
        $webhookPath = "cyclos-{$templateKey}-" . substr(md5($integration->id), 0, 8);
        $webhookUrl  = rtrim($integration->webhook_base_url ?? $integration->host_url, '/') . "/webhook/{$webhookPath}";

        $workflowData = [
            'name'   => "[Cyclos] {$template['name']}",
            'active' => false,
            'nodes'  => [
                [
                    'parameters' => [
                        'httpMethod' => 'POST',
                        'path'       => $webhookPath,
                        'options'    => (object) [],
                    ],
                    'name'        => 'Webhook',
                    'type'        => 'n8n-nodes-base.webhook',
                    'typeVersion' => 1.1,
                    'position'    => [250, 300],
                ],
                [
                    'parameters' => [
                        'conditions' => [
                            'options' => [
                                'caseSensitive'  => true,
                                'leftValue'      => '',
                                'typeValidation' => 'strict',
                            ],
                            'conditions' => [],
                            'combinator' => 'and',
                        ],
                    ],
                    'name'        => 'Filter',
                    'type'        => 'n8n-nodes-base.filter',
                    'typeVersion' => 2,
                    'position'    => [470, 300],
                ],
            ],
            'connections' => [
                'Webhook' => [
                    'main' => [[['node' => 'Filter', 'type' => 'main', 'index' => 0]]],
                ],
            ],
            'settings' => [
                'executionOrder' => 'v1',
            ],
        ];

        try {
            $result = $client->createWorkflow($workflowData);

            $mapping = N8nWorkflowMapping::create([
                'n8n_integration_id' => $integration->id,
                'n8n_workflow_id'    => (string) $result['id'],
                'name'               => $template['name'],
                'template_key'       => $templateKey,
                'trigger_event'      => $template['trigger_event'],
                'webhook_url'        => $webhookUrl,
                'is_active'          => false,
                'metadata'           => ['category' => $template['category'] ?? null],
            ]);

            return $mapping;
        } catch (\Exception $e) {
            Log::error('N8nWorkflowManager: deployTemplate failed', [
                'template' => $templateKey,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Trigger all n8n workflows mapped to a specific event.
     */
    public function triggerEvent(string $eventType, array $payload): void
    {
        $integration = $this->getIntegration();
        if (!$integration || !$integration->is_active) {
            return;
        }

        $mappings = N8nWorkflowMapping::where('n8n_integration_id', $integration->id)
            ->where('trigger_event', $eventType)
            ->where('is_active', true)
            ->whereNotNull('webhook_url')
            ->get();

        if ($mappings->isEmpty()) {
            return;
        }

        $client = N8nApiClient::fromIntegration($integration);

        foreach ($mappings as $mapping) {
            try {
                $client->triggerWebhook($mapping->webhook_url, $payload);
                $mapping->incrementExecution();
            } catch (\Exception $e) {
                Log::warning('N8nWorkflowManager: triggerEvent failed', [
                    'mapping_id' => $mapping->id,
                    'event'      => $eventType,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Run a health check and update the integration record.
     */
    public function healthCheck(N8nIntegration $integration): array
    {
        $client = N8nApiClient::fromIntegration($integration);
        $health = $client->healthCheck();

        $integration->update([
            'is_connected'         => $health['status'] === 'healthy',
            'last_health_check_at' => now(),
            'last_health_status'   => $health['status'],
        ]);

        return $health;
    }

    /**
     * Get available templates with deployment status.
     */
    public function getTemplates(N8nIntegration $integration): array
    {
        $templates = config('n8n.templates', []);
        $deployed  = N8nWorkflowMapping::where('n8n_integration_id', $integration->id)
            ->whereNotNull('template_key')
            ->pluck('template_key')
            ->toArray();

        return collect($templates)->map(function ($tpl, $key) use ($deployed) {
            return array_merge($tpl, [
                'key'      => $key,
                'deployed' => in_array($key, $deployed),
            ]);
        })->values()->toArray();
    }
}
