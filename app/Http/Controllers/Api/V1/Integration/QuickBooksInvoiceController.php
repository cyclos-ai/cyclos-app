<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DrayageInvoice;
use App\Models\Tenant\OceanInvoice;
use App\Services\QuickBooks\QuickBooksInvoiceService;
use Illuminate\Http\JsonResponse;

/**
 * User-initiated QuickBooks Online invoice sync endpoints. These operate on the
 * connected company's PRODUCTION books and are intended to run only on explicit
 * user action (e.g. a "Push to QuickBooks" button), never automatically.
 */
class QuickBooksInvoiceController extends Controller
{
    public function __construct(
        private readonly QuickBooksInvoiceService $service,
    ) {}

    /**
     * POST /api/v1/integrations/quickbooks/invoices/ocean/{uuid}/push
     */
    public function pushOcean(string $uuid): JsonResponse
    {
        $invoice = OceanInvoice::with(['organization', 'items'])->find($uuid);

        if (! $invoice) {
            return $this->notFound('Ocean invoice not found');
        }

        $result = $this->service->pushOceanInvoice($invoice);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Invoice pushed to QuickBooks');
    }

    /**
     * POST /api/v1/integrations/quickbooks/invoices/ocean/{uuid}/sync
     */
    public function syncOcean(string $uuid): JsonResponse
    {
        $invoice = OceanInvoice::find($uuid);

        if (! $invoice) {
            return $this->notFound('Ocean invoice not found');
        }

        $result = $this->service->syncOceanInvoiceStatus($invoice);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Invoice status synced from QuickBooks');
    }

    /**
     * POST /api/v1/integrations/quickbooks/invoices/drayage/{uuid}/push
     */
    public function pushDrayage(string $uuid): JsonResponse
    {
        $invoice = DrayageInvoice::with(['organization', 'items'])->find($uuid);

        if (! $invoice) {
            return $this->notFound('Drayage invoice not found');
        }

        $result = $this->service->pushDrayageInvoice($invoice);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Invoice pushed to QuickBooks');
    }

    /**
     * POST /api/v1/integrations/quickbooks/invoices/drayage/{uuid}/sync
     */
    public function syncDrayage(string $uuid): JsonResponse
    {
        $invoice = DrayageInvoice::find($uuid);

        if (! $invoice) {
            return $this->notFound('Drayage invoice not found');
        }

        $result = $this->service->syncDrayageInvoiceStatus($invoice);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Invoice status synced from QuickBooks');
    }
}
