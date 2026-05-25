<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * Hapag-Lloyd
 * Handles: HLCU, HLXU
 *
 * Real endpoint base: https://api.hlag.com/track-and-trace
 * Auth: OAuth2 client credentials (client_id + client_secret → Bearer token)
 */
class HapagLloydTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.hapag_lloyd.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.hapag_lloyd.api_url', 'https://api.hlag.com');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ];
    }

    public function getCarrierScac(): string
    {
        return 'HLCU';
    }

    public function getCarrierName(): string
    {
        return 'Hapag-Lloyd';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/track-and-trace/tracking', [
            'referenceType'  => 'BL',
            'referenceValue' => $mblNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Hapag-Lloyd API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/track-and-trace/tracking', [
            'referenceType'  => 'CONTAINER',
            'referenceValue' => $containerNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Hapag-Lloyd API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/track-and-trace/tracking', [
            'referenceType'  => 'BOOKING',
            'referenceValue' => $bookingNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Hapag-Lloyd API unavailable');
    }

    public function getVesselSchedule(string $vesselName, ?string $voyage = null): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        $params = ['vesselName' => $vesselName];
        if ($voyage !== null) {
            $params['voyageNumber'] = $voyage;
        }

        return $this->makeRequest('/schedules/vessel-schedule', $params) ?? [];
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        // Hapag-Lloyd Track & Trace API v1 structure
        $shipment  = $data['shipment'] ?? $data;
        $container = ($shipment['containers'] ?? [$shipment])[0] ?? [];
        $legs      = $shipment['transportLegs'] ?? [];
        $firstLeg  = $legs[0] ?? [];
        $lastLeg   = !empty($legs) ? $legs[count($legs) - 1] : [];

        $events = [];
        foreach ($container['events'] ?? $shipment['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['eventTypeCode'] ?? $rawEvent['eventType'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['eventDateTime'] ?? $rawEvent['plannedDate'] ?? '') ?? '',
                location:    $rawEvent['location']['locationName'] ?? $rawEvent['portName'] ?? null,
                locode:      $rawEvent['location']['UNLocationCode'] ?? $rawEvent['unlocode'] ?? null,
                vessel:      $rawEvent['vessel']['vesselName'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['transport']['exportVoyageNumber'] ?? $rawEvent['voyage'] ?? null,
                description: $rawEvent['eventTypeDescription'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $container['containerNumber'] ?? null,
            'mbl_number'              => $shipment['billOfLadingNumber'] ?? null,
            'status'                  => $this->normalizeStatus($container['containerStatus'] ?? ''),
            'vessel_name'             => $firstLeg['vessel']['vesselName'] ?? null,
            'voyage_number'           => $firstLeg['transport']['exportVoyageNumber'] ?? null,
            'pol'                     => $firstLeg['loadLocation']['UNLocationCode'] ?? null,
            'pod'                     => $lastLeg['dischargeLocation']['UNLocationCode'] ?? null,
            'eta'                     => $this->parseDate($lastLeg['plannedDeparture'] ?? null),
            'ata'                     => $this->parseDate($lastLeg['actualArrival'] ?? null),
            'etd'                     => $this->parseDate($firstLeg['plannedDeparture'] ?? null),
            'atd'                     => $this->parseDate($firstLeg['actualDeparture'] ?? null),
            'current_location'        => $container['currentLocation']['locationName'] ?? null,
            'current_location_locode' => $container['currentLocation']['UNLocationCode'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
