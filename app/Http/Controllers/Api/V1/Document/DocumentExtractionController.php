<?php

namespace App\Http\Controllers\Api\V1\Document;

use App\Http\Controllers\Controller;
use App\Services\Ocr\DocumentExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentExtractionController extends Controller
{
    public function __construct(
        private readonly DocumentExtractionService $extractionService
    ) {}

    /**
     * GET /api/v1/documents/extract/status
     * Returns whether the Anthropic API is configured.
     */
    public function status(): JsonResponse
    {
        return $this->success([
            'configured' => $this->extractionService->isConfigured(),
        ]);
    }

    /**
     * POST /api/v1/documents/extract
     * Accepts a PDF file upload, extracts structured shipping data via Claude.
     */
    public function extract(Request $request): JsonResponse
    {
        if (! $this->extractionService->isConfigured()) {
            return $this->error(
                'Document extraction is not configured. Add ANTHROPIC_API_KEY to your environment.',
                503
            );
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $file       = $request->file('file');
        $pdfContent = file_get_contents($file->getRealPath());

        if ($pdfContent === false) {
            return $this->error('Failed to read uploaded file.', 422);
        }

        $pdfBase64 = base64_encode($pdfContent);

        $result = $this->extractionService->extractFromPdf($pdfBase64);

        if (isset($result['error'])) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result, 'Document extracted successfully');
    }
}
