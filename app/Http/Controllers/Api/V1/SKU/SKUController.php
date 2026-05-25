<?php

namespace App\Http\Controllers\Api\V1\SKU;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Sku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SKUController extends Controller
{
    /**
     * GET /api/v1/skus
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sku::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'sku_code'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/skus/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $sku = Sku::where('uuid', $uuid)->first();

        if (! $sku) {
            return $this->notFound('SKU not found');
        }

        return $this->success($sku);
    }

    /**
     * POST /api/v1/skus
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'sku_code'     => 'required|string|max:100',
            'description'  => 'nullable|string|max:500',
            'unit_of_measure' => 'nullable|string|max:50',
            'weight_kg'    => 'nullable|numeric|min:0',
            'volume_cbm'   => 'nullable|numeric|min:0',
            'country_of_origin' => 'nullable|string|max:2',
            'hts_code'     => 'nullable|string|max:20',
            'vendor_uuid'  => 'nullable|uuid',
            'factory_uuid' => 'nullable|uuid',
            'is_active'    => 'nullable|boolean',
        ]);

        $sku = Sku::create($request->validated());

        return $this->created($sku, 'SKU created');
    }

    /**
     * PUT /api/v1/skus/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $sku = Sku::where('uuid', $uuid)->first();

        if (! $sku) {
            return $this->notFound('SKU not found');
        }

        $request->validate([
            'sku_code'          => 'sometimes|string|max:100',
            'description'       => 'nullable|string|max:500',
            'unit_of_measure'   => 'nullable|string|max:50',
            'weight_kg'         => 'nullable|numeric|min:0',
            'volume_cbm'        => 'nullable|numeric|min:0',
            'country_of_origin' => 'nullable|string|max:2',
            'hts_code'          => 'nullable|string|max:20',
            'vendor_uuid'       => 'nullable|uuid',
            'factory_uuid'      => 'nullable|uuid',
            'is_active'         => 'nullable|boolean',
        ]);

        $sku->update($request->validated());

        return $this->success($sku->fresh(), 'SKU updated');
    }

    /**
     * POST /api/v1/skus/filter
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

        $query = Sku::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginate($query, $request);
    }
}
