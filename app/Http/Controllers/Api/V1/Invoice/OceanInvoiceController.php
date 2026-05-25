<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreOceanInvoiceRequest;
use App\Http\Resources\Invoice\OceanInvoiceResource;
use App\Models\Tenant\OceanInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OceanInvoiceController extends Controller
{
    /**
     * GET /api/v1/ocean-invoices
     */
    public function index(Request $request): JsonResponse
    {
        $query = OceanInvoice::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'invoice_date'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, OceanInvoiceResource::class);
    }

    /**
     * GET /api/v1/ocean-invoices/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $invoice = OceanInvoice::where('uuid', $uuid)->with('items', 'payments')->first();

        if (! $invoice) {
            return $this->notFound('Ocean invoice not found');
        }

        return $this->success(new OceanInvoiceResource($invoice));
    }

    /**
     * POST /api/v1/ocean-invoices
     */
    public function store(StoreOceanInvoiceRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $invoice = OceanInvoice::create($data);

        foreach ($items as $item) {
            $invoice->items()->create($item);
        }

        return $this->created(new OceanInvoiceResource($invoice->load('items')), 'Ocean invoice created');
    }

    /**
     * PUT /api/v1/ocean-invoices/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $invoice = OceanInvoice::where('uuid', $uuid)->first();

        if (! $invoice) {
            return $this->notFound('Ocean invoice not found');
        }

        $request->validate([
            'invoice_number' => 'sometimes|string|max:100',
            'carrier_scac'   => 'nullable|string|max:4',
            'invoice_date'   => 'sometimes|date',
            'due_date'       => 'nullable|date',
            'total_amount'   => 'sometimes|numeric|min:0',
            'currency'       => 'nullable|string|max:3',
            'status'         => 'nullable|in:pending,ok_to_pay,paid,disputed,voided',
            'notes'          => 'nullable|string',
        ]);

        $invoice->update($request->only([
            'invoice_number', 'carrier_scac', 'invoice_date', 'due_date',
            'total_amount', 'currency', 'status', 'notes',
        ]));

        return $this->success(new OceanInvoiceResource($invoice->fresh()), 'Ocean invoice updated');
    }

    /**
     * DELETE /api/v1/ocean-invoices/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $invoice = OceanInvoice::where('uuid', $uuid)->first();

        if (! $invoice) {
            return $this->notFound('Ocean invoice not found');
        }

        $invoice->delete();

        return $this->noContent();
    }

    /**
     * GET /api/v1/ocean-invoices/ok-to-pay
     */
    public function okToPay(Request $request): JsonResponse
    {
        $query = OceanInvoice::where('status', 'ok_to_pay');

        $this->applySorting($query, $request->input('order_by', 'due_date'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, OceanInvoiceResource::class);
    }

    /**
     * GET /api/v1/ocean-invoices/paid
     */
    public function paid(Request $request): JsonResponse
    {
        $query = OceanInvoice::where('status', 'paid');

        $this->applySorting($query, $request->input('order_by', 'paid_date'), (int) $request->input('direction', -1));

        return $this->paginateResource($query, $request, OceanInvoiceResource::class);
    }

    /**
     * POST /api/v1/ocean-invoices/filter
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

        $query = OceanInvoice::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, OceanInvoiceResource::class);
    }

    /**
     * POST /api/v1/ocean-invoices/{uuid}/payments
     */
    public function addPayment(Request $request, string $uuid): JsonResponse
    {
        $invoice = OceanInvoice::where('uuid', $uuid)->first();

        if (! $invoice) {
            return $this->notFound('Ocean invoice not found');
        }

        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_date'   => 'required|date',
            'payment_method' => 'nullable|string|in:wire,ach,check,credit_card,other',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        $payment = $invoice->payments()->create($request->only([
            'amount', 'payment_date', 'payment_method', 'reference', 'notes',
        ]));

        // Update invoice status if fully paid
        $totalPaid = $invoice->payments()->sum('amount');
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid', 'paid_date' => $request->input('payment_date')]);
        }

        return $this->created($payment, 'Payment recorded');
    }
}
