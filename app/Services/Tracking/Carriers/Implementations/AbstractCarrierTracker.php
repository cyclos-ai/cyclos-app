<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Domain\Container\Enums\ContainerEventType;
use App\Services\Tracking\Carriers\CarrierTrackingInterface;
use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

abstract class AbstractCarrierTracker implements CarrierTrackingInterface
{
    protected Client  $httpClient;
    protected string  $apiKey;
    protected string  $baseUrl;

    public function __construct()
    {
        $this->apiKey  = $this->resolveApiKey();
        $this->baseUrl = $this->resolveBaseUrl();

        $this->httpClient = new Client([
            'base_uri'        => $this->baseUrl,
            'timeout'         => 30,
            'connect_timeout' => 10,
            'headers'         => $this->defaultHeaders(),
        ]);
    }

    /**
     * Inject per-tenant credentials, replacing the global config values.
     */
    public function setCredentials(\App\Models\Tenant\CarrierApiCredential $credential): void
    {
        $this->apiKey  = $credential->api_key ?? $credential->consumer_key ?? $credential->access_token ?? $this->apiKey;
        $this->baseUrl = $credential->getEffectiveApiUrl() ?: $this->baseUrl;

        // Rebuild HTTP client with new credentials
        $this->httpClient = new \GuzzleHttp\Client([
            'base_uri'        => $this->baseUrl,
            'timeout'         => 30,
            'connect_timeout' => 10,
            'headers'         => $this->defaultHeaders(),
        ]);
    }

    // ----------------------------------------------------------------
    // Abstract – carriers must supply these
    // ----------------------------------------------------------------

    abstract protected function resolveApiKey(): string;
    abstract protected function resolveBaseUrl(): string;
    abstract protected function defaultHeaders(): array;

    // ----------------------------------------------------------------
    // CarrierTrackingInterface – default implementations
    // ----------------------------------------------------------------

    public function supportsTracking(): bool
    {
        return true;
    }

    public function getVesselSchedule(string $vesselName, ?string $voyage = null): array
    {
        // Default: not implemented by most carriers in stub mode
        return [];
    }

    // ----------------------------------------------------------------
    // Shared HTTP helpers
    // ----------------------------------------------------------------

