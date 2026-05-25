<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Domain\Drayage\Enums\DrayageStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Container;
use App\Models\Tenant\DrayageEvent;
use App\Models\Tenant\ImportDrayage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckHubIntegrationController extends Controller
{
    // GET /api/v1/integrations/truckhub/containers
    // List containers available for TruckHub drayage dispatch
    public function listContainers(Request $request): JsonResponse
    {
        $query = Container::query()
            ->where('organization_id', tenancy()->tenant?->id)
            ->with(['mbl', 'importDrayage'])
            ->whereHas('importDrayage', function ($q) {
                $q->whereIn('drayage_status', [
                    DrayageStatus::PENDING->value,
                    DrayageStatus::TENDERED->value,
                    DrayageStatus::CONFIRMED->value,
                    DrayageStatus::DISPATCHED->value,
                ]);
            });

        $pageNum = (int) $request->input('page_num', 0);
        $pageSize = min(50, max(1, (int) $request->input('page_size', 20)));

        $total = $query->count();
        $items = $query->skip($pageNum * $pageSize)->take($pageSize)->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page_num' => $pageNum,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ]);
    }

    // POST /api/v1/integrations/truckhub/containers/{uuid}/status
    // Update container status pushed from TruckHub
    public function updateContainerStatus(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
            'truckhub_job_id' => 'nullable|string',
        ]);

        $container = Container::where('organization_id', tenancy()->tenant?->id)
            ->findOrFail($uuid);

        $container->update([
            'status' => $request->input('status'),
        ]);

        return $this->success([
            'container_id' => $container->id,
            'container_number' => $container->container_number,
            'status' => $container->status,
            'updated_at' => now(),
        ], 'Container status updated');
    }

    // GET /api/v1/integrations/truckhub/drayage-orders
    // List drayage orders available for TruckHub to accept
    public function listDrayageOrders(Request $request): JsonResponse
    {
        $query = ImportDrayage::query()
            ->where('organization_id', tenancy()->tenant?->id)
            ->with(['container', 'container.mbl'])
            ->whereIn('drayage_status', [
                DrayageStatus::PENDING->value,
                DrayageStatus::TENDERED->value,
            ]);

        if ($request->has('status')) {
            $query->where('drayage_status', $request->input('status'));
        }

        $pageNum = (int) $request->input('page_num', 0);
        $pageSize = min(50, max(1, (int) $request->input('page_size', 20)));

        $total = $query->count();
        $items = $query->skip($pageNum * $pageSize)->take($pageSize)->get();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page_num' => $pageNum,
                'page_size' => $pageSize,
                'pages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ]);
    }

    // POST /api/v1/integrations/truckhub/drayage-orders/{uuid}/accept
    // TruckHub motor carrier accepts a drayage order
    public function acceptDrayageOrder(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'carrier_scac' => 'nullable|string|max:10',
            'carrier_name' => 'nullable|string|max:255',
            'driver_name' => 'nullable|string|max:255',
            'truck_number' => 'nullable|string|max:50',
            'truckhub_job_id' => 'nullable|string',
        ]);

        $drayage = ImportDrayage::where('organization_id', tenancy()->tenant?->id)
            ->findOrFail($uuid);

        $drayage->update([
            'drayage_status' => DrayageStatus::CONFIRMED->value,
            'status' => 'confirmed',
            'drayage_provider_scac' => $request->input('carrier_scac', $drayage->drayage_provider_scac),
            'drayage_provider_name' => $request->input('carrier_name', $drayage->drayage_provider_name),
        ]);

        DrayageEvent::create([
            'import_drayage_id' => $drayage->id,
            'event_type' => DrayageStatus::CONFIRMED->value,
            'event_date' => now(),
            'notes' => 'Accepted via TruckHub integration' . ($request->input('truckhub_job_id') ? ' (Job: ' . $request->input('truckhub_job_id') . ')' : ''),
            'recorded_by' => $request->user()?->id,
            'source' => 'api',
            'metadata' => array_filter([
                'truckhub_job_id' => $request->input('truckhub_job_id'),
                'driver_name' => $request->input('driver_name'),
                'truck_number' => $request->input('truck_number'),
            ]),
            'created_at' => now(),
        ]);

        return $this->success([
            'drayage_id' => $drayage->id,
            'status' => DrayageStatus::CONFIRMED->value,
            'label' => DrayageStatus::CONFIRMED->label(),
        ], 'Drayage order accepted');
    }

    // POST /api/v1/integrations/truckhub/drayage-orders/{uuid}/update
    // TruckHub pushes a drayage step update
    public function updateDrayageStep(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
            'event_date' => 'nullable|date',
            'location' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'truckhub_job_id' => 'nullable|string',
        ]);

        $drayage = ImportDrayage::where('organization_id', tenancy()->tenant?->id)
            ->findOrFail($uuid);

        $newStatus = DrayageStatus::from($request->input('status'));

        $drayage->update([
            'drayage_status' => $newStatus->value,
        ]);

        DrayageEvent::create([
            'import_drayage_id' => $drayage->id,
            'event_type' => $newStatus->value,
            'event_date' => $request->input('event_date', now()),
            'location' => $request->input('location'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'notes' => $request->input('notes'),
            'recorded_by' => $request->user()?->id,
            'source' => 'api',
            'metadata' => array_filter([
                'truckhub_job_id' => $request->input('truckhub_job_id'),
            ]),
            'created_at' => now(),
        ]);

        // Sync container status for key transitions
        if ($drayage->container_id) {
            $containerStatus = match($newStatus) {
                DrayageStatus::PICKED_UP, DrayageStatus::IN_TRANSIT_DELIVERY => 'OUT_FOR_DELIVERY',
                DrayageStatus::EMPTY_RETURNED => 'EMPTY_RETURNED',
                default => null,
            };

            if ($containerStatus) {
                $drayage->container()->update(['status' => $containerStatus]);
            }
        }

        return $this->success([
            'drayage_id' => $drayage->id,
            'current_status' => $newStatus->value,
            'step' => $newStatus->step(),
            'label' => $newStatus->label(),
        ], 'Drayage step updated');
    }

    // GET /api/v1/integrations/truckhub/webhook-config
    // Return webhook configuration for TruckHub push updates
    public function webhookConfig(Request $request): JsonResponse
    {
        $organizationId = tenancy()->tenant?->id;

        return $this->success([
            'organization_id' => $organizationId,
            'supported_events' => [
                'drayage.status_updated',
                'drayage.pickup_confirmed',
                'drayage.delivered',
                'drayage.empty_returned',
                'container.status_updated',
            ],
            'push_endpoint' => url('/api/v1/integrations/truckhub/drayage-orders/{uuid}/update'),
            'authentication' => 'Bearer token via Authorization header',
            'api_version' => 'v1',
        ]);
    }
}
