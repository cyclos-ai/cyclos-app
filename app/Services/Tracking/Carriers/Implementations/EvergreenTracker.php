<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * Evergreen Marine Corporation
 * Handles: EGLV, EGHU
 *
 * Real endpoint base: https://www.evergreen-line.com/api/cargo-tracking
 * Auth: Ocp-Apim-Subscription-Key header
 */
class EvergreenTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.evergreen.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.evergreen.api_url', 'https://www.evergreen-line.com/api');
    }

    protected function defaultHeaders(): array
    {
        return [
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
            'Accept'                     => 'application/json',
        ];
    }

    public function getCarrierScac(): string
    {
        return 'EGLV';
    }

    public function getCarrierName(): string
    {
        return 'Evergreen Marine';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/cargo-tracking/bl', [
            'bl_no' => $mblNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Evergreen API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/cargo-tracking/container', [
            'container_no' => $containerNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Evergreen API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/cargo-tracking/booking', [
            'booking_no' => $bookingNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Evergreen API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $info     = $data['trackingInfo'] ?? $data;
        $schedule = $info['schedule'] ?? [];
        $pol      = $schedule[0] ?? [];
        $pod      = !empty($schedule) ? $schedule[count($schedule) - 1] : [];

        $events = [];
        foreach ($info['containerMoves'] ?? $info['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['activityCd'] ?? $rawEvent['eventType'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['activityDt'] ?? $rawEvent['eventDate'] ?? '') ?? '',
                location:    $rawEvent['portName'] ?? $rawEvent['location'] ?? null,
                locode:      $rawEvent['portCd'] ?? $rawEvent['locode'] ?? null,
                vessel:      $rawEvent['vslName'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voy'] ?? $rawEvent['voyage'] ?? null,
                description: $rawEvent['activityDesc'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $info['ctnrNo'] ?? null,
            'mbl_number'              => $info['blNo'] ?? null,
            'status'                  => $this->normalizeStatus($info['ctnrStatus'] ?? ''),
            'vessel_name'             => $pol['vslName'] ?? null,
            'voyage_number'           => $pol['voy'] ?? null,
            'pol'                     => $pol['portCd'] ?? null,
            'pod'                     => $pod['portCd'] ?? null,
            'eta'                     => $this->parseDate($pod['eta'] ?? null),
            'ata'                     => $this->parseDate($pod['ata'] ?? null),
            'etd'                     => $this->parseDate($pol['etd'] ?? null),
            'atd'                     => $this->parseDate($pol['atd'] ?? null),
            'current_location'        => $info['curLocName'] ?? null,
            'current_location_locode' => $info['curLocCd'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
