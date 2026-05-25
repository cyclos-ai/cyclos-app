<?php

namespace App\Http\Controllers\Api\V1\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\Invoice\DrayageInvoiceResource;
use App\Models\Tenant\DrayageInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrayageInvoiceController extends Controller
{
    /**
     * GET /api/v1/drayage-invoices
     */
    public function index(Request $request): JsonResponse
    {
        $query = DrayageInvoice::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'invoice_date'),
            (int) $request->input('direction', -1)
        );

        return $this->paginateResource($query, $request, DrayageInvoiceResource::class);
    }

    /**
     * GET /api/v1/drayage-invoices/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $invoice = DrayageInvoice::where('uuid', $uuid)->with('items', 'payments')->first();

        if (! $invoice) {
            return $this->notFound('Drayage invoice not found');
        }

        return $this->success(new DrayageInvoiceResource($invoice));
    }

    /**
     * POST /api/v1/drayage-invoices
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_number'   => 'required|string|max:100',
            'carrier_name'     => 'required|string|max:255',
            'invoice_date'     => 'required|date',
            'due_date'         => 'nullable|date',
            'total_amount'     => 'required|numeric|min:0',
            'currency'         => 'nullable|string|max:3',
            'container_uuid'   => 'nullable|uuid',
            'import_drayage_uuid' => 'nullable|uuid',
            'items'            => 'nullable|array',
            'items.*.charge_type'  => 'required_with:items|string',
            'items.*.amount'       => 'required_with:items|numeric|min:0',
            'items.*.description'  => 'nullable|string',
        ]);

        $data  = $request->except('items');
        $items = $request->input('items', []);

        $invoice = DrayageInvoice::create($data);

        foreach ($items as $item) {
            $invoice->items()->create($item);
        }

        return $this->created(new DrayageInvoiceResource($invoice->load('items')), 'Drayage invoice created');
    }

    /**
     * PUT /api/v1/drayage-invoices/{uuid}
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $invoice = DrayageInvoice::where('uuid', $uuid)->first();

        if (! $invoice) {
            return $this->notFound('Drayage invoice not found');
        }

        $request->validate([
            'invoice_number' => 'sometimes|string|max:100',
            'carrier_name'   => 'sometimes|string|max:255',
            'invoice_date'   => 'sometimes|date',
            'due_date'       => 'nullable|date',
            'total_amount'   => 'sometimes|numeric|min:0',
            'currency'       => 'nullable|string|max:3',
            'status'         => 'nullable|in:pending,ok_to_pay,paid,disputed,voided',
        ]);

        $invoice->update($request->only([
            'invoice_number', 'carrier_name', 'invoice_date', 'due_date',
            'total_amount', 'currency', 'status',
        ]));

        return $this->success(new DrayageInvoiceResource($invoice->fresh()), 'Drayage invoice updated');
    }

    /**
     * DELETE /api/v1/drayage-invoices/{uuid}
     */
    public function destroy(string $uuid): JsonResponse
    {
        $invoice = DrayageInvoice::where('uuid', $uuid)->first();

        if (! $invoice) {
            return $this->notFound('Drayage invoice not found');
        }

        $invoice->delete();

        return $this->noContent();
    }

    /**
     * POST /api/v1/drayage-invoices/{uuid}/payments
     */
    public function addPayment(Request $request, string $uuid): JsonResponse
    {
        $invoice = DrayageInvoice::where('uuid', $uuid)->first();

        if (! $invoice) {
            return $this->notFound('Drayage invoice not found');
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

        $totalPaid = $invoice->payments()->sum('amount');
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid', 'paid_date' => $request->input('payment_date')]);
        }

        return $this->created($payment, 'Payment recorded');
    }

    /**
     * POST /api/v1/drayage-invoices/filter
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

        $query = DrayageInvoice::query();

        if ($request->has('filters')) {
            $this->applyFilters($query, $request->input('filters', []));
        }

        $this->applySorting($query, $request->input('order_by'), (int) $request->input('direction', 1));

        return $this->paginateResource($query, $request, DrayageInvoiceResource::class);
    }
}
