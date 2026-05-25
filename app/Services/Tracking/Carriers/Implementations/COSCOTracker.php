<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * COSCO Shipping Group
 * Handles: COSU, CCLU (COSCO Container Lines), OOLU (OOCL)
 *
 * Real endpoint base: https://elines.coscoshipping.com/ebusiness/cargoTracking
 * Auth: Authorization header (API token)
 */
class COSCOTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.cosco.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.cosco.api_url', 'https://elines.coscoshipping.com');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ];
    }

    public function getCarrierScac(): string
    {
        return 'COSU';
    }

    public function getCarrierName(): string
    {
        return 'COSCO Shipping';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/ebusiness/cargoTracking', [
            'bl_no' => $mblNumber,
            'type'  => 'BL',
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('COSCO API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/ebusiness/cargoTracking', [
            'container_no' => $containerNumber,
            'type'         => 'CONTAINER',
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('COSCO API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/ebusiness/cargoTracking', [
            'booking_no' => $bookingNumber,
            'type'       => 'BOOKING',
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('COSCO API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $result    = $data['data'] ?? $data;
        $shipInfo  = $result['shipInfo'] ?? $result;
        $legs      = $result['transitPorts'] ?? [];
        $firstLeg  = $legs[0] ?? [];
        $lastLeg   = !empty($legs) ? $legs[count($legs) - 1] : [];

        $events = [];
        foreach ($result['containerTrackingList'] ?? $result['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['moveType'] ?? $rawEvent['event_type'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['moveTime'] ?? $rawEvent['event_time'] ?? '') ?? '',
                location:    $rawEvent['portName'] ?? $rawEvent['location'] ?? null,
                locode:      $rawEvent['portCode'] ?? null,
                vessel:      $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voyage'] ?? null,
                description: $rawEvent['moveDesc'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $shipInfo['containerNo'] ?? null,
            'mbl_number'              => $shipInfo['blNo'] ?? null,
            'status'                  => $this->normalizeStatus($shipInfo['status'] ?? ''),
            'vessel_name'             => $shipInfo['vesselName'] ?? $firstLeg['vesselName'] ?? null,
            'voyage_number'           => $shipInfo['voyage'] ?? $firstLeg['voyage'] ?? null,
            'pol'                     => $shipInfo['pol'] ?? $firstLeg['portCode'] ?? null,
            'pod'                     => $shipInfo['pod'] ?? $lastLeg['portCode'] ?? null,
            'eta'                     => $this->parseDate($shipInfo['eta'] ?? $lastLeg['eta'] ?? null),
            'ata'                     => $this->parseDate($shipInfo['ata'] ?? null),
            'etd'                     => $this->parseDate($shipInfo['etd'] ?? $firstLeg['etd'] ?? null),
            'atd'                     => $this->parseDate($shipInfo['atd'] ?? null),
            'current_location'        => $shipInfo['currentPort'] ?? null,
            'current_location_locode' => $shipInfo['currentPortCode'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
