<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\Tenant\N8nWorkflowMapping;
use App\Services\N8n\N8nApiClient;
use App\Services\N8n\N8nWorkflowManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class N8nIntegrationController extends Controller
{
    public function __construct(
        private readonly N8nWorkflowManager $manager,
    ) {}

    /**
     * GET /api/v1/n8n
     * Get current n8n integration status.
     */
    public function status(): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration) {
            return response()->json([
                'data' => [
                    'connected'  => false,
                    'configured' => false,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'connected'          => $integration->is_connected,
                'configured'         => true,
                'is_active'          => $integration->is_active,
                'host_url'           => $integration->host_url,
                'webhook_base_url'   => $integration->webhook_base_url,
                'last_health_check'  => $integration->last_health_check_at?->toIso8601String(),
                'last_health_status' => $integration->last_health_status,
                'workflow_count'     => $integration->workflowMappings()->count(),
                'active_workflows'   => $integration->activeWorkflows()->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/n8n/connect
     * Connect to an n8n instance.
     */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'host_url'         => 'required|url|max:500',
            'api_key'          => 'required|string|max:500',
            'webhook_base_url' => 'nullable|url|max:500',
        ]);

        $result = $this->manager->connect(
            $request->input('host_url'),
            $request->input('api_key'),
            $request->input('webhook_base_url'),
        );

        $health = $result['health'];

        if ($health['status'] !== 'healthy') {
            return response()->json([
                'message' => 'Failed to connect to n8n',
                'health'  => $health,
            ], 422);
        }

        return response()->json([
            'message' => 'Connected to n8n successfully',
            'data'    => [
                'connected' => true,
                'host_url'  => $result['integration']->host_url,
                'health'    => $health,
            ],
        ]);
    }

    /**
     * POST /api/v1/n8n/disconnect
     * Disconnect from n8n.
     */
    public function disconnect(): JsonResponse
    {
        $this->manager->disconnect();

        return response()->json(['message' => 'Disconnected from n8n']);
    }

    /**
     * POST /api/v1/n8n/health
     * Run a health check against the connected n8n instance.
     */
    public function health(): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration) {
            return response()->json(['message' => 'n8n not configured'], 404);
        }

        $health = $this->manager->healthCheck($integration);

        return response()->json(['data' => $health]);
    }

    /**
     * GET /api/v1/n8n/workflows
     * List workflows from n8n (live from n8n API).
     */
    public function workflows(): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration || !$integration->is_connected) {
            return response()->json(['message' => 'n8n not connected'], 404);
        }

        try {
            $client    = N8nApiClient::fromIntegration($integration);
            $workflows = $client->listWorkflows();

            // Enrich with local mapping data
            $mappings = N8nWorkflowMapping::where('n8n_integration_id', $integration->id)
                ->pluck('trigger_event', 'n8n_workflow_id')
                ->toArray();

            $data = collect($workflows['data'] ?? [])->map(function ($wf) use ($mappings) {
                $wf['cyclos_event'] = $mappings[(string) $wf['id']] ?? null;
                return $wf;
            });

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch workflows: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/n8n/workflows/sync
     * Sync workflows from n8n into local mappings.
     */
    public function syncWorkflows(): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration || !$integration->is_connected) {
            return response()->json(['message' => 'n8n not connected'], 404);
        }

        $synced = $this->manager->syncWorkflows($integration);

        return response()->json([
            'message' => count($synced) . ' workflows synced',
            'data'    => $synced,
        ]);
    }

    /**
     * GET /api/v1/n8n/templates
     * List available workflow templates.
     */
    public function templates(): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration) {
            $templates = collect(config('n8n.templates', []))->map(function ($tpl, $key) {
                return array_merge($tpl, ['key' => $key, 'deployed' => false]);
            })->values();

            return response()->json(['data' => $templates]);
        }

        return response()->json([
            'data' => $this->manager->getTemplates($integration),
        ]);
    }

    /**
     * POST /api/v1/n8n/templates/{key}/deploy
     * Deploy a workflow template to n8n.
     */
    public function deployTemplate(string $key): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration || !$integration->is_connected) {
            return response()->json(['message' => 'n8n not connected'], 404);
        }

        $mapping = $this->manager->deployTemplate($integration, $key);

        if (!$mapping) {
            return response()->json(['message' => 'Failed to deploy template'], 500);
        }

        return response()->json([
            'message' => "Template '{$mapping->name}' deployed to n8n",
            'data'    => $mapping,
        ], 201);
    }

    /**
     * PUT /api/v1/n8n/workflow-mappings/{id}
     * Update a local workflow mapping (event binding, active state).
     */
    public function updateMapping(Request $request, string $id): JsonResponse
    {
        $mapping = N8nWorkflowMapping::find($id);

        if (!$mapping) {
            return response()->json(['message' => 'Mapping not found'], 404);
        }

        $request->validate([
            'trigger_event' => 'sometimes|string|max:100',
            'webhook_url'   => 'sometimes|nullable|url|max:500',
            'is_active'     => 'sometimes|boolean',
        ]);

        $mapping->update($request->only(['trigger_event', 'webhook_url', 'is_active']));

        // If activating/deactivating, sync to n8n
        if ($request->has('is_active')) {
            try {
                $integration = $mapping->integration;
                $client      = N8nApiClient::fromIntegration($integration);

                if ($request->boolean('is_active')) {
                    $client->activateWorkflow($mapping->n8n_workflow_id);
                } else {
                    $client->deactivateWorkflow($mapping->n8n_workflow_id);
                }
            } catch (\Exception $e) {
                // Log but don't fail — local state is updated
                \Log::warning('N8n: failed to sync workflow active state', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'message' => 'Mapping updated',
            'data'    => $mapping->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/n8n/workflow-mappings/{id}
     * Remove a workflow mapping (optionally delete from n8n too).
     */
    public function deleteMapping(Request $request, string $id): JsonResponse
    {
        $mapping = N8nWorkflowMapping::find($id);

        if (!$mapping) {
            return response()->json(['message' => 'Mapping not found'], 404);
        }

        // Optionally delete the workflow from n8n
        if ($request->boolean('delete_from_n8n')) {
            try {
                $client = N8nApiClient::fromIntegration($mapping->integration);
                $client->deleteWorkflow($mapping->n8n_workflow_id);
            } catch (\Exception $e) {
                // Log but continue with local deletion
                \Log::warning('N8n: failed to delete workflow from n8n', ['error' => $e->getMessage()]);
            }
        }

        $mapping->delete();

        return response()->json(['message' => 'Mapping deleted']);
    }

    /**
     * GET /api/v1/n8n/executions
     * List recent executions from n8n.
     */
    public function executions(Request $request): JsonResponse
    {
        $integration = $this->manager->getIntegration();

        if (!$integration || !$integration->is_connected) {
            return response()->json(['message' => 'n8n not connected'], 404);
        }

        try {
            $client     = N8nApiClient::fromIntegration($integration);
            $executions = $client->listExecutions(
                limit: (int) $request->input('limit', 20),
                workflowId: $request->input('workflow_id'),
            );

            return response()->json(['data' => $executions['data'] ?? []]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch executions'], 500);
        }
    }
}
