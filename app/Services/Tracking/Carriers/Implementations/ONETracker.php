<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * ONE - Ocean Network Express (merger of K Line, NYK, MOL)
 * Handles: ONEY, ONEU, KKLU ("K" Line), NYKU (NYK), MOLU (MOL)
 *
 * Real endpoint base: https://ecomm.one-line.com/ecom/CUP_HOM_3301GS.do
 * Auth: API key header (X-API-Key) for programmatic access
 */
class ONETracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.one.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.one.api_url', 'https://ecomm.one-line.com');
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
        return 'ONEY';
    }

    public function getCarrierName(): string
    {
        return 'ONE - Ocean Network Express';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/api/v1/tracking/bl', [
            'bl_number' => $mblNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('ONE API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/api/v1/tracking/container', [
            'container_number' => $containerNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('ONE API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/api/v1/tracking/booking', [
            'booking_number' => $bookingNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('ONE API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $list      = $data['list'] ?? [$data];
        $info      = $list[0] ?? [];
        $routing   = $info['routingInfo'] ?? [];
        $legs      = $routing['legs'] ?? [];
        $firstLeg  = $legs[0] ?? [];
        $lastLeg   = !empty($legs) ? $legs[count($legs) - 1] : [];

        $events = [];
        foreach ($info['containerTrackingList'] ?? $info['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['statusCd'] ?? $rawEvent['eventCode'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['eventDt'] ?? $rawEvent['eventDate'] ?? '') ?? '',
                location:    $rawEvent['placeNm'] ?? $rawEvent['locationName'] ?? null,
                locode:      $rawEvent['placeCd'] ?? $rawEvent['locode'] ?? null,
                vessel:      $rawEvent['vslEngNm'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['skdVoyNo'] ?? $rawEvent['voyage'] ?? null,
                description: $rawEvent['statusNm'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $info['cntrNo'] ?? null,
            'mbl_number'              => $info['blNo'] ?? null,
            'status'                  => $this->normalizeStatus($info['cntrStsCd'] ?? $info['status'] ?? ''),
            'vessel_name'             => $firstLeg['vslEngNm'] ?? null,
            'voyage_number'           => $firstLeg['skdVoyNo'] ?? null,
            'pol'                     => $firstLeg['porCd'] ?? null,
            'pod'                     => $lastLeg['dlyPlcCd'] ?? null,
            'eta'                     => $this->parseDate($lastLeg['etaDate'] ?? null),
            'ata'                     => $this->parseDate($lastLeg['ataDate'] ?? null),
            'etd'                     => $this->parseDate($firstLeg['etdDate'] ?? null),
            'atd'                     => $this->parseDate($firstLeg['atdDate'] ?? null),
            'current_location'        => $info['curLocNm'] ?? null,
            'current_location_locode' => $info['curLocCd'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
