<?php

namespace App\Jobs\Invoice;

use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Models\Tenant\OceanInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::info('ReconcileInvoicesJob: starting invoice reconciliation');

        $discrepancies = 0;

        OceanInvoice::with('items')
            ->whereNotIn('status', [InvoiceStatus::VOID->value, InvoiceStatus::PAID->value])
            ->chunkById(100, function ($invoices) use (&$discrepancies) {
                foreach ($invoices as $invoice) {
                    $calculatedSubtotal = $invoice->items->sum('amount');
                    $calculatedTotal    = $calculatedSubtotal + (float) $invoice->tax_amount;

                    $subtotalDiff = abs((float) $invoice->subtotal - $calculatedSubtotal);
                    $totalDiff    = abs((float) $invoice->total_amount - $calculatedTotal);

                    if ($subtotalDiff > 0.01 || $totalDiff > 0.01) {
                        Log::warning('ReconcileInvoicesJob: discrepancy found', [
                            'invoice_id'           => $invoice->id,
                            'invoice_number'       => $invoice->invoice_number,
                            'stored_subtotal'      => $invoice->subtotal,
                            'calculated_subtotal'  => $calculatedSubtotal,
                            'stored_total'         => $invoice->total_amount,
                            'calculated_total'     => $calculatedTotal,
                        ]);

                        $invoice->update([
                            'subtotal'     => $calculatedSubtotal,
                            'total_amount' => $calculatedTotal,
                        ]);

                        $discrepancies++;
                    }
                }
            });

        Log::info('ReconcileInvoicesJob: completed', ['discrepancies_fixed' => $discrepancies]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ReconcileInvoicesJob: failed', ['error' => $exception->getMessage()]);
    }
}
