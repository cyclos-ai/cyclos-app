<?php

namespace App\Http\Controllers\Api\V1\Container;

use App\Domain\Container\Enums\ContainerEventType;
use App\Domain\Container\Enums\ContainerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Container\FilterContainerRequest;
use App\Http\Requests\Container\StoreContainerRequest;
use App\Http\Requests\Container\UpdateContainerRequest;
use App\Http\Resources\Container\ContainerResource;
use App\Models\Tenant\Container;
use App\Models\Tenant\ContainerEvent;
use App\Services\Vessel\VesselLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContainerController extends Controller
{
    public function __construct(
        private readonly VesselLinkingService $vesselLinkingService,
    ) {}

    /**
     * GET /api/v1/containers
     */
    public function index(Request $request): JsonResponse
    {
        $query = Container::with('mbl');

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $container = Container::with(['mbl', 'vessel'])->find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        return $this->success(new ContainerResource($container));
    }

    /**
     * POST /api/v1/containers
     */
    public function store(StoreContainerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attributes = $this->containerAttributes($data);
        $attributes['organization_id'] = tenancy()->tenant?->id;

        $container = Container::create($attributes);

        $this->maybeLink($container, $data);

        return $this->created(new ContainerResource($container->fresh()->load(['mbl', 'vessel'])), 'Container created successfully');
    }

    /**
     * PUT /api/v1/containers/{uuid}
     */
    public function update(UpdateContainerRequest $request, string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $container->update($this->containerAttributes($request->validated()));

        $this->maybeLink($container, $request->validated());

        return $this->success(new ContainerResource($container->fresh()->load(['mbl', 'vessel'])), 'Container updated successfully');
    }

    /**
     * POST /api/v1/containers/{uuid}/link-vessel
     */
    public function linkVessel(Request $request, string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate([
            'vessel_name'   => ['nullable', 'string', 'max:255'],
            'imo'           => ['nullable', 'string', 'max:20'],
            'mmsi'          => ['nullable', 'string', 'max:20'],
            'voyage_number' => ['nullable', 'string', 'max:50'],
            'carrier_scac'  => ['nullable', 'string', 'max:4'],
        ]);

        $vessel = $this->vesselLinkingService->linkContainerToVessel($container, $request->only([
            'vessel_name',
            'imo',
            'mmsi',
            'voyage_number',
            'carrier_scac',
        ]));

        if (! $vessel) {
            return $this->error('No vessel identifying information provided (vessel_name or imo required)', 422);
        }

        return $this->success($vessel, 'Container linked to vessel successfully');
    }

    /**
     * Link a vessel when vessel-identifying fields are present in the payload.
     */
    private function maybeLink(Container $container, array $data): void
    {
        $hasVesselInfo = ! empty($data['vessel_name'])
            || ! empty($data['imo'])
            || ! empty($data['mmsi']);

        if (! $hasVesselInfo) {
            return;
        }

        $this->vesselLinkingService->linkContainerToVessel($container, [
            'vessel_name'   => $data['vessel_name']   ?? null,
            'imo'           => $data['imo']            ?? null,
            'mmsi'          => $data['mmsi']           ?? null,
            'voyage_number' => $data['voyage_number']  ?? null,
            'carrier_scac'  => $data['carrier_scac']   ?? null,
        ]);
    }

    /**
     * Map validated request fields to actual `containers` columns. The request
     * accepts API aliases (mbl_uuid, weight_kg, last_free_day) and vessel-linking
     * inputs (vessel_name/imo/mmsi/voyage_number) that are NOT columns — the
     * latter are resolved to vessel_id by maybeLink()/VesselLinkingService.
     */
    private function containerAttributes(array $data): array
    {
        $attrs = [];

        foreach (['container_number', 'carrier_scac', 'size', 'type', 'pol', 'pod',
                  'eta', 'etd', 'ata', 'atd', 'status', 'notes'] as $key) {
            if (array_key_exists($key, $data)) {
                $attrs[$key] = $data[$key];
            }
        }

        $aliases = [
            'mbl_uuid'      => 'mbl_id',
            'vessel_uuid'   => 'vessel_id',
            'booking_uuid'  => 'booking_id',
            'weight_kg'     => 'weight',
            'last_free_day' => 'last_free_day_demurrage',
        ];
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $data) && $data[$from] !== null) {
                $attrs[$to] = $data[$from];
            }
        }

        if (! empty($data['priority'])) {
            $attrs['is_priority'] = in_array($data['priority'], ['high', 'critical'], true);
        }

        return $attrs;
    }

    /**
     * DELETE /api/v1/containers/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $container->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/containers/filter
     */
    public function filter(FilterContainerRequest $request): JsonResponse
    {
        $query = Container::with('mbl');

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by'),
            (int) $request->input('direction', 1)
        );

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/active
     */
    public function active(Request $request): JsonResponse
    {
        $query = Container::with('mbl')
            ->whereNotNull('vessel_id')
            ->where('status', '!=', 'EMPTY_RETURNED');

        $this->applySorting($query, $request->input('order_by', 'eta'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/not-tracking
     */
    public function notTracking(Request $request): JsonResponse
    {
        $query = Container::with('mbl')
            ->where('status', 'NOT_TRACKING');

        $this->applySorting($query, $request->input('order_by', 'created_at'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/dropped-mbl
     */
    public function droppedMbl(Request $request): JsonResponse
    {
        $query = Container::with('mbl')->whereNull('mbl_id');

        $this->applySorting($query, $request->input('order_by', 'created_at'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/mbl/{mbl_number}
     */
    public function byMbl(string $mblNumber, Request $request): JsonResponse
    {
        $query = Container::query()->whereHas('mbl', function ($q) use ($mblNumber) {
            $q->where('mbl_number', $mblNumber);
        });

        $this->applySorting($query, $request->input('order_by', 'container_number'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * GET /api/v1/containers/number/{container_number}
     */
    public function byContainerNumber(string $containerNumber): JsonResponse
    {
        $container = Container::where('container_number', strtoupper($containerNumber))->first();

        if (! $container) {
            return $this->notFound('Container not found');
        }

        return $this->success(new ContainerResource($container));
    }

    /**
     * PATCH /api/v1/containers/{uuid}/priority
     */
    public function updatePriority(Request $request, string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate(['priority' => 'required|boolean']);

        $container->update(['is_priority' => $request->input('priority')]);

        return $this->success(new ContainerResource($container->fresh()->load('mbl')), 'Priority updated');
    }

    /**
     * PATCH /api/v1/containers/{uuid}/status
     * Manually set a container's status (admin override). Logs a manual event so
     * the change is auditable in the container timeline.
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ContainerStatus::class)],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $previous = $container->status;
        $new      = ContainerStatus::from($validated['status']);

        $container->update(['status' => $new]);

        ContainerEvent::create([
            'container_id' => $container->id,
            'event_type'   => ContainerEventType::MANUAL_UPDATE->value,
            'description'  => $validated['note']
                ?? ('Status manually changed from ' . ($previous?->label() ?? 'n/a') . ' to ' . $new->label()),
            'event_date'   => now(),
            'raw_data'     => [
                'manual'  => true,
                'from'    => $previous?->value,
                'to'      => $new->value,
                'user_id' => $request->user()?->id,
            ],
        ]);

        return $this->success(
            new ContainerResource($container->fresh()->load(['mbl', 'vessel'])),
            'Status updated'
        );
    }

    /**
     * PATCH /api/v1/containers/{uuid}/outgate
     */
    public function outgate(Request $request, string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate([
            'outgate_date'     => 'required|date',
            'trucker_name'     => 'nullable|string|max:255',
            'license_plate'    => 'nullable|string|max:50',
        ]);

        $container->update([
            'outgate_date'  => $request->input('outgate_date'),
            'trucker_name'  => $request->input('trucker_name'),
            'license_plate' => $request->input('license_plate'),
            'status'        => ContainerStatus::OUT_FOR_DELIVERY,
        ]);

        return $this->success(new ContainerResource($container->fresh()->load('mbl')), 'Container outgated');
    }

    /**
     * PATCH /api/v1/containers/{uuid}/empty-return
     */
    public function emptyReturn(Request $request, string $uuid): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $request->validate([
            'empty_return_date'     => 'required|date',
            'empty_return_location' => 'nullable|string|max:255',
        ]);

        $container->update([
            'empty_return_date'     => $request->input('empty_return_date'),
            'status'                => ContainerStatus::EMPTY_RETURNED,
        ]);

        return $this->success(new ContainerResource($container->fresh()->load('mbl')), 'Empty return recorded');
    }

    /**
     * GET /api/v1/containers/{uuid}/location-history
     */
    public function locationHistory(string $uuid, Request $request): JsonResponse
    {
        $container = Container::find($uuid);

        if (! $container) {
            return $this->notFound('Container not found');
        }

        $history = $container->locationHistory()
            ->orderBy('recorded_at', 'desc')
            ->paginate(
                min(50, max(1, (int) $request->input('page_size', 20))),
                ['*'],
                'page',
                max(1, (int) $request->input('page_num', 0) + 1)
            );

        return response()->json([
            'data' => $history->items(),
            'meta' => [
                'total'     => $history->total(),
                'page_num'  => $history->currentPage() - 1,
                'page_size' => $history->perPage(),
                'pages'     => $history->lastPage(),
            ],
        ]);
    }
}
