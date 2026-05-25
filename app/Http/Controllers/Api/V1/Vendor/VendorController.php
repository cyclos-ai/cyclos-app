<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * GET /api/v1/vendors
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query();

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
     * GET /api/v1/vendors/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $vendor = Vendor::where('uuid', $uuid)->first();

        if (! $vendor) {
            return $this->notFound('Vendor not found');
        }

        return $this->success($vendor);
    }

    /**
     * POST /api/v1/vendors
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'vendor_code'   => 'nullable|string|max:50',
            'country_code'  => 'nullable|string|max:2',
            'city'          => 'nullable|string|max:100',
            'address'       => 'nullable|string|max:500',
            'contact_name'  => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'payment_terms' => 'nullable|string|max:100',
            'currency'      => 'nullable|string|max:3',
            'is_active'     => 'nullable|boolean',
        ]);

        $vendor = Vendor::create($request->validated());

        return $this->created($vendor, 'Vendor created');
    }

    /**
     * PUT /api/v1/vendors/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $vendor = Vendor::where('uuid', $uuid)->first();

        if (! $vendor) {
            return $this->notFound('Vendor not found');
        }

        $request->validate([
            'name'          => 'sometimes|string|max:255',
            'vendor_code'   => 'nullable|string|max:50',
            'country_code'  => 'nullable|string|max:2',
            'city'          => 'nullable|string|max:100',
            'address'       => 'nullable|string|max:500',
            'contact_name'  => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'payment_terms' => 'nullable|string|max:100',
            'currency'      => 'nullable|string|max:3',
            'is_active'     => 'nullable|boolean',
        ]);

        $vendor->update($request->validated());

        return $this->success($vendor->fresh(), 'Vendor updated');
    }

    /**
     * DELETE /api/v1/vendors/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $vendor = Vendor::where('uuid', $uuid)->first();

        if (! $vendor) {
            return $this->notFound('Vendor not found');
        }

        $vendor->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/vendors/filter
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

        $query = Vendor::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginate($query, $request);
    }
}
