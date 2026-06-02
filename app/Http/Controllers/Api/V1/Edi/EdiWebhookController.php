<?php

namespace App\Http\Controllers\Api\V1\Edi;

use App\Http\Controllers\Controller;
use App\Services\Edi\Edi315Parser;
use App\Services\Edi\Edi315Processor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EdiWebhookController extends Controller
{
    public function __construct(
        private readonly Edi315Parser    $parser,
        private readonly Edi315Processor $processor,
    ) {}

    // ----------------------------------------------------------------
    // POST /api/v1/edi/315
    // ----------------------------------------------------------------

    /**
     * Receive a raw EDI 315 message from an EDI provider.
     *
     * Authentication: X-EDI-Key header must match EDI_WEBHOOK_KEY in .env.
     * Content-Type:   text/plain  OR  application/edi-x12
     */
    public function receive315(Request $request): JsonResponse
    {
        if (! $this->authenticateEdiKey($request)) {
            return $this->error('Unauthorized', 401);
        }

        $contentType = $request->header('Content-Type', '');
        $allowedTypes = ['text/plain', 'application/edi-x12', 'application/x-edi-x12'];
        $typeOk = collect($allowedTypes)->contains(
            fn ($t) => str_contains(strtolower($contentType), strtolower($t))
        );

        if (! $typeOk) {
            return $this->error(
                'Unsupported Content-Type. Use text/plain or application/edi-x12.',
                415
            );
        }

        $rawEdi = $request->getContent();

        if (empty(trim($rawEdi))) {
            return $this->error('Request body is empty', 422);
        }

        Log::debug('EdiWebhookController: received EDI 315', [
            'content_length' => strlen($rawEdi),
            'sender_ip'      => $request->ip(),
        ]);

        try {
            $parsed = $this->parser->parse($rawEdi);
            $result = $this->processor->process($parsed);

            Log::info('EdiWebhookController: EDI 315 processed', $result);

            return $this->success($result, 'EDI 315 processed successfully');
        } catch (\Throwable $e) {
            Log::error('EdiWebhookController: EDI 315 processing failed', [
                'error'   => $e->getMessage(),
                'raw_edi' => substr($rawEdi, 0, 500),
            ]);

            return $this->error('EDI processing failed: ' . $e->getMessage(), 500);
        }
    }

    // ----------------------------------------------------------------
    // POST /api/v1/edi/315/test
    // ----------------------------------------------------------------

    /**
     * Test endpoint — accepts JSON with an edi_text field.
     * Requires auth:api.
     */
    public function test315(Request $request): JsonResponse
    {
        $request->validate([
            'edi_text' => ['required', 'string'],
        ]);

        $rawEdi = $request->input('edi_text');

        try {
            $parsed = $this->parser->parse($rawEdi);
            $result = $this->processor->process($parsed);

            return $this->success([
                'parsed'     => $parsed,
                'processing' => $result,
            ], 'EDI 315 test processed');
        } catch (\Throwable $e) {
            return $this->error('EDI test failed: ' . $e->getMessage(), 500);
        }
    }

    // ----------------------------------------------------------------
    // GET /api/v1/edi/315/sample
    // ----------------------------------------------------------------

    /**
     * Return a sample EDI 315 message for integration testing.
     */
    public function sample315(): JsonResponse
    {
        $samplePath = database_path('seeders/data/sample_edi_315.txt');

        if (! file_exists($samplePath)) {
            return $this->error('Sample file not found', 404);
        }

        $rawEdi  = file_get_contents($samplePath);
        $parsed  = $this->parser->parse($rawEdi);

        return $this->success([
            'raw'    => $rawEdi,
            'parsed' => $parsed,
        ], 'Sample EDI 315 message');
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function authenticateEdiKey(Request $request): bool
    {
        $configuredKey = config('services.edi.webhook_key');

        // If no key is configured, reject all requests in production;
        // allow in non-production environments for easier local dev.
        if (empty($configuredKey)) {
            return ! app()->isProduction();
        }

        $providedKey = $request->header('X-EDI-Key');

        return hash_equals($configuredKey, (string) $providedKey);
    }
}
