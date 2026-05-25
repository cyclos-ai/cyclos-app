<?php

namespace App\Services\DataUpload;

use App\Domain\Container\Enums\ContainerStatus;
use App\Events\Container\ContainerCreated;
use App\Events\Tracking\TrackingRequestCreated;
use App\Models\Central\User;
use App\Models\Tenant\Container;
use App\Models\Tenant\MBL;
use App\Models\Tenant\TrackingRequest;
use App\Models\Tenant\UploadBatch;
use App\Services\Tracking\ContainerTrackingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContainerCSVUploadService
{
    public function __construct(
        private ContainerTrackingService $trackingService,
    ) {}

    public function processContainerUpload(
        UploadedFile $file,
        bool $hasHeader = true,
        ?array $mapping = null,
        ?User $user = null,
    ): array {
        $batchId = Str::uuid()->toString();
        $rows = $this->parseFile($file, $hasHeader);
        $mapping = $mapping ?? $this->getDefaultMapping();

        $results = [
            'batch_id' => $batchId,
            'total_rows' => count($rows),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        UploadBatch::create([
            'id' => $batchId,
            'organization_id' => tenancy()->tenant?->id,
            'uploaded_by' => $user?->id,
            'filename' => $file->getClientOriginalName(),
            'total_rows' => count($rows),
            'status' => 'processing',
        ]);

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                try {
                    $mapped = $this->mapRow($row, $mapping);
                    $this->processRow($mapped, $results, $user);
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'row' => $index + ($hasHeader ? 2 : 1),
                        'error' => $e->getMessage(),
                    ];
                    $results['skipped']++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CSV upload failed', ['batch_id' => $batchId, 'error' => $e->getMessage()]);
            throw $e;
        }

        UploadBatch::where('id', $batchId)->update([
            'status' => 'completed',
            'created_count' => $results['created'],
            'updated_count' => $results['updated'],
            'skipped_count' => $results['skipped'],
            'error_count' => count($results['errors']),
            'errors' => !empty($results['errors']) ? $results['errors'] : null,
            'completed_at' => now(),
        ]);

        return $results;
    }

    private function processRow(array $data, array &$results, ?User $user): void
    {
        if (empty($data['mbl_number']) && empty($data['container_number'])) {
            throw new \InvalidArgumentException('Either MBL number or container number is required');
        }

        $organizationId = tenancy()->tenant?->id;

        $mbl = null;
        if (!empty($data['mbl_number'])) {
            $carrierScac = $this->trackingService->detectCarrierFromMBL($data['mbl_number']);

            $mbl = MBL::updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'mbl_number' => strtoupper(trim($data['mbl_number'])),
                ],
                array_filter([
                    'carrier_scac' => $carrierScac ?? $data['carrier_scac'] ?? null,
                    'pol' => $data['pol'] ?? null,
                    'pod' => $data['pod'] ?? null,
                    'shipper_name' => $data['shipper_name'] ?? null,
                    'consignee_name' => $data['consignee_name'] ?? null,
                ], fn($v) => $v !== null),
            );
        }

        if (!empty($data['container_number'])) {
            $containerNumber = strtoupper(trim($data['container_number']));

            $existing = Container::where('organization_id', $organizationId)
                ->where('container_number', $containerNumber)
                ->first();

            if ($existing) {
                $existing->update(array_filter([
                    'mbl_id' => $mbl?->id,
                    'carrier_scac' => $data['carrier_scac'] ?? null,
                    'size' => $data['size'] ?? null,
                    'type' => $data['type'] ?? null,
                    'weight' => $data['weight'] ?? null,
                    'pol' => $data['pol'] ?? null,
                    'pod' => $data['pod'] ?? null,
                    'final_destination' => $data['final_destination'] ?? null,
                    'eta' => $data['eta'] ?? null,
                    'seal_number' => $data['seal_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ], fn($v) => $v !== null));
                $results['updated']++;
            } else {
                $container = Container::create([
                    'organization_id' => $organizationId,
                    'container_number' => $containerNumber,
                    'mbl_id' => $mbl?->id,
                    'carrier_scac' => $data['carrier_scac'] ?? ($mbl?->carrier_scac),
                    'status' => ContainerStatus::NOT_TRACKING->value,
                    'size' => $data['size'] ?? null,
                    'type' => $data['type'] ?? null,
                    'weight' => $data['weight'] ?? null,
                    'weight_unit' => $data['weight_unit'] ?? 'KG',
                    'seal_number' => $data['seal_number'] ?? null,
                    'pol' => $data['pol'] ?? ($mbl?->pol),
                    'pod' => $data['pod'] ?? ($mbl?->pod),
                    'final_destination' => $data['final_destination'] ?? null,
                    'eta' => $data['eta'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                ContainerCreated::dispatch($container);

                if ($container->carrier_scac || $mbl) {
                    $trackingRequest = TrackingRequest::create([
                        'organization_id' => $organizationId,
                        'request_type' => $mbl ? 'MBL' : 'CONTAINER',
                        'reference_number' => $mbl?->mbl_number ?? $containerNumber,
                        'carrier_scac' => $container->carrier_scac,
                        'container_id' => $container->id,
                        'mbl_id' => $mbl?->id,
                        'requested_by' => $user?->id,
                        'status' => 'pending',
                    ]);
                    TrackingRequestCreated::dispatch($trackingRequest);
                }

                $results['created']++;
            }
        } elseif ($mbl) {
            $existingRequest = TrackingRequest::where('organization_id', $organizationId)
                ->where('reference_number', $mbl->mbl_number)
                ->where('request_type', 'MBL')
                ->exists();

            if (!$existingRequest) {
                $trackingRequest = TrackingRequest::create([
                    'organization_id' => $organizationId,
                    'request_type' => 'MBL',
                    'reference_number' => $mbl->mbl_number,
                    'carrier_scac' => $mbl->carrier_scac,
                    'mbl_id' => $mbl->id,
                    'requested_by' => $user?->id,
                    'status' => 'pending',
                ]);
                TrackingRequestCreated::dispatch($trackingRequest);
                $results['created']++;
            } else {
                $results['updated']++;
            }
        }
    }

    private function parseFile(UploadedFile $file, bool $hasHeader): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if (in_array($extension, ['csv', 'txt'])) {
            $handle = fopen($file->getRealPath(), 'r');
            $header = null;

            while (($line = fgetcsv($handle)) !== false) {
                if ($hasHeader && $header === null) {
                    $header = array_map('strtolower', array_map('trim', $line));
                    continue;
                }

                if ($header) {
                    $row = [];
                    foreach ($header as $i => $col) {
                        $row[$col] = $line[$i] ?? null;
                    }
                    $rows[] = $row;
                } else {
                    $rows[] = $line;
                }
            }
            fclose($handle);
        }

        return $rows;
    }

    private function mapRow(array $row, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $csvColumn => $dbField) {
            $csvColumn = strtolower(trim($csvColumn));
            if (isset($row[$csvColumn]) && $row[$csvColumn] !== '') {
                $mapped[$dbField] = trim($row[$csvColumn]);
            }
        }
        return $mapped;
    }

    public function getDefaultMapping(): array
    {
        return [
            'mbl' => 'mbl_number',
            'mbl_number' => 'mbl_number',
            'master_bill' => 'mbl_number',
            'master_bol' => 'mbl_number',
            'container' => 'container_number',
            'container_number' => 'container_number',
            'container_no' => 'container_number',
            'cntr_no' => 'container_number',
            'carrier' => 'carrier_scac',
            'carrier_scac' => 'carrier_scac',
            'scac' => 'carrier_scac',
            'shipping_line' => 'carrier_scac',
            'steamship_line' => 'carrier_scac',
            'size' => 'size',
            'container_size' => 'size',
            'type' => 'type',
            'container_type' => 'type',
            'weight' => 'weight',
            'gross_weight' => 'weight',
            'weight_unit' => 'weight_unit',
            'seal' => 'seal_number',
            'seal_number' => 'seal_number',
            'pol' => 'pol',
            'port_of_loading' => 'pol',
            'origin_port' => 'pol',
            'pod' => 'pod',
            'port_of_discharge' => 'pod',
            'destination_port' => 'pod',
            'final_destination' => 'final_destination',
            'delivery_location' => 'final_destination',
            'eta' => 'eta',
            'estimated_arrival' => 'eta',
            'shipper' => 'shipper_name',
            'shipper_name' => 'shipper_name',
            'consignee' => 'consignee_name',
            'consignee_name' => 'consignee_name',
            'notes' => 'notes',
            'reference' => 'notes',
        ];
    }

    public function getAvailableFields(): array
    {
        return [
            ['field' => 'mbl_number', 'label' => 'Master Bill of Lading (MBL)', 'required' => false, 'description' => 'MBL number with 4-letter SCAC prefix (e.g., MAEU123456789)'],
            ['field' => 'container_number', 'label' => 'Container Number', 'required' => false, 'description' => 'Container number (e.g., MSCU1234567)'],
            ['field' => 'carrier_scac', 'label' => 'Carrier SCAC Code', 'required' => false, 'description' => '4-letter Standard Carrier Alpha Code'],
            ['field' => 'size', 'label' => 'Container Size', 'required' => false, 'description' => '20, 40, or 45'],
            ['field' => 'type', 'label' => 'Container Type', 'required' => false, 'description' => 'DRY, REEFER, FLAT_RACK, OPEN_TOP, TANK'],
            ['field' => 'weight', 'label' => 'Weight', 'required' => false, 'description' => 'Gross weight'],
            ['field' => 'weight_unit', 'label' => 'Weight Unit', 'required' => false, 'description' => 'KG or LBS (default: KG)'],
            ['field' => 'seal_number', 'label' => 'Seal Number', 'required' => false],
            ['field' => 'pol', 'label' => 'Port of Loading', 'required' => false, 'description' => 'UN/LOCODE (e.g., CNSHA)'],
            ['field' => 'pod', 'label' => 'Port of Discharge', 'required' => false, 'description' => 'UN/LOCODE (e.g., USLAX)'],
            ['field' => 'final_destination', 'label' => 'Final Destination', 'required' => false],
            ['field' => 'eta', 'label' => 'Estimated Arrival', 'required' => false, 'description' => 'Date format: YYYY-MM-DD'],
            ['field' => 'shipper_name', 'label' => 'Shipper Name', 'required' => false],
            ['field' => 'consignee_name', 'label' => 'Consignee Name', 'required' => false],
            ['field' => 'notes', 'label' => 'Notes', 'required' => false],
        ];
    }

    public function getTemplate(): array
    {
        return [
            'headers' => ['MBL Number', 'Container Number', 'Carrier SCAC', 'Size', 'Type', 'Weight', 'Weight Unit', 'Seal Number', 'Port of Loading', 'Port of Discharge', 'Final Destination', 'ETA', 'Shipper Name', 'Consignee Name', 'Notes'],
            'sample_rows' => [
                ['MAEU123456789', 'MSCU1234567', 'MAEU', '40', 'DRY', '25000', 'KG', 'SL12345', 'CNSHA', 'USLAX', 'Chicago, IL', '2024-06-15', 'ABC Exports Co', 'XYZ Imports LLC', 'Priority shipment'],
                ['CMDU987654321', 'CMAU7654321', 'CMDU', '20', 'REEFER', '18000', 'KG', 'SL67890', 'KRPUS', 'USNYC', 'Newark, NJ', '2024-06-20', 'Korea Trading', 'East Coast Dist', 'Temperature sensitive'],
            ],
            'notes' => 'Either MBL Number or Container Number is required. SCAC codes are auto-detected from MBL prefix if not provided.',
        ];
    }

    public function getUploadStatus(string $batchId): ?array
    {
        $batch = UploadBatch::find($batchId);
        if (!$batch) {
            return null;
        }

        return [
            'batch_id' => $batch->id,
            'filename' => $batch->filename,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'created' => $batch->created_count ?? 0,
            'updated' => $batch->updated_count ?? 0,
            'skipped' => $batch->skipped_count ?? 0,
            'errors' => $batch->errors ?? [],
            'uploaded_at' => $batch->created_at,
            'completed_at' => $batch->completed_at,
        ];
    }

    public function getUploadHistory(int $page = 0, int $pageSize = 20): array
    {
        $query = UploadBatch::where('organization_id', tenancy()->tenant?->id)
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $items = $query->skip($page * $pageSize)->take($pageSize)->get();

        return [
            'items' => $items,
            'total' => $total,
            'page_num' => $page,
            'page_size' => $pageSize,
        ];
    }
}
