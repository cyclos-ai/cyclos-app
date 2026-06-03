<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocumentExtractionService
{
    private ?string $apiKey;
    private string $model;
    private string $baseUrl;

    private const EXTRACTION_PROMPT = <<<'PROMPT'
Extract all shipping/logistics data from this document and return ONLY a valid JSON object with no markdown, no code fences, and no explanation. Use null for missing fields.

Return this exact structure:
{
  "document_type": "delivery_order|bill_of_lading|arrival_notice|booking_confirmation|other",
  "container_numbers": [],
  "mbl_number": null,
  "booking_number": null,
  "carrier_scac": null,
  "carrier_name": null,
  "vessel_name": null,
  "voyage_number": null,
  "pol": null,
  "pod": null,
  "final_destination": null,
  "eta": null,
  "etd": null,
  "shipper": null,
  "consignee": null,
  "notify_party": null,
  "reference_numbers": {},
  "weight": null,
  "container_type": null,
  "confidence": "high|medium|low"
}

Rules:
- container_numbers: array of strings like ["MSCU1234567"]
- eta/etd: ISO date YYYY-MM-DD or null
- carrier_scac: 4-letter SCAC code if identifiable (e.g. MAEU, MSCU, HLCU)
- confidence: high if most fields filled, medium if partial, low if sparse
- Return ONLY the JSON object, nothing else
PROMPT;

    public function __construct()
    {
        $this->apiKey  = config('services.anthropic.api_key');
        $this->model   = config('services.anthropic.model', 'claude-3-5-sonnet-20241022');
        $this->baseUrl = rtrim(config('services.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');
    }

    /**
     * Returns true if the Anthropic API key is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Extract structured shipping data from a base64-encoded PDF.
     *
     * @param  string $pdfBase64  Base64-encoded PDF content.
     * @return array              Structured data array, or ['error' => '...'] on failure.
     */
    public function extractFromPdf(string $pdfBase64): array
    {
        if (! $this->isConfigured()) {
            return ['error' => 'Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your environment.'];
        }

        Log::info('DocumentExtractionService: sending PDF to Claude', [
            'model'       => $this->model,
            'base64_size' => strlen($pdfBase64),
        ]);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post("{$this->baseUrl}/messages", [
                    'model'      => $this->model,
                    'max_tokens' => 2048,
                    'messages'   => [
                        [
                            'role'    => 'user',
                            'content' => [
                                [
                                    'type'   => 'document',
                                    'source' => [
                                        'type'       => 'base64',
                                        'media_type' => 'application/pdf',
                                        'data'       => $pdfBase64,
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => self::EXTRACTION_PROMPT,
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                $status = $response->status();
                $body   = $response->body();
                Log::warning('DocumentExtractionService: API request failed', [
                    'status' => $status,
                    'body'   => $body,
                ]);

                return ['error' => "Anthropic API returned HTTP {$status}: " . $response->json('error.message', 'Unknown error')];
            }

            $rawText = $response->json('content.0.text', '');

            if (empty($rawText)) {
                Log::warning('DocumentExtractionService: empty response from Claude');

                return ['error' => 'No content returned from Anthropic API.'];
            }

            $cleaned = $this->stripMarkdownFences($rawText);

            $data = json_decode($cleaned, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('DocumentExtractionService: JSON decode failed', [
                    'json_error' => json_last_error_msg(),
                    'raw_text'   => $rawText,
                ]);

                return ['error' => 'Failed to parse extraction response as JSON. ' . json_last_error_msg()];
            }

            Log::info('DocumentExtractionService: extraction successful', [
                'document_type'       => $data['document_type'] ?? 'unknown',
                'confidence'          => $data['confidence']    ?? 'unknown',
                'container_count'     => count($data['container_numbers'] ?? []),
            ]);

            return $data;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::warning('DocumentExtractionService: HTTP request exception', ['error' => $e->getMessage()]);

            return ['error' => 'HTTP request to Anthropic API failed: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            Log::warning('DocumentExtractionService: unexpected error', ['error' => $e->getMessage()]);

            return ['error' => 'Unexpected error during document extraction: ' . $e->getMessage()];
        }
    }

    /**
     * Strip markdown code fences (```json ... ``` or ``` ... ```) from a string.
     */
    private function stripMarkdownFences(string $text): string
    {
        $text = trim($text);

        // Remove ```json ... ``` or ``` ... ```
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)\s*```$/m', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
    }
}
