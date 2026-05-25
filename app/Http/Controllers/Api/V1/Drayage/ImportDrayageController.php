<?php

namespace App\Http\Controllers\Api\V1\Drayage;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ImportDrayage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportDrayageController extends Controller
{
    /**
     * GET /api/v1/import-drayage
     */
    public function index(Request $request): JsonResponse
    {
        $query = ImportDrayage::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/import-drayage/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::where('uuid', $uuid)->with('events')->first();

        if (! $drayage) {
            return $this->notFound('Import drayage record not found');
        }

        return $this->success($drayage);
    }

    /**
     * POST /api/v1/import-drayage
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'container_uuid'       => 'required|uuid',
            'carrier_name'         => 'nullable|string|max:255',
            'driver_name'          => 'nullable|string|max:255',
            'pickup_location'      => 'nullable|string|max:500',
            'delivery_location'    => 'nullable|string|max:500',
            'scheduled_pickup'     => 'nullable|date',
            'scheduled_delivery'   => 'nullable|date',
            'actual_pickup'        => 'nullable|date',
            'actual_delivery'      => 'nullable|date',
            'status'               => 'nullable|in:pending,dispatched,picked_up,in_transit,delivered,cancelled',
            'notes'                => 'nullable|string',
        ]);

        $drayage = ImportDrayage::create($request->validated());

        return $this->created($drayage, 'Import drayage record created');
    }

    /**
     * PUT /api/v1/import-drayage/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::where('uuid', $uuid)->first();

        if (! $drayage) {
            return $this->notFound('Import drayage record not found');
        }

        $request->validate([
            'carrier_name'       => 'nullable|string|max:255',
            'driver_name'        => 'nullable|string|max:255',
            'pickup_location'    => 'nullable|string|max:500',
            'delivery_location'  => 'nullable|string|max:500',
            'scheduled_pickup'   => 'nullable|date',
            'scheduled_delivery' => 'nullable|date',
            'actual_pickup'      => 'nullable|date',
            'actual_delivery'    => 'nullable|date',
            'status'             => 'nullable|in:pending,dispatched,picked_up,in_transit,delivered,cancelled',
            'notes'              => 'nullable|string',
        ]);

        $drayage->update($request->validated());

        return $this->success($drayage->fresh(), 'Import drayage record updated');
    }

    /**
     * DELETE /api/v1/import-drayage/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $drayage = ImportDrayage::where('uuid', $uuid)->first();

        if (! $drayage) {
            return $this->notFound('Import drayage record not found');
        }

        $drayage->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/import-drayage/{uuid}/events
     */
    public function events(string $uuid, Request $request): JsonResponse
    {
        $drayage = ImportDrayage::where('uuid', $uuid)->first();

        if (! $drayage) {
            return $this->notFound('Import drayage record not found');
        }

        $events = $drayage->events()
            ->orderBy('occurred_at', 'desc')
            ->get();

        return $this->success($events);
    }

    /**
     * POST /api/v1/import-drayage/filter
     */
    public function filter(Request $request): JsonResponse
    {
        $request->validate([
            'filters'            => 'nullable|array',
            'filters.*.field'    => 'required_with:filters|string',
            'filters.*.operator' => 'required_with:filters|string|in:eq,neq,gt,gte,lt,lte,contains,not_contains,starts_with,ends_with,is_null,is_not_null,in,not_in',
            'filters.*.value'    => 'nullable',
            'order_by'           => 'nullable|string',
            'direction'          => 'nullable|in:1,-1',
            'page_num'           => 'nullable|integer|min:0',
            'page_size'          => 'nullable|integer|min:1|max:50',
        ]);

        $query = ImportDrayage::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginate($query, $request);
    }
}
