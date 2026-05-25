<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tracking\StoreTrackingRequest;
use App\Http\Resources\Tracking\TrackingRequestResource;
use App\Models\Tenant\TrackingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingRequestController extends Controller
{
    /**
     * POST /api/v1/tracking-requests
     * Create tracking request with carrier auto-detection.
     */
    public function store(StoreTrackingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tracking = TrackingRequest::create($data);

        return $this->created(new TrackingRequestResource($tracking), 'Tracking request created');
    }

    /**
     * POST /api/v1/tracking-requests/with-carrier
     */
    public function storeWithCarrier(Request $request): JsonResponse
    {
        $request->validate([
            'reference_number' => 'required|string|max:100',
            'request_type'     => 'required|in:MBL,CONTAINER,BOOKING,AWB',
            'carrier_scac'     => 'required|string|max:4',
        ]);

        $tracking = TrackingRequest::create($request->only(['reference_number', 'request_type', 'carrier_scac']));

        return $this->created(new TrackingRequestResource($tracking), 'Tracking request created with carrier');
    }

    /**
     * POST /api/v1/tracking-requests/booking
     */
    public function storeBooking(Request $request): JsonResponse
    {
        $request->validate([
            'booking_number' => 'required|string|max:100',
            'carrier_scac'   => 'required|string|max:4',
        ]);

        $tracking = TrackingRequest::create([
            'reference_number' => $request->input('booking_number'),
            'request_type'     => 'BOOKING',
            'carrier_scac'     => $request->input('carrier_scac'),
        ]);

        return $this->created(new TrackingRequestResource($tracking), 'Booking tracking request created');
    }

    /**
     * POST /api/v1/tracking-requests/non-party
     */
    public function storeNonParty(Request $request): JsonResponse
    {
        $request->validate([
            'reference_number' => 'required|string|max:100',
            'request_type'     => 'required|in:MBL,CONTAINER',
            'carrier_scac'     => 'nullable|string|max:4',
        ]);

        $tracking = TrackingRequest::create(array_merge(
            $request->only(['reference_number', 'request_type', 'carrier_scac']),
            ['is_non_party' => true]
        ));

        return $this->created(new TrackingRequestResource($tracking), 'Non-party tracking request created');
    }

    /**
     * GET /api/v1/tracking-requests/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $tracking = TrackingRequest::where('uuid', $uuid)->first();

        if (! $tracking) {
            return $this->notFound('Tracking request not found');
        }

        return $this->success(new TrackingRequestResource($tracking));
    }

    /**
     * GET /api/v1/tracking-requests/mbl/{mbl_number}
     */
    public function byMbl(string $mblNumber, Request $request): JsonResponse
    {
        $query = TrackingRequest::where('reference_number', $mblNumber)
            ->where('request_type', 'MBL');

        $this->applySorting($query, $request->input('order_by', 'created_at'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, TrackingRequestResource::class);
    }

    /**
     * GET /api/v1/tracking-requests/container/{container_number}
     */
    public function byContainer(string $containerNumber, Request $request): JsonResponse
    {
        $query = TrackingRequest::where('reference_number', strtoupper($containerNumber))
            ->where('request_type', 'CONTAINER');

        $this->applySorting($query, $request->input('order_by', 'created_at'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, TrackingRequestResource::class);
    }

    /**
     * DELETE /api/v1/tracking-requests/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $tracking = TrackingRequest::where('uuid', $uuid)->first();

        if (! $tracking) {
            return $this->notFound('Tracking request not found');
        }

        $tracking->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/tracking-requests/supported-carriers
     */
    public function supportedCarriers(Request $request): JsonResponse
    {
        // Return static list; in production this would come from the carrier service
        $carriers = [
            ['scac' => 'MAEU', 'name' => 'Maersk',          'tracking_types' => ['MBL', 'CONTAINER', 'BOOKING']],
            ['scac' => 'MSCU', 'name' => 'MSC',              'tracking_types' => ['MBL', 'CONTAINER', 'BOOKING']],
            ['scac' => 'CMDU', 'name' => 'CMA CGM',          'tracking_types' => ['MBL', 'CONTAINER', 'BOOKING']],
            ['scac' => 'COSU', 'name' => 'COSCO',            'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'HLCU', 'name' => 'Hapag-Lloyd',      'tracking_types' => ['MBL', 'CONTAINER', 'BOOKING']],
            ['scac' => 'EGLV', 'name' => 'Evergreen',        'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'YMLU', 'name' => 'Yang Ming',        'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'ONEY', 'name' => 'ONE',              'tracking_types' => ['MBL', 'CONTAINER', 'BOOKING']],
            ['scac' => 'APLU', 'name' => 'APL',              'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'ZIMU', 'name' => 'ZIM',              'tracking_types' => ['MBL', 'CONTAINER', 'BOOKING']],
            ['scac' => 'WHLC', 'name' => 'Wan Hai',          'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'SMLU', 'name' => 'SM Line',          'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'ANNU', 'name' => 'ANL',              'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'HDMU', 'name' => 'HMM',              'tracking_types' => ['MBL', 'CONTAINER']],
            ['scac' => 'PCIU', 'name' => 'Pacific International Lines', 'tracking_types' => ['MBL', 'CONTAINER']],
        ];

        return $this->success($carriers);
    }

    /**
     * POST /api/v1/tracking-requests/filter
     */
    public function filter(Request $request): JsonResponse
    {
        $request->validate([
            'filters'          => 'nullable|array',
            'filters.*.field'  => 'required_with:filters|string',
            'filters.*.operator' => 'required_with:filters|string|in:eq,neq,gt,gte,lt,lte,contains,not_contains,starts_with,ends_with,is_null,is_not_null,in,not_in',
            'filters.*.value'  => 'nullable',
            'order_by'         => 'nullable|string',
            'direction'        => 'nullable|in:1,-1',
            'page_num'         => 'nullable|integer|min:0',
            'page_size'        => 'nullable|integer|min:1|max:50',
        ]);

        $query = TrackingRequest::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, TrackingRequestResource::class);
    }
}
