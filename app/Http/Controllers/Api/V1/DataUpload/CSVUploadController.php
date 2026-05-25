<?php

namespace App\Http\Controllers\Api\V1\DataUpload;

use App\Http\Controllers\Controller;
use App\Services\DataUpload\ContainerCSVUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CSVUploadController extends Controller
{
    public function __construct(
        private ContainerCSVUploadService $uploadService,
    ) {}

    // POST /api/v1/uploads/containers
    public function uploadContainers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
            'has_header' => 'boolean',
            'mapping' => 'nullable|array',
        ]);

        $result = $this->uploadService->processContainerUpload(
            $request->file('file'),
            $request->boolean('has_header', true),
            $request->input('mapping'),
            $request->user(),
        );

        return $this->success($result, 'Upload processed successfully');
    }

    // GET /api/v1/uploads/containers/template
    public function downloadTemplate(): JsonResponse
    {
        $template = $this->uploadService->getTemplate();
        return $this->success($template);
    }

    // GET /api/v1/uploads/containers/mapping-fields
    public function mappingFields(): JsonResponse
    {
        $fields = $this->uploadService->getAvailableFields();
        return $this->success($fields);
    }

    // GET /api/v1/uploads/{upload_id}/status
    public function uploadStatus(string $uploadId): JsonResponse
    {
        $status = $this->uploadService->getUploadStatus($uploadId);
        if (!$status) {
            return $this->notFound('Upload not found');
        }
        return $this->success($status);
    }

    // GET /api/v1/uploads/history
    public function uploadHistory(Request $request): JsonResponse
    {
        $history = $this->uploadService->getUploadHistory(
            (int) $request->input('page_num', 0),
            (int) $request->input('page_size', 20),
        );
        return $this->success($history);
    }
}
