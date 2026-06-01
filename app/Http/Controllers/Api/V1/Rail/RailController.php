<?php

namespace App\Http\Controllers\Api\V1\Rail;

use App\Http\Controllers\Controller;
use App\Models\Tenant\RailMilestone;
use App\Models\Tenant\RailRamp;
use App\Models\Tenant\RailShipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RailController extends Controller
{
    /**
     * GET /api/v1/rail/carriers
     */
    public function carrierLookup(Request $request): JsonResponse
    {
        $carriers = [
            ['scac' => 'BNSF', 'name' => 'BNSF Railway',                  'network' => 'Class I'],
            ['scac' => 'UP',   'name' => 'Union Pacific Railroad',         'network' => 'Class I'],
            ['scac' => 'CSX',  'name' => 'CSX Transportation',             'network' => 'Class I'],
            ['scac' => 'NS',   'name' => 'Norfolk Southern Railway',       'network' => 'Class I'],
            ['scac' => 'CN',   'name' => 'Canadian National Railway',      'network' => 'Class I'],
            ['scac' => 'CP',   'name' => 'Canadian Pacific Kansas City',   'network' => 'Class I'],
            ['scac' => 'KCS',  'name' => 'Kansas City Southern',           'network' => 'Class I'],
        ];

        return $this->success($carriers);
    }

    /**
     * GET /api/v1/rail/milestones/container/{container_number}
     */
    public function milestonesByContainer(string $containerNumber, Request $request): JsonResponse
    {
        $milestones = RailMilestone::where('container_number', strtoupper($containerNumber))
            ->orderBy('event_date', 'desc')
            ->get();

        if ($milestones->isEmpty()) {
            return $this->notFound('No rail milestones found for container');
        }

        return $this->success($milestones);
    }

    /**
     * GET /api/v1/rail/milestones/{uuid}
     */
    public function milestonesByUuid(string $uuid): JsonResponse
    {
        $milestone = RailMilestone::where('uuid', $uuid)->first();

        if (! $milestone) {
            return $this->notFound('Rail milestone not found');
        }

        return $this->success($milestone);
    }

    /**
     * GET /api/v1/rail/ramps
     * Optional query params: ?carrier=BNSF&state=GA&active=1
     */
    public function ramps(Request $request): JsonResponse
    {
        $query = RailRamp::query()->orderBy('carrier_scac')->orderBy('name');

        if ($request->filled('carrier')) {
            $query->byCarrier($request->input('carrier'));
        }

        if ($request->filled('state')) {
            $query->byState($request->input('state'));
        }

        if ($request->boolean('active', false)) {
            $query->active();
        }

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/rail/ramps/{code}
     */
    public function rampDetail(string $code): JsonResponse
    {
        $ramp = RailRamp::where('code', strtoupper($code))->first();

        if (! $ramp) {
            return $this->notFound('Rail ramp not found');
        }

        return $this->success($ramp);
    }

    /**
     * GET /api/v1/rail/shipments
     * Optional query params: ?carrier=BNSF&status=in_transit&container_id=uuid
     */
    public function shipments(Request $request): JsonResponse
    {
        $query = RailShipment::with(['container', 'originRamp', 'destinationRamp'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('carrier')) {
            $query->where('rail_carrier_scac', strtoupper($request->input('carrier')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('container_id')) {
            $query->where('container_id', $request->input('container_id'));
        }

        return $this->paginate($query, $request);
    }

    /**
     * POST /api/v1/rail/shipments
     */
    public function storeShipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'container_id'         => ['required', 'uuid', 'exists:containers,id'],
            'rail_carrier_scac'    => ['required', 'string', 'max:10'],
            'train_id'             => ['nullable', 'string', 'max:50'],
            'origin_ramp_id'       => ['nullable', 'uuid', 'exists:rail_ramps,id'],
            'destination_ramp_id'  => ['nullable', 'uuid', 'exists:rail_ramps,id'],
            'origin_port'          => ['nullable', 'string', 'max:10'],
            'status'               => ['nullable', 'string', 'in:pending,loaded,in_transit,arrived,available,picked_up'],
            'departed_at'          => ['nullable', 'date'],
            'eta'                  => ['nullable', 'date'],
            'arrived_at'           => ['nullable', 'date'],
            'available_at'         => ['nullable', 'date'],
            'picked_up_at'         => ['nullable', 'date'],
            'bill_of_lading'       => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string'],
            'metadata'             => ['nullable', 'array'],
        ]);

        $shipment = RailShipment::create($validated);
        $shipment->load(['container', 'originRamp', 'destinationRamp']);

        return $this->created($shipment, 'Rail shipment created');
    }

    /**
     * GET /api/v1/rail/shipments/{uuid}
     */
    public function shipmentDetail(string $uuid): JsonResponse
    {
        $shipment = RailShipment::with(['container', 'originRamp', 'destinationRamp', 'milestones'])
            ->find($uuid);

        if (! $shipment) {
            return $this->notFound('Rail shipment not found');
        }

        return $this->success($shipment);
    }

    /**
     * PUT /api/v1/rail/shipments/{uuid}
     */
    public function updateShipment(Request $request, string $uuid): JsonResponse
    {
        $shipment = RailShipment::find($uuid);

        if (! $shipment) {
            return $this->notFound('Rail shipment not found');
        }

        $validated = $request->validate([
            'rail_carrier_scac'    => ['sometimes', 'string', 'max:10'],
            'train_id'             => ['nullable', 'string', 'max:50'],
            'origin_ramp_id'       => ['nullable', 'uuid', 'exists:rail_ramps,id'],
            'destination_ramp_id'  => ['nullable', 'uuid', 'exists:rail_ramps,id'],
            'origin_port'          => ['nullable', 'string', 'max:10'],
            'status'               => ['nullable', 'string', 'in:pending,loaded,in_transit,arrived,available,picked_up'],
            'departed_at'          => ['nullable', 'date'],
            'eta'                  => ['nullable', 'date'],
            'arrived_at'           => ['nullable', 'date'],
            'available_at'         => ['nullable', 'date'],
            'picked_up_at'         => ['nullable', 'date'],
            'bill_of_lading'       => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string'],
            'metadata'             => ['nullable', 'array'],
        ]);

        $shipment->update($validated);
        $shipment->load(['container', 'originRamp', 'destinationRamp']);

        return $this->success($shipment, 'Rail shipment updated');
    }

    /**
     * PATCH /api/v1/rail/shipments/{uuid}/status
     */
    public function updateShipmentStatus(Request $request, string $uuid): JsonResponse
    {
        $shipment = RailShipment::find($uuid);

        if (! $shipment) {
            return $this->notFound('Rail shipment not found');
        }

        $validated = $request->validate([
            'status'       => ['required', 'string', 'in:pending,loaded,in_transit,arrived,available,picked_up'],
            'departed_at'  => ['nullable', 'date'],
            'arrived_at'   => ['nullable', 'date'],
            'available_at' => ['nullable', 'date'],
            'picked_up_at' => ['nullable', 'date'],
            'eta'          => ['nullable', 'date'],
        ]);

        // Auto-set timestamp for the new status if not explicitly provided
        $statusTimestamps = [
            'in_transit' => 'departed_at',
            'arrived'    => 'arrived_at',
            'available'  => 'available_at',
            'picked_up'  => 'picked_up_at',
        ];

        $status = $validated['status'];
        $autoField = $statusTimestamps[$status] ?? null;

        if ($autoField && empty($validated[$autoField])) {
            $validated[$autoField] = now();
        }

        $shipment->update($validated);

        return $this->success($shipment, 'Rail shipment status updated');
    }
}
