<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * CMA CGM Group
 * Handles: CMDU, ANNU (ANL), APLU (APL)
 *
 * Real endpoint base: https://api.cma-cgm.com/booking/shipment/v2
 * Auth: Authorization: Bearer {token} (OAuth2 client credentials)
 */
class CMACGMTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.cma_cgm.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.cma_cgm.api_url', 'https://api.cma-cgm.com');
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
        return 'CMDU';
    }

    public function getCarrierName(): string
    {
        return 'CMA CGM';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/booking/shipment/v2/track', [
            'billOfLadingNumber' => $mblNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('CMA CGM API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/booking/shipment/v2/track', [
            'equipmentNumber' => $containerNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('CMA CGM API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/booking/shipment/v2/track', [
            'bookingReference' => $bookingNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('CMA CGM API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $shipment = $data['shipment'] ?? $data;
        $routing  = $shipment['routing'] ?? [];
        $segments = $routing['transportSegments'] ?? [];
        $firstSeg = $segments[0] ?? [];
        $lastSeg  = !empty($segments) ? $segments[count($segments) - 1] : [];

        $events = [];
        foreach ($shipment['events'] ?? $data['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['eventTypeCode'] ?? $rawEvent['eventType'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['eventDateTime'] ?? $rawEvent['localDate'] ?? '') ?? '',
                location:    $rawEvent['location']['locationName'] ?? $rawEvent['portName'] ?? null,
                locode:      $rawEvent['location']['UNLocationCode'] ?? $rawEvent['portCode'] ?? null,
                vessel:      $rawEvent['transport']['vesselName'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['transport']['exportVoyageNumber'] ?? $rawEvent['voyageNumber'] ?? null,
                description: $rawEvent['eventDescription'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $shipment['equipmentNumber'] ?? null,
            'mbl_number'              => $shipment['billOfLadingNumber'] ?? null,
            'status'                  => $this->normalizeStatus($shipment['transportStatus'] ?? ''),
            'vessel_name'             => $firstSeg['vessel']['vesselName'] ?? null,
            'voyage_number'           => $firstSeg['exportVoyageNumber'] ?? null,
            'pol'                     => $firstSeg['loadLocation']['UNLocationCode'] ?? null,
            'pod'                     => $lastSeg['dischargeLocation']['UNLocationCode'] ?? null,
            'eta'                     => $this->parseDate($lastSeg['estimatedArrivalDate'] ?? null),
            'ata'                     => $this->parseDate($lastSeg['actualArrivalDate'] ?? null),
            'etd'                     => $this->parseDate($firstSeg['estimatedDepartureDate'] ?? null),
            'atd'                     => $this->parseDate($firstSeg['actualDepartureDate'] ?? null),
            'current_location'        => $shipment['currentLocation']['locationName'] ?? null,
            'current_location_locode' => $shipment['currentLocation']['UNLocationCode'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
