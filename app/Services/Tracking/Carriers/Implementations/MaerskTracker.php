<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * Maersk Track & Trace API v2
 * Handles group: MAEU, SUDU, SAFI, CCNI, MAEI, MSKU, SEAU, SEJJ
 *
 * Real endpoints:
 *   GET /track-and-trace/container?containerNumber={number}
 *   GET /track-and-trace/events?containerNumber={number}
 *
 * Auth: Consumer-Key header (API gateway) + optional OAuth2 for advanced endpoints
 */
class MaerskTracker extends AbstractCarrierTracker
{
    // Maersk-specific event type codes → standard mapping
    private const EVENT_MAP = [
        'GATE-IN'              => 'GATE_IN',
        'LOAD'                 => 'LOADED',
        'VESSEL-DEPARTURE'     => 'VESSEL_DEPARTURE',
        'VESSEL-ARRIVAL'       => 'VESSEL_ARRIVAL',
        'DISCHARGE'            => 'DISCHARGED',
        'TRANSSHIP'            => 'TRANSSHIPMENT',
        'GATE-OUT'             => 'GATE_OUT',
        'RAIL-DEPARTURE'       => 'RAIL_DEPARTURE',
        'RAIL-ARRIVAL'         => 'RAIL_ARRIVAL',
        'PICKUP'               => 'OUT_FOR_DELIVERY',
        'DELIVERED'            => 'DELIVERED',
        'EMPTY-RETURN'         => 'EMPTY_RETURN',
        'CUSTOMS-HOLD'         => 'CUSTOMS_HOLD',
        'CUSTOMS-RELEASE'      => 'CUSTOMS_RELEASE',
    ];

    protected function resolveApiKey(): string
    {
        return config('carriers.maersk.consumer_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.maersk.api_url', 'https://api.maersk.com');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Consumer-Key' => $this->apiKey,
            'Accept'       => 'application/json',
        ];
    }

    public function getCarrierScac(): string
    {
        return 'MAEU';
    }

    public function getCarrierName(): string
    {
        return 'Maersk';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        // Real: GET /track-and-trace/container?BillOfLadingNumber={mbl}
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/track-and-trace/container', [
            'BillOfLadingNumber' => $mblNumber,
        ]);

        if ($data === null) {
            return CarrierTrackingResponse::failure('Maersk API unavailable');
        }

        return $this->parseResponse($data);
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/track-and-trace/container', [
            'containerNumber' => $containerNumber,
        ]);

        if ($data === null) {
            return CarrierTrackingResponse::failure('Maersk API unavailable');
        }

        return $this->parseResponse($data);
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/track-and-trace/container', [
            'BookingReference' => $bookingNumber,
        ]);

        if ($data === null) {
            return CarrierTrackingResponse::failure('Maersk API unavailable');
        }

        return $this->parseResponse($data);
    }

    public function getVesselSchedule(string $vesselName, ?string $voyage = null): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        $params = ['vesselName' => $vesselName];
        if ($voyage !== null) {
            $params['voyage'] = $voyage;
        }

        return $this->makeRequest('/vessel-schedules', $params) ?? [];
    }

    // ----------------------------------------------------------------
    // Private
    // ----------------------------------------------------------------

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        // Maersk v2 response structure
        $containers = $data['containers'] ?? [$data];
        $container  = $containers[0] ?? [];
        $transport  = $container['transportPlan'] ?? [];
        $firstLeg   = $transport[0] ?? [];
        $lastLeg    = end($transport) ?: [];

        $events = [];
        foreach ($container['events'] ?? [] as $rawEvent) {
            $eventCode = strtoupper($rawEvent['eventType'] ?? $rawEvent['activityName'] ?? '');
            $events[]  = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($eventCode),
                eventDate:   $this->parseDate($rawEvent['eventDateTime'] ?? $rawEvent['eventDate'] ?? '') ?? '',
                location:    $rawEvent['location']['cityName'] ?? $rawEvent['portName'] ?? null,
                locode:      $rawEvent['location']['UNLocationCode'] ?? $rawEvent['portCode'] ?? null,
                vessel:      $rawEvent['vessel']['vesselName'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voyage']['carrierVoyageNumber'] ?? $rawEvent['voyageNumber'] ?? null,
                description: $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $container['containerNumber'] ?? null,
            'mbl_number'              => $container['billOfLadingNumber'] ?? null,
            'status'                  => $this->normalizeStatus($container['containerStatus'] ?? ''),
            'vessel_name'             => $firstLeg['vessel']['vesselName'] ?? null,
            'voyage_number'           => $firstLeg['voyage']['carrierVoyageNumber'] ?? null,
            'pol'                     => $firstLeg['portOfLoad']['UNLocationCode'] ?? null,
            'pod'                     => $lastLeg['portOfDischarge']['UNLocationCode'] ?? null,
            'eta'                     => $this->parseDate($lastLeg['estimatedTimeOfArrival'] ?? null),
            'ata'                     => $this->parseDate($lastLeg['actualTimeOfArrival'] ?? null),
            'etd'                     => $this->parseDate($firstLeg['estimatedTimeOfDeparture'] ?? null),
            'atd'                     => $this->parseDate($firstLeg['actualTimeOfDeparture'] ?? null),
            'current_location'        => $container['currentLocation']['cityName'] ?? null,
            'current_location_locode' => $container['currentLocation']['UNLocationCode'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