    /**
     * Perform a GET request and return decoded JSON body, or null on error.
     */
    protected function makeRequest(string $uri, array $queryParams = []): ?array
    {
        try {
            $response = $this->httpClient->get($uri, ['query' => $queryParams]);
            $body     = (string) $response->getBody();

            return json_decode($body, true) ?? [];

        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            Log::warning("{$this->getCarrierScac()} carrier API client error", [
                'status' => $status,
                'uri'    => $uri,
                'body'   => (string) $e->getResponse()->getBody(),
            ]);
            return null;

        } catch (ConnectException $e) {
            Log::error("{$this->getCarrierScac()} carrier API connection failed", [
                'uri'   => $uri,
                'error' => $e->getMessage(),
            ]);
            return null;

        } catch (RequestException $e) {
            Log::error("{$this->getCarrierScac()} carrier API request error", [
                'uri'   => $uri,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Perform a POST request and return decoded JSON body, or null on error.
     */
    protected function makePostRequest(string $uri, array $payload = []): ?array
    {
        try {
            $response = $this->httpClient->post($uri, ['json' => $payload]);
            $body     = (string) $response->getBody();

            return json_decode($body, true) ?? [];

        } catch (ClientException $e) {
            Log::warning("{$this->getCarrierScac()} carrier API POST client error", [
                'status' => $e->getResponse()->getStatusCode(),
                'uri'    => $uri,
            ]);
            return null;

        } catch (RequestException $e) {
            Log::error("{$this->getCarrierScac()} carrier API POST request error", [
                'uri'   => $uri,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ----------------------------------------------------------------
    // Date / status normalisation helpers
    // ----------------------------------------------------------------

    /**
     * Parse a carrier date string into ISO-8601 format, returning null on failure.
     */
    protected function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        // Already ISO-8601
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateStr)) {
            return $dateStr;
        }

        // Try common formats
        $formats = [
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i',
            'm/d/Y H:i:s',
            'Ymd\THis\Z',
            'Ymd',
        ];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $dateStr, new \DateTimeZone('UTC'));
            if ($dt !== false) {
                return $dt->format('Y-m-d\TH:i:s\Z');
            }
        }

        // Last resort: strtotime
        $ts = strtotime($dateStr);
        if ($ts !== false) {
            return gmdate('Y-m-d\TH:i:s\Z', $ts);
        }

        return $dateStr; // return raw if we cannot parse
    }

    /**
     * Normalise a carrier-specific status string into a ContainerStatus value string.
     */
    protected function normalizeStatus(string $rawStatus): string
    {
        $map = [
            // Generic terms
            'gate in'             => 'AT_ORIGIN',
            'gate_in'             => 'AT_ORIGIN',
            'loaded'              => 'LOADED_ON_VESSEL',
            'load'                => 'LOADED_ON_VESSEL',
            'vessel departure'    => 'ON_WATER',
            'departed'            => 'ON_WATER',
            'on vessel'           => 'ON_WATER',
            'at sea'              => 'ON_WATER',
            'vessel arrival'      => 'AWAITING_DISCHARGE',
            'arrived'             => 'AWAITING_DISCHARGE',
            'discharge'           => 'AT_OCEAN_TERMINAL',
            'discharged'          => 'AT_OCEAN_TERMINAL',
            'gate out'            => 'AT_OCEAN_TERMINAL',
            'rail departure'      => 'ON_RAIL',
            'rail arrival'        => 'ARRIVED_AT_RAIL_TERMINAL',
            'out for delivery'    => 'OUT_FOR_DELIVERY',
            'delivered'           => 'OUT_FOR_DELIVERY',
            'empty return'        => 'EMPTY_RETURNED',
            'empty returned'      => 'EMPTY_RETURNED',
        ];

        $lower = strtolower(trim($rawStatus));

        foreach ($map as $pattern => $status) {
            if (str_contains($lower, $pattern)) {
                return $status;
            }
        }

        return 'NOT_TRACKING';
    }

    /**
     * Map a carrier-specific event code/description to a ContainerEventType value.
     */
    protected function mapEventType(string $rawEventCode): string
    {
        $map = [
            'GATE-IN'             => ContainerEventType::GATE_IN->value,
            'GATE_IN'             => ContainerEventType::GATE_IN->value,
            'LOAD'                => ContainerEventType::LOADED->value,
            'LOADED'              => ContainerEventType::LOADED->value,
            'VESSEL-DEPARTURE'    => ContainerEventType::VESSEL_DEPARTURE->value,
            'VESSEL_DEPARTURE'    => ContainerEventType::VESSEL_DEPARTURE->value,
            'DEPART'              => ContainerEventType::DEPARTED->value,
            'DEPARTED'            => ContainerEventType::DEPARTED->value,
            'VESSEL-ARRIVAL'      => ContainerEventType::VESSEL_ARRIVAL->value,
            'VESSEL_ARRIVAL'      => ContainerEventType::VESSEL_ARRIVAL->value,
            'ARRIVE'              => ContainerEventType::ARRIVED->value,
            'ARRIVED'             => ContainerEventType::ARRIVED->value,
            'DISCHARGE'           => ContainerEventType::DISCHARGED->value,
            'DISCHARGED'          => ContainerEventType::DISCHARGED->value,
            'GATE-OUT'            => ContainerEventType::GATE_OUT->value,
            'GATE_OUT'            => ContainerEventType::GATE_OUT->value,
            'TRANSSHIP'           => ContainerEventType::TRANSSHIPMENT->value,
            'TRANSSHIPMENT'       => ContainerEventType::TRANSSHIPMENT->value,
            'RAIL-DEPARTURE'      => ContainerEventType::RAIL_DEPARTURE->value,
            'RAIL_DEPARTURE'      => ContainerEventType::RAIL_DEPARTURE->value,
            'RAIL-ARRIVAL'        => ContainerEventType::RAIL_ARRIVAL->value,
            'RAIL_ARRIVAL'        => ContainerEventType::RAIL_ARRIVAL->value,
            'OUT-FOR-DELIVERY'    => ContainerEventType::OUT_FOR_DELIVERY->value,
            'OUT_FOR_DELIVERY'    => ContainerEventType::OUT_FOR_DELIVERY->value,
            'DELIVERED'           => ContainerEventType::DELIVERED->value,
            'DELIVERY'            => ContainerEventType::DELIVERED->value,
            'EMPTY-RETURN'        => ContainerEventType::EMPTY_RETURN->value,
            'EMPTY_RETURN'        => ContainerEventType::EMPTY_RETURN->value,
            'CUSTOMS-HOLD'        => ContainerEventType::CUSTOMS_HOLD->value,
            'CUSTOMS_HOLD'        => ContainerEventType::CUSTOMS_HOLD->value,
            'CUSTOMS-RELEASE'     => ContainerEventType::CUSTOMS_RELEASE->value,
            'CUSTOMS_RELEASE'     => ContainerEventType::CUSTOMS_RELEASE->value,
        ];

        $upper = strtoupper(trim($rawEventCode));

        return $map[$upper] ?? ContainerEventType::MANUAL_UPDATE->value;
    }

    /**
     * Build a CarrierTrackingEvent from a normalised array.
     */
    protected function buildEvent(array $data): CarrierTrackingEvent
    {
        return new CarrierTrackingEvent(
            eventType:   $this->mapEventType($data['event_type'] ?? $data['event_code'] ?? ''),
            eventDate:   $this->parseDate($data['event_date'] ?? $data['date'] ?? '') ?? '',
            location:    $data['location'] ?? $data['location_name'] ?? null,
            locode:      $data['locode'] ?? $data['un_locode'] ?? null,
            vessel:      $data['vessel'] ?? $data['vessel_name'] ?? null,
            voyage:      $data['voyage'] ?? $data['voyage_number'] ?? null,
            description: $data['description'] ?? $data['event_description'] ?? null,
        );
    }

    /**
     * Build a stub/simulated tracking response for development / API-not-yet-integrated mode.
     */
    protected function buildStubResponse(string $reference, string $refType = 'container'): CarrierTrackingResponse
    {
        $now    = now();
        $etd    = $now->copy()->subDays(14)->format('Y-m-d\TH:i:s\Z');
        $atd    = $now->copy()->subDays(13)->format('Y-m-d\TH:i:s\Z');
        $eta    = $now->copy()->addDays(7)->format('Y-m-d\TH:i:s\Z');

        $events = [
            new CarrierTrackingEvent(
                eventType:   ContainerEventType::GATE_IN->value,
                eventDate:   $now->copy()->subDays(16)->format('Y-m-d\TH:i:s\Z'),
                location:    'Shanghai, China',
                locode:      'CNSHA',
                vessel:      null,
                voyage:      null,
                description: 'Container received at origin gate',
            ),
            new CarrierTrackingEvent(
                eventType:   ContainerEventType::LOADED->value,
                eventDate:   $now->copy()->subDays(14)->format('Y-m-d\TH:i:s\Z'),
                location:    'Shanghai, China',
                locode:      'CNSHA',
                vessel:      'STUB VESSEL',
                voyage:      'V001E',
                description: 'Container loaded on vessel',
            ),
            new CarrierTrackingEvent(
                eventType:   ContainerEventType::VESSEL_DEPARTURE->value,
                eventDate:   $atd,
                location:    'Shanghai, China',
                locode:      'CNSHA',
                vessel:      'STUB VESSEL',
                voyage:      'V001E',
                description: 'Vessel departed port of loading',
            ),
        ];

        return CarrierTrackingResponse::success([
            'container_number'        => $refType === 'container' ? $reference : null,
            'mbl_number'              => $refType === 'mbl'       ? $reference : null,
            'status'                  => 'ON_WATER',
            'vessel_name'             => 'STUB VESSEL',
            'voyage_number'           => 'V001E',
            'pol'                     => 'CNSHA',
            'pod'                     => 'USLAX',
            'eta'                     => $eta,
            'ata'                     => null,
            'etd'                     => $etd,
            'atd'                     => $atd,
            'current_location'        => 'Pacific Ocean',
            'current_location_locode' => null,
            'events'                  => $events,
            'raw_data'                => ['stub' => true, 'carrier' => $this->getCarrierScac()],
        ]);
    }
}
