<?php

namespace App\Services\DataUpload;

use App\Models\Tenant\Booking;
use App\Models\Tenant\Container;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\SKU;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DataUploadService
{
    private const SUPPORTED_TYPES = ['containers', 'bookings', 'purchase_orders', 'skus'];

    private const CONTAINER_RULES = [
        'container_number' => 'required|string|max:20',
        'status'           => 'nullable|string',
        'carrier_scac'     => 'nullable|string|max:10',
        'size'             => 'nullable|string',
        'type'             => 'nullable|string',
        'eta'              => 'nullable|date',
    ];

    private const BOOKING_RULES = [
        'booking_number' => 'required|string|max:50',
        'carrier_scac'   => 'nullable|string|max:10',
        'etd'            => 'nullable|date',
        'eta'            => 'nullable|date',
    ];

    private const PURCHASE_ORDER_RULES = [
        'po_number'  => 'required|string|max:100',
        'vendor'     => 'nullable|string|max:255',
        'ship_date'  => 'nullable|date',
    ];

    private const SKU_RULES = [
        'sku_code'   => 'required|string|max:100',
        'description'=> 'nullable|string|max:255',
        'quantity'   => 'nullable|integer|min:0',
    ];

    public function processUpload(string $type, UploadedFile $file): array
    {
        $this->assertSupportedType($type);

        $rows    = $this->parseFile($file);
        $results = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // 1-based, +1 for header row

            $validation = $this->validateRow($type, $row);

            if (!empty($validation)) {
                $results['errors'][] = ['row' => $rowNumber, 'errors' => $validation];
                continue;
            }

            try {
                $existed = $this->upsertRow($type, $row);
                $existed ? $results['updated']++ : $results['created']++;
            } catch (\Throwable $e) {
                Log::error('DataUploadService: row upsert failed', [
                    'type'  => $type,
                    'row'   => $rowNumber,
                    'error' => $e->getMessage(),
                ]);
                $results['errors'][] = ['row' => $rowNumber, 'errors' => [$e->getMessage()]];
            }
        }

        return $results;
    }

    public function validateRow(string $type, array $row): array
    {
        $rules = match ($type) {
            'containers'      => self::CONTAINER_RULES,
            'bookings'        => self::BOOKING_RULES,
            'purchase_orders' => self::PURCHASE_ORDER_RULES,
            'skus'            => self::SKU_RULES,
            default           => [],
        };

        $validator = Validator::make($row, $rules);

        return $validator->fails() ? $validator->errors()->all() : [];
    }

    public function getTemplate(string $type): string
    {
        $this->assertSupportedType($type);

        $headers = match ($type) {
            'containers'      => array_keys(self::CONTAINER_RULES),
            'bookings'        => array_keys(self::BOOKING_RULES),
            'purchase_orders' => array_keys(self::PURCHASE_ORDER_RULES),
            'skus'            => array_keys(self::SKU_RULES),
        };

        $path   = storage_path("templates/{$type}.csv");
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);
        fclose($handle);

        return $path;
    }

    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            return $this->parseCsv($file->getRealPath());
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseExcel($file->getRealPath());
        }

        throw new \InvalidArgumentException("Unsupported file type: {$extension}. Use CSV or XLSX.");
    }

    private function parseCsv(string $path): array
    {
        $handle  = fopen($path, 'r');
        $headers = fgetcsv($handle);
        $rows    = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === count($headers)) {
                $rows[] = array_combine($headers, $line);
            }
        }

        fclose($handle);

        return $rows;
    }

    private function parseExcel(string $path): array
    {
        // Stub: In production uses PhpSpreadsheet or maatwebsite/excel
        Log::info('DataUploadService: parseExcel stub called', ['path' => $path]);
        return [];
    }

    private function upsertRow(string $type, array $row): bool
    {
        return match ($type) {
            'containers'      => $this->upsertContainer($row),
            'bookings'        => $this->upsertBooking($row),
            'purchase_orders' => $this->upsertPurchaseOrder($row),
            'skus'            => $this->upsertSku($row),
        };
    }

    private function upsertContainer(array $row): bool
    {
        $existing = Container::where('container_number', $row['container_number'])->first();
        Container::updateOrCreate(['container_number' => $row['container_number']], $row);
        return $existing !== null;
    }

    private function upsertBooking(array $row): bool
    {
        $existing = Booking::where('booking_number', $row['booking_number'])->first();
        Booking::updateOrCreate(['booking_number' => $row['booking_number']], $row);
        return $existing !== null;
    }

    private function upsertPurchaseOrder(array $row): bool
    {
        $existing = PurchaseOrder::where('po_number', $row['po_number'])->first();
        PurchaseOrder::updateOrCreate(['po_number' => $row['po_number']], $row);
        return $existing !== null;
    }

    private function upsertSku(array $row): bool
    {
        $existing = SKU::where('sku_code', $row['sku_code'])->first();
        SKU::updateOrCreate(['sku_code' => $row['sku_code']], $row);
        return $existing !== null;
    }

    private function assertSupportedType(string $type): void
    {
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Unsupported upload type: {$type}. Supported types: " . implode(', ', self::SUPPORTED_TYPES)
            );
        }
    }
}
