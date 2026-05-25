<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * Yang Ming Marine Transport
 * Handles: YMLU, YMJA
 *
 * Real endpoint base: https://www.yangming.com/api/cargotracking
 * Auth: X-API-Key header
 */
class YangMingTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.yang_ming.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.yang_ming.api_url', 'https://www.yangming.com/api');
    }

    protected function defaultHeaders(): array
    {
        return [
            'X-API-Key'    => $this->apiKey,
            'Accept'       => 'application/json',
        ];
    }

    public function getCarrierScac(): string
    {
        return 'YMLU';
    }

    public function getCarrierName(): string
    {
        return 'Yang Ming Marine';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/cargotracking', [
            'type' => 'BL',
            'no'   => $mblNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Yang Ming API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/cargotracking', [
            'type' => 'CNO',
            'no'   => $containerNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Yang Ming API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/cargotracking', [
            'type' => 'BKG',
            'no'   => $bookingNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('Yang Ming API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $result   = $data['result'] ?? $data;
        $list     = $result['list'] ?? [$result];
        $info     = $list[0] ?? [];
        $legs     = $info['scheduleList'] ?? [];
        $firstLeg = $legs[0] ?? [];
        $lastLeg  = !empty($legs) ? $legs[count($legs) - 1] : [];

        $events = [];
        foreach ($info['moveList'] ?? $info['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['moveCd'] ?? $rawEvent['eventCode'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['moveDt'] ?? $rawEvent['eventDate'] ?? '') ?? '',
                location:    $rawEvent['portNm'] ?? $rawEvent['location'] ?? null,
                locode:      $rawEvent['portCd'] ?? null,
                vessel:      $rawEvent['vslNm'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voyNo'] ?? $rawEvent['voyage'] ?? null,
                description: $rawEvent['moveDsc'] ?? $rawEvent['description'] ?? null,
            );
        }

        return CarrierTrackingResponse::success([
            'container_number'        => $info['cntrNo'] ?? null,
            'mbl_number'              => $info['blNo'] ?? null,
            'status'                  => $this->normalizeStatus($info['cntrSts'] ?? ''),
            'vessel_name'             => $firstLeg['vslNm'] ?? null,
            'voyage_number'           => $firstLeg['voyNo'] ?? null,
            'pol'                     => $firstLeg['polCd'] ?? null,
            'pod'                     => $lastLeg['podCd'] ?? null,
            'eta'                     => $this->parseDate($lastLeg['eta'] ?? null),
            'ata'                     => $this->parseDate($lastLeg['ata'] ?? null),
            'etd'                     => $this->parseDate($firstLeg['etd'] ?? null),
            'atd'                     => $this->parseDate($firstLeg['atd'] ?? null),
            'current_location'        => $info['curLoc'] ?? null,
            'current_location_locode' => $info['curLocCd'] ?? null,
            'events'                  => $events,
            'raw_data'                => $data,
        ]);
    }
}
