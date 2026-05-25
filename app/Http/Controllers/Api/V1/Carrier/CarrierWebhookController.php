<?php

namespace App\Http\Controllers\Api\V1\Carrier;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CarrierWebhookController extends Controller
{
    /**
     * POST /api/v1/carrier-webhooks/{scac}
     * Receive inbound webhook events from carrier APIs (e.g., Maersk Transport Events).
     */
    public function receive(Request $request, string $scac): JsonResponse
    {
        $scac = strtoupper($scac);

        Log::info("Carrier webhook received from {$scac}", [
            'headers'      => $request->headers->all(),
            'payload_size' => strlen($request->getContent()),
        ]);

        if (!$this->validateSignature($request, $scac)) {
            Log::warning("Carrier webhook signature validation failed for {$scac}");
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->all();
        $events  = $this->parseCarrierPayload($scac, $payload);

        Log::info("Carrier webhook processed for {$scac}", [
            'events_count' => count($events),
        ]);

        // TODO: Dispatch job to process events and update containers
        // ProcessCarrierWebhookJob::dispatch($scac, $events);

        return response()->json([
            'message'          => 'Webhook received successfully.',
            'events_processed' => count($events),
        ]);
    }

    private function validateSignature(Request $request, string $scac): bool
    {
        return match($scac) {
            'MAEU', 'SUDU', 'SAFI' => $this->validateMaerskSignature($request),
            default                 => true,
        };
    }

    private function validateMaerskSignature(Request $request): bool
    {
        $signature = $request->header('X-Maersk-Signature');
        if (!$signature) return true; // Allow unsigned during development

        $secret = config('carriers.webhook_secrets.maersk', '');
        if (empty($secret)) return true;

        $computed = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($computed, $signature);
    }

    private function parseCarrierPayload(string $scac, array $payload): array
    {
        return match($scac) {
            'MAEU', 'SUDU', 'SAFI' => $this->parseMaerskPayload($payload),
            default                 => $payload['events'] ?? [$payload],
        };
    }

    private function parseMaerskPayload(array $payload): array
    {
        $events = [];
        foreach ($payload['events'] ?? [$payload] as $event) {
            $events[] = [
                'event_type'   => $event['eventType'] ?? $event['transportEventTypeCode'] ?? null,
                'event_date'   => $event['eventDateTime'] ?? $event['eventCreatedDateTime'] ?? null,
                'container'    => $event['equipmentReference'] ?? $event['containerNumber'] ?? null,
                'location'     => $event['location']['locationName'] ?? $event['portName'] ?? null,
                'locode'       => $event['location']['UNLocationCode'] ?? null,
                'vessel'       => $event['transportCall']['vessel']['vesselName'] ?? null,
                'voyage'       => $event['transportCall']['carrierVoyageNumber'] ?? null,
                'carrier_scac' => $event['carrierSCACCode'] ?? 'MAEU',
            ];
        }
        return $events;
    }
}
