<?php

namespace App\Http\Controllers\Api\V1\DataUpload;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DataUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DataUploadController extends Controller
{
    protected array $supportedTypes = [
        'containers',
        'purchase_orders',
        'skus',
        'vendors',
        'factories',
        'carrier_contracts',
    ];

    /**
     * POST /api/v1/data-upload
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'        => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'upload_type' => 'required|in:' . implode(',', $this->supportedTypes),
            'options'     => 'nullable|array',
        ]);

        $file     = $request->file('file');
        $type     = $request->input('upload_type');
        $path     = $file->store('uploads/' . $type, 's3');

        $upload = DataUpload::create([
            'upload_type'  => $type,
            'file_name'    => $file->getClientOriginalName(),
            'file_path'    => $path,
            'file_size'    => $file->getSize(),
            'mime_type'    => $file->getMimeType(),
            'status'       => 'pending',
            'user_id'      => $request->user()->id,
            'options'      => $request->input('options'),
        ]);

        // Dispatch processing job in production:
        // dispatch(new ProcessDataUploadJob($upload))->onQueue('uploads');

        return $this->created([
            'upload_uuid' => $upload->uuid,
            'status'      => 'pending',
            'file_name'   => $upload->file_name,
        ], 'File uploaded and queued for processing');
    }

    /**
     * GET /api/v1/data-upload/template/{type}
     */
    public function template(string $type): JsonResponse
    {
        if (! in_array($type, $this->supportedTypes)) {
            return $this->error('Unsupported template type. Supported: ' . implode(', ', $this->supportedTypes), 422);
        }

        $templates = [
            'containers' => [
                'headers'  => ['container_number', 'carrier_scac', 'mbl_number', 'booking_number', 'pol', 'pod', 'eta', 'size', 'type', 'weight_kg', 'priority'],
                'example'  => ['ABCD1234567', 'MAEU', 'MAEU123456789', 'BKG987654321', 'CNSHA', 'USLAX', '2024-03-15', '40', 'HC', '24500', 'normal'],
            ],
            'purchase_orders' => [
                'headers'  => ['po_number', 'vendor_code', 'order_date', 'required_date', 'ship_date', 'status'],
                'example'  => ['PO-2024-001', 'VEND001', '2024-01-15', '2024-03-01', '2024-02-15', 'confirmed'],
            ],
            'skus' => [
                'headers'  => ['sku_code', 'description', 'unit_of_measure', 'weight_kg', 'volume_cbm', 'country_of_origin', 'hts_code'],
                'example'  => ['SKU-001', 'Blue Widget 10cm', 'EA', '0.5', '0.001', 'CN', '8302.41.6000'],
            ],
            'vendors' => [
                'headers'  => ['name', 'vendor_code', 'country_code', 'city', 'contact_email', 'payment_terms'],
                'example'  => ['ACME Corp', 'ACME001', 'CN', 'Shenzhen', 'contact@acme.com', 'Net 30'],
            ],
            'factories' => [
                'headers'  => ['name', 'country_code', 'city', 'address', 'contact_email'],
                'example'  => ['Shenzhen Factory 1', 'CN', 'Shenzhen', '123 Industrial Rd', 'factory@example.com'],
            ],
            'carrier_contracts' => [
                'headers'  => ['carrier_scac', 'contract_type', 'free_days_demurrage', 'free_days_detention', 'effective_date', 'expiry_date'],
                'example'  => ['MAEU', 'custom', '10', '7', '2024-01-01', '2024-12-31'],
            ],
        ];

        return $this->success([
            'type'     => $type,
            'template' => $templates[$type] ?? null,
            'download_url' => null, // In production: signed URL to pre-built template file
        ]);
    }

    /**
     * GET /api/v1/data-upload/status/{uuid}
     */
    public function status(string $uuid): JsonResponse
    {
        $upload = DataUpload::where('uuid', $uuid)->first();

        if (! $upload) {
            return $this->notFound('Upload record not found');
        }

        return $this->success([
            'uuid'           => $upload->uuid,
            'status'         => $upload->status,
            'file_name'      => $upload->file_name,
            'upload_type'    => $upload->upload_type,
            'rows_total'     => $upload->rows_total,
            'rows_processed' => $upload->rows_processed,
            'rows_failed'    => $upload->rows_failed,
            'errors'         => $upload->errors,
            'created_at'     => $upload->created_at,
            'completed_at'   => $upload->completed_at,
        ]);
    }
}
