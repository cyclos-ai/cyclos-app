<?php

namespace App\Services\QuickBooks;

use App\Domain\Invoice\Enums\ChargeType;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Models\Tenant\DrayageInvoice;
use App\Models\Tenant\OceanInvoice;
use App\Models\Tenant\Organization;
use Illuminate\Support\Facades\Log;

/**
 * Pushes Cyclos Ocean & Drayage invoices into QuickBooks Online and syncs
 * their payment status back. Consumes the sibling-owned QuickBooksService for
 * all authenticated QBO communication; this class only maps Cyclos data to/from
 * the QBO entity shapes.
 *
 * QBO requires every invoice line to reference an existing Item via ItemRef and
 * every invoice to reference an existing Customer via CustomerRef, so customers
 * and items are resolved (found-or-created) before an invoice is posted.
 */
class QuickBooksInvoiceService
{
    /**
     * Per-request cache of resolved QBO Item ids, keyed by item name.
     *
     * @var array<string, string>
     */
    private static array $itemIdCache = [];

    public function __construct(
        private readonly QuickBooksService $qb,
    ) {}

    /**
     * Find a QBO Customer by display name, creating it from the organization if absent.
     *
     * @return array{id?: string, error?: string}
     */
    public function findOrCreateCustomer(Organization $org): array
    {
        try {
            $displayName = $org->display_name ?: $org->name;

            if (! $displayName) {
                return ['error' => 'Organization has no name to map to a QuickBooks customer'];
            }

            $existing = $this->query(
                "SELECT * FROM Customer WHERE DisplayName = '" . $this->escape($displayName) . "'",
                'Customer'
            );

            if ($existing === null) {
                return ['error' => 'Failed to query QuickBooks for customer'];
            }

            if (! empty($existing)) {
                return ['id' => (string) $existing[0]['Id']];
            }

            $payload = ['DisplayName' => $displayName];

            if ($org->name) {
                $payload['CompanyName'] = $org->name;
            }

            if ($org->phone) {
                $payload['PrimaryPhone'] = ['FreeFormNumber' => $org->phone];
            }

            $billAddr = array_filter([
                'Line1'                  => $org->address_line1,
                'City'                   => $org->city,
                'CountrySubDivisionCode' => $org->state,
                'PostalCode'             => $org->postal_code,
                'Country'                => $org->country,
            ], static fn ($value) => $value !== null && $value !== '');

            if (! empty($billAddr)) {
                $payload['BillAddr'] = $billAddr;
            }

            $response = $this->qb->request('post', 'customer', $payload);

            if (! is_array($response) || isset($response['error'])) {
                return ['error' => $this->errorMessage($response, 'Failed to create QuickBooks customer')];
            }

            $id = $response['Customer']['Id'] ?? null;

            if (! $id) {
                return ['error' => 'QuickBooks customer create returned no Id'];
            }

            return ['id' => (string) $id];
        } catch (\Throwable $e) {
            Log::error('QuickBooksInvoiceService::findOrCreateCustomer failed', [
                'organization_id' => $org->id ?? null,
                'error'           => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected error resolving QuickBooks customer'];
        }
    }

    /**
     * Resolve the Id of an Income account, required when creating QBO Items.
     */
    public function findOrCreateIncomeAccountId(): ?string
    {
        try {
            $accounts = $this->query(
                "SELECT * FROM Account WHERE AccountType = 'Income' MAXRESULTS 1",
                'Account'
            );

            if (empty($accounts)) {
                return null;
            }

            return isset($accounts[0]['Id']) ? (string) $accounts[0]['Id'] : null;
        } catch (\Throwable $e) {
            Log::error('QuickBooksInvoiceService::findOrCreateIncomeAccountId failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find a QBO Item by name, creating a Service item if absent. Results are
     * cached per request to avoid repeated lookups for the same charge type.
     *
     * @return array{id?: string, error?: string}
     */
    public function findOrCreateItem(string $name): array
    {
        try {
            $name = trim($name) !== '' ? trim($name) : 'Freight Charges';

            if (isset(self::$itemIdCache[$name])) {
                return ['id' => self::$itemIdCache[$name]];
            }

            $existing = $this->query(
                "SELECT * FROM Item WHERE Name = '" . $this->escape($name) . "'",
                'Item'
            );

            if ($existing === null) {
                return ['error' => 'Failed to query QuickBooks for item'];
            }

            if (! empty($existing)) {
                $id = (string) $existing[0]['Id'];
                self::$itemIdCache[$name] = $id;

                return ['id' => $id];
            }

            $incomeAccountId = $this->findOrCreateIncomeAccountId();

            if (! $incomeAccountId) {
                return ['error' => 'No QuickBooks income account available to create item'];
            }

            $response = $this->qb->request('post', 'item', [
                'Name'           => $name,
                'Type'           => 'Service',
                'IncomeAccountRef' => ['value' => $incomeAccountId],
            ]);

            if (! is_array($response) || isset($response['error'])) {
                return ['error' => $this->errorMessage($response, 'Failed to create QuickBooks item')];
            }

            $id = $response['Item']['Id'] ?? null;

            if (! $id) {
                return ['error' => 'QuickBooks item create returned no Id'];
            }

            self::$itemIdCache[$name] = (string) $id;

            return ['id' => (string) $id];
        } catch (\Throwable $e) {
            Log::error('QuickBooksInvoiceService::findOrCreateItem failed', [
                'name'  => $name,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected error resolving QuickBooks item'];
        }
    }

    /**
     * Push an Ocean invoice to QuickBooks as a QBO Invoice.
     *
     * @return array{qb_invoice_id?: string, doc_number?: string, error?: string}
     */
    public function pushOceanInvoice(OceanInvoice $invoice): array
    {
        return $this->pushInvoice($invoice);
    }

    /**
     * Push a Drayage invoice to QuickBooks as a QBO Invoice.
     *
     * @return array{qb_invoice_id?: string, doc_number?: string, error?: string}
     */
    public function pushDrayageInvoice(DrayageInvoice $invoice): array
    {
        return $this->pushInvoice($invoice);
    }

    /**
     * Sync an Ocean invoice's status from QuickBooks (marks paid when balance hits zero).
     *
     * @return array{balance?: float, total?: float, status?: string, error?: string}
     */
    public function syncOceanInvoiceStatus(OceanInvoice $invoice): array
    {
        return $this->syncInvoiceStatus($invoice);
    }

    /**
     * Sync a Drayage invoice's status from QuickBooks (marks paid when balance hits zero).
     *
     * @return array{balance?: float, total?: float, status?: string, error?: string}
     */
    public function syncDrayageInvoiceStatus(DrayageInvoice $invoice): array
    {
        return $this->syncInvoiceStatus($invoice);
    }

    /**
     * Shared push implementation for both Ocean and Drayage invoices.
     *
     * @param  OceanInvoice|DrayageInvoice  $invoice
     * @return array{qb_invoice_id?: string, doc_number?: string, error?: string}
     */
    private function pushInvoice(OceanInvoice|DrayageInvoice $invoice): array
    {
        try {
            if (! $this->qb->isConnected()) {
                return ['error' => 'QuickBooks not connected'];
            }

            if (! $invoice->organization) {
                return ['error' => 'Invoice has no organization to map to a QuickBooks customer'];
            }

            $customer = $this->findOrCreateCustomer($invoice->organization);

            if (isset($customer['error'])) {
                return ['error' => $customer['error']];
            }

            $lines = $this->buildLines($invoice);

            if (isset($lines['error'])) {
                return ['error' => $lines['error']];
            }

            $docNumber = substr((string) $invoice->invoice_number, 0, 21);

            $payload = [
                'Line'        => $lines['lines'],
                'CustomerRef' => ['value' => $customer['id']],
            ];

            if ($docNumber !== '') {
                $payload['DocNumber'] = $docNumber;
            }

            if ($invoice->invoice_date) {
                $payload['TxnDate'] = $invoice->invoice_date->format('Y-m-d');
            }

            if ($invoice->due_date) {
                $payload['DueDate'] = $invoice->due_date->format('Y-m-d');
            }

            if ($invoice->currency) {
                $payload['CurrencyRef'] = ['value' => $invoice->currency];
            }

            if ($invoice->notes) {
                $payload['PrivateNote'] = $invoice->notes;
            }

            $response = $this->qb->request('post', 'invoice', $payload);

            if (! is_array($response) || isset($response['error'])) {
                return ['error' => $this->errorMessage($response, 'Failed to create QuickBooks invoice')];
            }

            $qbId = $response['Invoice']['Id'] ?? null;

            if (! $qbId) {
                return ['error' => 'QuickBooks invoice create returned no Id'];
            }

            $qbDocNumber = $response['Invoice']['DocNumber'] ?? $docNumber;

            $metadata = $invoice->metadata ?? [];
            $metadata['qb_invoice_id'] = (string) $qbId;
            $metadata['qb_doc_number'] = $qbDocNumber;
            $metadata['qb_synced_at']  = now()->toIso8601String();
            $invoice->metadata = $metadata;
            $invoice->save();

            return [
                'qb_invoice_id' => (string) $qbId,
                'doc_number'    => $qbDocNumber,
            ];
        } catch (\Throwable $e) {
            Log::error('QuickBooksInvoiceService::pushInvoice failed', [
                'invoice_id'   => $invoice->id ?? null,
                'invoice_type' => $invoice::class,
                'error'        => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected error pushing invoice to QuickBooks'];
        }
    }

    /**
     * Build QBO SalesItemLineDetail lines for an invoice. Falls back to a single
     * total-based line (item "Freight Charges") when the invoice has no items.
     *
     * @param  OceanInvoice|DrayageInvoice  $invoice
     * @return array{lines?: array<int, array<string, mixed>>, error?: string}
     */
    private function buildLines(OceanInvoice|DrayageInvoice $invoice): array
    {
        $lines = [];
        $items = $invoice->items;

        if ($items !== null && $items->isNotEmpty()) {
            foreach ($items as $item) {
                $itemName = $this->chargeTypeLabel($item->charge_type);
                $resolved = $this->findOrCreateItem($itemName);

                if (isset($resolved['error'])) {
                    return ['error' => $resolved['error']];
                }

                $lines[] = [
                    'DetailType'          => 'SalesItemLineDetail',
                    'Amount'              => (float) $item->amount,
                    'Description'         => $item->description,
                    'SalesItemLineDetail' => [
                        'ItemRef'   => ['value' => $resolved['id']],
                        'Qty'       => $item->quantity,
                        'UnitPrice' => (float) $item->unit_price,
                    ],
                ];
            }

            return ['lines' => $lines];
        }

        $resolved = $this->findOrCreateItem('Freight Charges');

        if (isset($resolved['error'])) {
            return ['error' => $resolved['error']];
        }

        $lines[] = [
            'DetailType'          => 'SalesItemLineDetail',
            'Amount'              => (float) $invoice->total_amount,
            'Description'         => $invoice->notes ?: 'Freight Charges',
            'SalesItemLineDetail' => [
                'ItemRef'   => ['value' => $resolved['id']],
                'Qty'       => 1,
                'UnitPrice' => (float) $invoice->total_amount,
            ],
        ];

        return ['lines' => $lines];
    }

    /**
     * Resolve a human-readable QBO item name from an invoice item's charge type.
     *
     * Ocean items cast charge_type to the ChargeType enum, while Drayage items
     * store it as a raw string, so both shapes (and null) are handled here.
     *
     * @param  ChargeType|string|null  $chargeType
     */
    private function chargeTypeLabel(ChargeType|string|null $chargeType): string
    {
        if ($chargeType instanceof ChargeType) {
            return $chargeType->label();
        }

        if (is_string($chargeType) && trim($chargeType) !== '') {
            return ChargeType::tryFrom($chargeType)?->label() ?? trim($chargeType);
        }

        return 'Freight Charges';
    }

    /**
     * Shared status-sync implementation for both Ocean and Drayage invoices.
     *
     * @param  OceanInvoice|DrayageInvoice  $invoice
     * @return array{balance?: float, total?: float, status?: string, error?: string}
     */
    private function syncInvoiceStatus(OceanInvoice|DrayageInvoice $invoice): array
    {
        try {
            if (! $this->qb->isConnected()) {
                return ['error' => 'QuickBooks not connected'];
            }

            $qbInvoiceId = $invoice->metadata['qb_invoice_id'] ?? null;

            if (! $qbInvoiceId) {
                return ['error' => 'Not pushed to QuickBooks yet'];
            }

            $response = $this->qb->request('get', 'invoice/' . $qbInvoiceId);

            if (! is_array($response) || isset($response['error'])) {
                return ['error' => $this->errorMessage($response, 'Failed to fetch QuickBooks invoice')];
            }

            $qbInvoice = $response['Invoice'] ?? null;

            if (! is_array($qbInvoice)) {
                return ['error' => 'QuickBooks invoice not found'];
            }

            $balance = (float) ($qbInvoice['Balance'] ?? 0);
            $total   = (float) ($qbInvoice['TotalAmt'] ?? 0);

            if ($balance == 0.0) {
                $invoice->status = InvoiceStatus::PAID;

                if (! $invoice->payment_date) {
                    $invoice->payment_date = now()->toDateString();
                }

                $invoice->save();
            }

            return [
                'balance' => $balance,
                'total'   => $total,
                'status'  => $invoice->status instanceof InvoiceStatus
                    ? $invoice->status->value
                    : (string) $invoice->status,
            ];
        } catch (\Throwable $e) {
            Log::error('QuickBooksInvoiceService::syncInvoiceStatus failed', [
                'invoice_id'   => $invoice->id ?? null,
                'invoice_type' => $invoice::class,
                'error'        => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected error syncing invoice status from QuickBooks'];
        }
    }

    /**
     * Run a QBO query and normalize the response down to the entity rows,
     * handling both the unwrapped and QueryResponse-wrapped shapes.
     *
     * Returns an array of rows (possibly empty) on success, or null on error.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function query(string $sql, string $entity): ?array
    {
        $response = $this->qb->query($sql);

        if (! is_array($response) || isset($response['error'])) {
            Log::warning('QuickBooksInvoiceService: query failed', [
                'entity' => $entity,
                'error'  => is_array($response) ? ($response['error'] ?? null) : null,
            ]);

            return null;
        }

        if (isset($response['QueryResponse'])) {
            return $response['QueryResponse'][$entity] ?? [];
        }

        return $response[$entity] ?? [];
    }

    /**
     * Escape single quotes for QBO query string literals by doubling them.
     */
    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * Extract a human-readable error message from a QBO error response.
     *
     * @param  mixed  $response
     */
    private function errorMessage($response, string $fallback): string
    {
        if (is_array($response) && isset($response['error'])) {
            $error = $response['error'];

            if (is_string($error) && $error !== '') {
                return $error;
            }

            if (is_array($error)) {
                return $fallback . ': ' . json_encode($error);
            }
        }

        return $fallback;
    }
}
