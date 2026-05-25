<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * MSC - Mediterranean Shipping Company
 * Handles: MSCU, MEDU
 *
 * Real endpoint base: https://www.msc.com/api/feature/tools/TrackingInfo
 * Auth: X-API-Key header
 */
class MSCTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.msc.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.msc.api_url', 'https://www.msc.com/api');
    }

    protected function defaultHeaders(): array
    {
        return [
            'X-API-Key'    => $this->apiKey,
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function getCarrierScac(): string
    {
        return 'MSCU';
    }

    public function getCarrierName(): string
    {
        return 'MSC - Mediterranean Shipping Company';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/feature/tools/TrackingInfo', [
            'trackingNumber' => $mblNumber,
            'searchType'     => 'BL',
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('MSC API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/feature/tools/TrackingInfo', [
            'trackingNumber' => $containerNumber,
            'searchType'     => 'CONTAINER',
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('MSC API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/feature/tools/TrackingInfo', [
            'trackingNumber' => $bookingNumber,
            'searchType'     => 'BOOKING',
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('MSC API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $result  = $data['result'] ?? $data;
        $summary = $result['trackingInfo'] ?? $result;

        $events = [];
        foreach ($result['movements'] ?? $result['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['moveType'] ?? $rawEvent['eventType'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['moveDateTime'] ?? $rawEvent['eventDate'] ?? '') ?? '',
                location:    $rawEvent['portName'] ?? $rawEvent['location'] ?? null,
                locode:      $rawEvent['portCode'] ?? $rawEvent['locode'] ?? null,
                vessel:      $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voyage'] ?? $rawEvent['voyageNo'] ?? null,
                description: $rawEvent['moveDescription'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $summary['containerNumber'] ?? null,
            'mbl_number'              => $summary['billOfLading'] ?? null,
            'status'                  => $this->normalizeStatus($summary['containerStatus'] ?? ''),
            'vessel_name'             => $summary['vesselName'] ?? null,
            'voyage_number'           => $summary['voyage'] ?? null,
            'pol'                     => $summary['polCode'] ?? null,
            'pod'                     => $summary['podCode'] ?? null,
            'eta'                     => $this->parseDate($summary['eta'] ?? null),
            'ata'                     => $this->parseDate($summary['ata'] ?? null),
            'etd'                     => $this->parseDate($summary['etd'] ?? null),
            'atd'                     => $this->parseDate($summary['atd'] ?? null),
            'current_location'        => $summary['currentPort'] ?? null,
            'current_location_locode' => $summary['currentPortCode'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
