<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * ZIM Integrated Shipping Services
 * Handles: ZIMU, ZLCU
 *
 * Real endpoint base: https://www.zim.com/api/tracking
 * Auth: Authorization: Bearer {token}
 */
class ZIMTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.zim.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.zim.api_url', 'https://www.zim.com/api');
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
        return 'ZIMU';
    }

    public function getCarrierName(): string
    {
        return 'ZIM Integrated Shipping';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/tracking/bl/' . urlencode($mblNumber));

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('ZIM API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/tracking/container/' . urlencode($containerNumber));

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('ZIM API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/tracking/booking/' . urlencode($bookingNumber));

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('ZIM API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $tracking  = $data['tracking'] ?? $data;
        $container = $tracking['container'] ?? $tracking;
        $voyage    = $tracking['voyage'] ?? [];
        $legs      = $voyage['legs'] ?? [];
        $firstLeg  = $legs[0] ?? [];
        $lastLeg   = !empty($legs) ? $legs[count($legs) - 1] : [];

        $events = [];
        foreach ($tracking['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['eventType'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['eventDate'] ?? $rawEvent['actualDate'] ?? '') ?? '',
                location:    $rawEvent['portName'] ?? $rawEvent['location'] ?? null,
                locode:      $rawEvent['portCode'] ?? $rawEvent['unlocode'] ?? null,
                vessel:      $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voyageNumber'] ?? null,
                description: $rawEvent['eventDescription'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $container['number'] ?? null,
            'mbl_number'              => $tracking['blNumber'] ?? null,
            'status'                  => $this->normalizeStatus($container['status'] ?? ''),
            'vessel_name'             => $firstLeg['vesselName'] ?? null,
            'voyage_number'           => $firstLeg['voyageNumber'] ?? null,
            'pol'                     => $firstLeg['portOfLoad'] ?? null,
            'pod'                     => $lastLeg['portOfDischarge'] ?? null,
            'eta'                     => $this->parseDate($lastLeg['eta'] ?? null),
            'ata'                     => $this->parseDate($lastLeg['ata'] ?? null),
            'etd'                     => $this->parseDate($firstLeg['etd'] ?? null),
            'atd'                     => $this->parseDate($firstLeg['atd'] ?? null),
            'current_location'        => $container['currentLocation'] ?? null,
            'current_location_locode' => $container['currentLocationCode'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
