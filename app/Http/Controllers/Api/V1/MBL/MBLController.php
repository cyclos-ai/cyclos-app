<?php

namespace App\Http\Controllers\Api\V1\MBL;

use App\Http\Controllers\Controller;
use App\Http\Resources\Container\ContainerResource;
use App\Http\Resources\MBL\MBLResource;
use App\Models\Tenant\Mbl;
use App\Services\Vessel\VesselLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MBLController extends Controller
{
    public function __construct(
        private readonly VesselLinkingService $vesselLinkingService,
    ) {}

    /**
     * GET /api/v1/mbls
     */
    public function index(Request $request): JsonResponse
    {
        $query = Mbl::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, MBLResource::class);
    }

    /**
     * POST /api/v1/mbls
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->validationRules(required: true));

        $mbl = Mbl::create([
            'organization_id' => tenancy()->tenant?->id,
            'mbl_number'      => $data['mbl_number'],
            'carrier_scac'    => $data['carrier_scac']   ?? null,
            'pol'             => $data['pol']            ?? null,
            'pod'             => $data['pod']            ?? null,
            'eta'             => $data['eta']            ?? null,
            'etd'             => $data['etd']            ?? null,
            'ata'             => $data['ata']            ?? null,
            'atd'             => $data['atd']            ?? null,
            'status'          => $data['status']         ?? 'active',
            'shipper_name'    => $data['shipper_name']   ?? null,
            'consignee_name'  => $data['consignee_name'] ?? null,
            'notify_party'    => $data['notify_party']   ?? null,
        ]);

        $this->maybeLinkVessel($mbl, $data);

        return $this->created(new MBLResource($mbl->fresh()->load('vessel')), 'MBL created successfully');
    }

    /**
     * GET /api/v1/mbls/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $mbl = Mbl::with('vessel')->find($uuid);

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        return $this->success(new MBLResource($mbl));
    }

    /**
     * PUT /api/v1/mbls/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $mbl = Mbl::find($uuid);

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        $data = $request->validate($this->validationRules(required: false));

        $columns = [
            'mbl_number', 'carrier_scac', 'pol', 'pod', 'eta', 'etd', 'ata', 'atd',
            'status', 'shipper_name', 'consignee_name', 'notify_party',
        ];

        $attrs = [];
        foreach ($columns as $key) {
            if (array_key_exists($key, $data)) {
                $attrs[$key] = $data[$key];
            }
        }

        if (! empty($attrs)) {
            $mbl->update($attrs);
        }

        $this->maybeLinkVessel($mbl, $data);

        return $this->success(new MBLResource($mbl->fresh()->load('vessel')), 'MBL updated successfully');
    }

    /**
     * DELETE /api/v1/mbls/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $mbl = Mbl::find($uuid);

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        $mbl->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/mbls/number/{mbl_number}
     */
    public function byNumber(string $mblNumber): JsonResponse
    {
        $mbl = Mbl::with('vessel')->where('mbl_number', $mblNumber)->first();

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        return $this->success(new MBLResource($mbl));
    }

    /**
     * PATCH /api/v1/mbls/{uuid}/not-tracking
     */
    public function updateNotTracking(Request $request, string $uuid): JsonResponse
    {
        $mbl = Mbl::find($uuid);

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        $request->validate([
            'is_not_tracking' => 'required|boolean',
            'reason'          => 'nullable|string|max:500',
        ]);

        $notTracking = $request->boolean('is_not_tracking');

        // The mbls table has no dedicated is_not_tracking columns — reflect the
        // state in status and keep the reason in metadata.
        $mbl->update([
            'status'   => $notTracking ? 'not_tracking' : 'active',
            'metadata' => array_merge($mbl->metadata ?? [], [
                'not_tracking'        => $notTracking,
                'not_tracking_reason' => $request->input('reason'),
            ]),
        ]);

        return $this->success(new MBLResource($mbl->fresh()), 'MBL tracking status updated');
    }

    /**
     * GET /api/v1/mbls/{uuid}/containers
     */
    public function containers(string $uuid, Request $request): JsonResponse
    {
        $mbl = Mbl::find($uuid);

        if (! $mbl) {
            return $this->notFound('MBL not found');
        }

        $query = $mbl->containers()->getQuery();

        $this->applySorting(
            $query,
            $request->input('order_by', 'container_number'),
            (int) $request->input('direction', 1)
        );

        return $this->paginateResource($query, $request, ContainerResource::class);
    }

    /**
     * Shared validation rules for store/update. `mbl_number` is required only
     * on create. vessel_name/imo/mmsi/voyage_number are vessel-linking inputs.
     */
    private function validationRules(bool $required): array
    {
        return [
            'mbl_number'     => [$required ? 'required' : 'sometimes', 'string', 'max:100'],
            'carrier_scac'   => ['nullable', 'string', 'max:10'],
            'pol'            => ['nullable', 'string', 'max:100'],
            'pod'            => ['nullable', 'string', 'max:100'],
            'eta'            => ['nullable', 'date'],
            'etd'            => ['nullable', 'date'],
            'ata'            => ['nullable', 'date'],
            'atd'            => ['nullable', 'date'],
            'status'         => ['nullable', 'string', 'max:50'],
            'shipper_name'   => ['nullable', 'string', 'max:255'],
            'consignee_name' => ['nullable', 'string', 'max:255'],
            'notify_party'   => ['nullable', 'string', 'max:255'],
            // Vessel auto-linking (OCR / manual entry) — resolved to vessel_id.
            'vessel_name'    => ['nullable', 'string', 'max:255'],
            'imo'            => ['nullable', 'string', 'max:20'],
            'mmsi'           => ['nullable', 'string', 'max:20'],
            'voyage_number'  => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Find or create a vessel from name/imo/mmsi and link it to the MBL.
     */
    private function maybeLinkVessel(Mbl $mbl, array $data): void
    {
        if (empty($data['vessel_name']) && empty($data['imo']) && empty($data['mmsi'])) {
            return;
        }

        $vessel = $this->vesselLinkingService->findOrCreateVessel([
            'vessel_name'   => $data['vessel_name']   ?? null,
            'imo'           => $data['imo']            ?? null,
            'mmsi'          => $data['mmsi']           ?? null,
            'voyage_number' => $data['voyage_number'] ?? null,
            'carrier_scac'  => $data['carrier_scac']  ?? null,
        ]);

        if ($vessel) {
            $mbl->update(['vessel_id' => $vessel->id]);
        }
    }
}
