<?php

namespace App\Http\Controllers\Api\V1\PurchaseOrder;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrder\PurchaseOrderResource;
use App\Models\Tenant\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    /**
     * GET /api/v1/purchase-orders
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'created_at'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, PurchaseOrderResource::class);
    }

    /**
     * GET /api/v1/purchase-orders/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $po = PurchaseOrder::where('uuid', $uuid)->with('items')->first();

        if (! $po) {
            return $this->notFound('Purchase order not found');
        }

        return $this->success(new PurchaseOrderResource($po));
    }

    /**
     * POST /api/v1/purchase-orders
     */
    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $po = PurchaseOrder::create($data);

        foreach ($items as $item) {
            $po->items()->create($item);
        }

        return $this->created(new PurchaseOrderResource($po->load('items')), 'Purchase order created');
    }

    /**
     * PUT /api/v1/purchase-orders/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $po = PurchaseOrder::where('uuid', $uuid)->first();

        if (! $po) {
            return $this->notFound('Purchase order not found');
        }

        $request->validate([
            'po_number'      => 'sometimes|string|max:100',
            'vendor_uuid'    => 'nullable|uuid',
            'factory_uuid'   => 'nullable|uuid',
            'order_date'     => 'sometimes|date',
            'required_date'  => 'nullable|date',
            'ship_date'      => 'nullable|date',
            'status'         => 'nullable|in:draft,confirmed,in_production,shipped,received,cancelled',
            'notes'          => 'nullable|string',
        ]);

        $po->update($request->only([
            'po_number', 'vendor_uuid', 'factory_uuid', 'order_date',
            'required_date', 'ship_date', 'status', 'notes',
        ]));

        return $this->success(new PurchaseOrderResource($po->fresh()), 'Purchase order updated');
    }

    /**
     * DELETE /api/v1/purchase-orders/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $po = PurchaseOrder::where('uuid', $uuid)->first();

        if (! $po) {
            return $this->notFound('Purchase order not found');
        }

        $po->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/purchase-orders/filter
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

        $query = PurchaseOrder::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, PurchaseOrderResource::class);
    }

    /**
     * GET /api/v1/purchase-orders/{uuid}/items
     */
    public function items(string $uuid, Request $request): JsonResponse
    {
        $po = PurchaseOrder::where('uuid', $uuid)->first();

        if (! $po) {
            return $this->notFound('Purchase order not found');
        }

        $items = $po->items()
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->success($items);
    }
}
