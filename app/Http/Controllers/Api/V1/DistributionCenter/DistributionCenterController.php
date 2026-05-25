<?php

namespace App\Http\Controllers\Api\V1\DistributionCenter;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DistributionCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistributionCenterController extends Controller
{
    /**
     * GET /api/v1/distribution-centers
     */
    public function index(Request $request): JsonResponse
    {
        $query = DistributionCenter::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'name'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/distribution-centers/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $dc = DistributionCenter::where('uuid', $uuid)->first();

        if (! $dc) {
            return $this->notFound('Distribution center not found');
        }

        return $this->success($dc);
    }

    /**
     * POST /api/v1/distribution-centers
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'nullable|string|max:20',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country_code'  => 'nullable|string|max:2',
            'zip_code'      => 'nullable|string|max:20',
            'contact_name'  => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'is_active'     => 'nullable|boolean',
        ]);

        $dc = DistributionCenter::create($request->validated());

        return $this->created($dc, 'Distribution center created');
    }

    /**
     * POST /api/v1/distribution-centers/{uuid}/containers
     */
    public function associateContainer(Request $request, string $uuid): JsonResponse
    {
        $dc = DistributionCenter::where('uuid', $uuid)->first();

        if (! $dc) {
            return $this->notFound('Distribution center not found');
        }

        $request->validate([
            'container_uuid'    => 'required|uuid',
            'expected_arrival'  => 'nullable|date',
            'actual_arrival'    => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        $association = $dc->containers()->attach(
            $request->input('container_uuid'),
            [
                'expected_arrival' => $request->input('expected_arrival'),
                'actual_arrival'   => $request->input('actual_arrival'),
                'notes'            => $request->input('notes'),
            ]
        );

        return $this->created([
            'distribution_center_uuid' => $uuid,
            'container_uuid'           => $request->input('container_uuid'),
        ], 'Container associated with distribution center');
    }

    /**
     * POST /api/v1/distribution-centers/filter
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

        $query = DistributionCenter::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginate($query, $request);
    }
}
