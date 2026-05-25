<?php

namespace App\Services\Invoice;

use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Events\Invoice\InvoiceCreated;
use App\Events\Invoice\InvoicePaymentReceived;
use App\Models\Tenant\DrayageInvoice;
use App\Models\Tenant\DrayageInvoiceItem;
use App\Models\Tenant\OceanInvoice;
use App\Models\Tenant\OceanInvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    private const VALID_OCEAN_TRANSITIONS = [
        InvoiceStatus::DRAFT->value     => [InvoiceStatus::SUBMITTED->value, InvoiceStatus::VOID->value],
        InvoiceStatus::SUBMITTED->value => [InvoiceStatus::APPROVED->value, InvoiceStatus::DISPUTED->value, InvoiceStatus::VOID->value],
        InvoiceStatus::APPROVED->value  => [InvoiceStatus::PAID->value, InvoiceStatus::DISPUTED->value, InvoiceStatus::VOID->value],
        InvoiceStatus::DISPUTED->value  => [InvoiceStatus::APPROVED->value, InvoiceStatus::VOID->value],
        InvoiceStatus::PAID->value      => [],
        InvoiceStatus::VOID->value      => [],
    ];

    public function createOceanInvoice(array $data): OceanInvoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = OceanInvoice::create([
                'organization_id' => $data['organization_id'],
                'mbl_id'          => $data['mbl_id'] ?? null,
                'invoice_number'  => $data['invoice_number'],
                'invoice_date'    => $data['invoice_date'] ?? now()->toDateString(),
                'due_date'        => $data['due_date'] ?? null,
                'status'          => InvoiceStatus::DRAFT,
                'currency'        => $data['currency'] ?? 'USD',
                'notes'           => $data['notes'] ?? null,
                'subtotal'        => 0,
                'tax_amount'      => 0,
                'total_amount'    => 0,
                'metadata'        => $data['metadata'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                OceanInvoiceItem::create([
                    'ocean_invoice_id' => $invoice->id,
                    'container_id'     => $item['container_id'] ?? null,
                    'description'      => $item['description'],
                    'quantity'         => $item['quantity'] ?? 1,
                    'unit_price'       => $item['unit_price'],
                    'amount'           => ($item['quantity'] ?? 1) * $item['unit_price'],
                    'charge_type'      => $item['charge_type'] ?? null,
                ]);
            }

            event(new InvoiceCreated($invoice));

            return $invoice->fresh('items');
        });
    }

    public function updateInvoiceStatus(OceanInvoice|DrayageInvoice $invoice, string $status): OceanInvoice|DrayageInvoice
    {
        $currentStatus  = $invoice->status->value;
        $allowedTargets = self::VALID_OCEAN_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($status, $allowedTargets, true)) {
            throw new \InvalidArgumentException(
                "Cannot transition invoice from {$currentStatus} to {$status}."
            );
        }

        $invoice->update(['status' => $status]);

        return $invoice->fresh();
    }

    public function recordPayment(OceanInvoice|DrayageInvoice $invoice, array $paymentData): OceanInvoice|DrayageInvoice
    {
        $invoice->update([
            'payment_date'       => $paymentData['payment_date'] ?? now()->toDateString(),
            'payment_reference'  => $paymentData['reference'] ?? null,
            'payment_method'     => $paymentData['method'] ?? null,
            'status'             => InvoiceStatus::PAID,
        ]);

        event(new InvoicePaymentReceived($invoice, $paymentData));

        return $invoice->fresh();
    }

    public function createDrayageInvoice(array $data): DrayageInvoice
    {
        return DB::transaction(function () use ($data) {
            $invoice = DrayageInvoice::create([
                'organization_id' => $data['organization_id'],
                'container_id'    => $data['container_id'] ?? null,
                'invoice_number'  => $data['invoice_number'],
                'invoice_date'    => $data['invoice_date'] ?? now()->toDateString(),
                'due_date'        => $data['due_date'] ?? null,
                'status'          => InvoiceStatus::DRAFT,
                'currency'        => $data['currency'] ?? 'USD',
                'notes'           => $data['notes'] ?? null,
                'subtotal'        => 0,
                'tax_amount'      => 0,
                'total_amount'    => 0,
                'metadata'        => $data['metadata'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                DrayageInvoiceItem::create([
                    'drayage_invoice_id' => $invoice->id,
                    'description'        => $item['description'],
                    'quantity'           => $item['quantity'] ?? 1,
                    'unit_price'         => $item['unit_price'],
                    'amount'             => ($item['quantity'] ?? 1) * $item['unit_price'],
                    'charge_type'        => $item['charge_type'] ?? null,
                ]);
            }

            event(new InvoiceCreated($invoice));

            return $invoice->fresh('items');
        });
    }

    public function generateInvoicePdf(OceanInvoice|DrayageInvoice $invoice): string
    {
        // Stub: In production this uses a PDF library (e.g., Barryvdh/Laravel-DomPDF)
        // Returns a file path or base64 string
        Log::info('InvoiceService: generateInvoicePdf stub called', [
            'invoice_id'   => $invoice->id,
            'invoice_type' => get_class($invoice),
        ]);

        return storage_path("invoices/invoice-{$invoice->id}.pdf");
    }
}
