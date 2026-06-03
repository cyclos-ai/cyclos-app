<?php

declare(strict_types=1);

namespace App\Services\Tracking\Providers;

use App\Services\Tracking\Contracts\ContainerTrackingProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generic DCSA Track & Trace 2.x adapter.
 *
 * Supports two auth strategies:
 *  - 'oauth2'  : fetches an access token via client-credentials grant then calls the API.
 *  - 'apikey'  : sends a static API key in the Authorization or X-Api-Key header.
 *
 * Carrier config shape (from config('services.carriers.*')):
 * {
 *   scac        : string,
 *   name        : string,
 *   base_url    : string|null,
 *   auth        : 'oauth2'|'apikey',
 *   // oauth2:
 *   client_id?  : string,
 *   client_secret? : string,
 *   token_url?  : string,
 *   // apikey:
 *   api_key?    : string,
 * }
 */
class DcsaTrackingProvider implements ContainerTrackingProvider
{
    private ?string $lastError = null;

    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
    ) {}

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function name(): string
    {
        return strtolower($this->config['scac'] ?? 'dcsa');
    }

    public function isConfigured(): bool
    {
        if (empty($this->config['base_url'])) {
            return false;
        }

        $auth = $this->config['auth'] ?? 'apikey';

        if ($auth === 'oauth2') {
            return ! empty($this->config['client_id'])
                && ! empty($this->config['client_secret'])
                && ! empty($this->config['token_url']);
        }

        // apikey
        return ! empty($this->config['api_key']);
    }

    public function supports(?string $carrierScac): bool
    {
        if ($carrierScac === null) {
            return false;
        }

        return strtoupper($carrierScac) === strtoupper($this->config['scac'] ?? '');
    }

    public function track(string $containerNumber, ?string $carrierScac = null, ?string $bol = null): ?array
    {
        $this->lastError = null;
        $carrierName     = $this->config['name'] ?? $this->config['scac'] ?? 'carrier';

        if (! $this->isConfigured()) {
            $this->lastError = "{$carrierName}: not configured — add credentials to .env";
            return null;
        }

        try {
            $token = $this->resolveAuthToken();

            if ($token === null) {
                $this->lastError = "{$carrierName}: failed to obtain access token";
                return null;
            }

            $baseUrl = rtrim($this->config['base_url'], '/');
            $params  = ['equipmentReference' => $containerNumber];

            if ($bol !== null) {
                $params['transportDocumentReference'] = $bol;
            }

            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders($this->buildAuthHeaders($token))
                ->get("{$baseUrl}/events", $params);

            if (! $response->successful()) {
                $status = $response->status();
                $this->lastError = "{$carrierName}: API returned HTTP {$status}";

                Log::warning('DcsaTrackingProvider: non-successful response', [
                    'carrier'   => $carrierName,
                    'container' => $containerNumber,
                    'status'    => $status,
                    'body'      => $response->body(),
                ]);

                return null;
            }

            $events = $response->json() ?? [];

            if (empty($events)) {
                $this->lastError = "{$carrierName}: no events returned for {$containerNumber}";
                return null;
            }

            return $this->normalizeEvents($containerNumber, $events);

        } catch (\Throwable $e) {
            $this->lastError = "{$carrierName}: request exception — " . $e->getMessage();

            Log::warning('DcsaTrackingProvider: request failed', [
                'carrier'   => $carrierName,
                'container' => $containerNumber,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ----------------------------------------------------------------
    // Auth helpers
    // ----------------------------------------------------------------

    /**
     * Returns the bearer token string for oauth2, the raw api_key for apikey,
     * or null on failure.
     */
    private function resolveAuthToken(): ?string
    {
        $auth = $this->config['auth'] ?? 'apikey';

        if ($auth === 'oauth2') {
            return $this->fetchOauthToken();
        }

        // apikey — token is the key itself
        return $this->config['api_key'] ?? null;
    }

    private function fetchOauthToken(): ?string
    {
        $cacheKey = 'dcsa_oauth_token_' . md5($this->config['client_id'] . $this->config['token_url']);

        return Cache::store('file')->remember($cacheKey, 3300, function () {
            try {
                $response = Http::timeout(8)
                    ->connectTimeout(5)
                    ->asForm()
                    ->post($this->config['token_url'], [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $this->config['client_id'],
                        'client_secret' => $this->config['client_secret'],
                    ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::warning('DcsaTrackingProvider: OAuth token request failed', [
                    'carrier' => $this->config['scac'] ?? 'unknown',
                    'status'  => $response->status(),
                ]);

                return null;
            } catch (\Throwable $e) {
                Log::warning('DcsaTrackingProvider: OAuth token exception', [
                    'carrier' => $this->config['scac'] ?? 'unknown',
                    'error'   => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /** @return array<string,string> */
    private function buildAuthHeaders(string $token): array
    {
        $auth = $this->config['auth'] ?? 'apikey';

        if ($auth === 'oauth2') {
            return [
                'Authorization' => "Bearer {$token}",
                'Accept'        => 'application/json',
            ];
        }

        // apikey carriers vary; send in both common headers for compatibility
        return [
            'Authorization' => "ApiKey {$token}",
            'X-Api-Key'     => $token,
            'Accept'        => 'application/json',
        ];
    }

    // ----------------------------------------------------------------
    // DCSA event normalisation
    // ----------------------------------------------------------------

    /**
     * Map an array of DCSA events to the canonical normalized schema.
     *
     * @param  string $containerNumber
     * @param  array<int,array<string,mixed>> $events
     * @return array<string,mixed>
     */
    private function normalizeEvents(string $containerNumber, array $events): array
    {
        // Sort events ascending by eventDateTime so we process chronologically
        usort($events, static function (array $a, array $b): int {
            return strcmp($a['eventDateTime'] ?? '', $b['eventDateTime'] ?? '');
        });

        $latestEvent        = end($events) ?: [];
        $latestTransport    = null;
        $latestEstArri      = null;
        $latestActArri      = null;
        $latestDeparture    = null;
        $latestLoad         = null;
        $latestDischarge    = null;

        foreach ($events as $ev) {
            $evType  = $ev['eventType'] ?? '';
            $evCode  = $ev['transportEventTypeCode'] ?? $ev['equipmentEventTypeCode'] ?? '';
            $cls     = $ev['eventClassifierCode'] ?? '';

            if ($evType === 'TRANSPORT') {
                $latestTransport = $ev;

                if ($evCode === 'DEPA') {
                    $latestDeparture = $ev;
                }

                if ($evCode === 'ARRI') {
                    if ($cls === 'ACT') {
                        $latestActArri = $ev;
                    } elseif (in_array($cls, ['EST', 'PLN'], true)) {
                        $latestEstArri = $ev;
                    }
                }
            }

            if ($evType === 'EQUIPMENT') {
                if ($evCode === 'LOAD') {
                    $latestLoad = $ev;
                }
                if ($evCode === 'DISC') {
                    $latestDischarge = $ev;
                }
            }
        }

        $currentVessel  = $this->extractVesselName($latestTransport);
        $currentVoyage  = $this->extractVoyage($latestTransport);
        $lastLocation   = $this->extractLocation($latestEvent);
        $eta            = $this->extractDateTime($latestEstArri ?? $latestActArri);
        $atd            = $this->extractDateTime($latestDeparture);
        $loadingPort    = $this->extractLocationCode($latestLoad);
        $dischargePort  = $this->extractLocationCode($latestDischarge);
        $containerStatus = $this->deriveStatus($events);

        $carrierName = $this->config['name'] ?? ($this->config['scac'] ?? '');

        $normalizedEvents = array_map([$this, 'normalizeEventEntry'], $events);

        return [
            'container_id'              => $containerNumber,
            'container_type'            => null,
            'container_status'          => $containerStatus,
            'shipping_line_name'        => $carrierName,
            'shipping_line_id'          => $this->config['scac'] ?? null,
            'shipped_from'              => $loadingPort,
            'shipped_to'                => $dischargePort,
            'loading_port'              => $loadingPort,
            'discharging_port'          => $dischargePort,
            'atd_origin'                => $atd,
            'eta_final_destination'     => $eta,
            'last_location'             => $lastLocation,
            'next_location'             => null,
            'last_vessel_name'          => $currentVessel,
            'current_vessel_name'       => $currentVessel,
            'current_voyage_number'     => $currentVoyage,
            'bill_of_lading'            => null,
            'last_updated'              => $this->extractDateTime($latestEvent),
            'source'                    => $this->config['scac']
                ? strtolower($this->config['scac'])
                : 'dcsa',
            'events'                    => $normalizedEvents,
        ];
    }

    /** @param array<string,mixed> $event */
    private function extractVesselName(?array $event): ?string
    {
        if ($event === null) {
            return null;
        }

        return $event['transportCall']['vessel']['vesselName']
            ?? $event['transportCall']['vesselName']
            ?? null;
    }

    /** @param array<string,mixed> $event */
    private function extractVoyage(?array $event): ?string
    {
        if ($event === null) {
            return null;
        }

        return $event['transportCall']['carrierVoyageNumber']
            ?? $event['transportCall']['exportVoyageNumber']
            ?? null;
    }

    /** @param array<string,mixed> $event */
    private function extractLocation(?array $event): ?string
    {
        if ($event === null) {
            return null;
        }

        // DCSA locations can be inside transportCall or at the event root
        $loc = $event['eventLocation']
            ?? $event['transportCall']['location']
            ?? null;

        if ($loc === null) {
            return null;
        }

        return $loc['locationName']
            ?? $loc['UNLocationCode']
            ?? null;
    }

    /** @param array<string,mixed> $event */
    private function extractLocationCode(?array $event): ?string
    {
        if ($event === null) {
            return null;
        }

        $loc = $event['eventLocation']
            ?? $event['transportCall']['location']
            ?? null;

        if ($loc === null) {
            return null;
        }

        return $loc['UNLocationCode']
            ?? $loc['locationName']
            ?? null;
    }

    /** @param array<string,mixed>|false $event */
    private function extractDateTime($event): ?string
    {
        if (! $event) {
            return null;
        }

        return $event['eventDateTime'] ?? $event['eventCreatedDateTime'] ?? null;
    }

    /**
     * Derive a human-readable container status from the latest significant event.
     *
     * @param array<int,array<string,mixed>> $events  Chronologically sorted
     */
    private function deriveStatus(array $events): string
    {
        // Walk backwards for the most recent meaningful event
        foreach (array_reverse($events) as $ev) {
            $evType = $ev['eventType'] ?? '';
            $evCode = $ev['transportEventTypeCode'] ?? $ev['equipmentEventTypeCode'] ?? '';
            $cls    = $ev['eventClassifierCode'] ?? '';

            // Actual events trump estimated ones for status derivation
            if ($cls === 'PLN') {
                continue;
            }

            if ($evType === 'EQUIPMENT') {
                $status = match ($evCode) {
                    'GTIN' => 'AT_ORIGIN',
                    'LOAD' => 'LOADED_ON_VESSEL',
                    'DISC' => 'AT_OCEAN_TERMINAL',
                    'GTOT' => 'OUT_FOR_DELIVERY',
                    default => null,
                };
                if ($status !== null) {
                    return $status;
                }
            }

            if ($evType === 'TRANSPORT') {
                $status = match ($evCode) {
                    'DEPA' => ($cls === 'ACT') ? 'ON_WATER' : 'AT_ORIGIN',
                    'ARRI' => ($cls === 'ACT') ? 'AWAITING_DISCHARGE' : 'ON_WATER',
                    default => null,
                };
                if ($status !== null) {
                    return $status;
                }
            }
        }

        return 'IN_TRANSIT';
    }

    /**
     * Map a raw DCSA event to a flat, serialisable shape.
     *
     * @param  array<string,mixed> $ev
     * @return array<string,mixed>
     */
    private function normalizeEventEntry(array $ev): array
    {
        return [
            'eventType'      => $ev['eventType'] ?? null,
            'eventTypeCode'  => $ev['transportEventTypeCode'] ?? $ev['equipmentEventTypeCode'] ?? $ev['shipmentEventTypeCode'] ?? null,
            'classifier'     => $ev['eventClassifierCode'] ?? null,
            'eventDateTime'  => $ev['eventDateTime'] ?? null,
            'location'       => $this->extractLocation($ev),
            'vesselName'     => $this->extractVesselName($ev),
            'voyage'         => $this->extractVoyage($ev),
            'raw'            => $ev,
        ];
    }
}
