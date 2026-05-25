<?php

namespace App\Services\Tracking\Carriers\Implementations;

use App\Services\Tracking\Carriers\CarrierTrackingEvent;
use App\Services\Tracking\Carriers\CarrierTrackingResponse;

/**
 * HMM - Hyundai Merchant Marine
 * Handles: HDMU
 *
 * Real endpoint base: https://www.hmm21.com/e-service/trackTrace
 * Auth: X-API-Key header
 */
class HMMTracker extends AbstractCarrierTracker
{
    protected function resolveApiKey(): string
    {
        return config('carriers.hmm.api_key', '');
    }

    protected function resolveBaseUrl(): string
    {
        return config('carriers.hmm.api_url', 'https://www.hmm21.com/api');
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
        return 'HDMU';
    }

    public function getCarrierName(): string
    {
        return 'HMM - Hyundai Merchant Marine';
    }

    public function trackByMBL(string $mblNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($mblNumber, 'mbl');
        }

        $data = $this->makeRequest('/e-service/trackTrace', [
            'searchType' => 'BL',
            'searchValue' => $mblNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('HMM API unavailable');
    }

    public function trackByContainer(string $containerNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($containerNumber, 'container');
        }

        $data = $this->makeRequest('/e-service/trackTrace', [
            'searchType'  => 'CONTAINER',
            'searchValue' => $containerNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('HMM API unavailable');
    }

    public function trackByBooking(string $bookingNumber): CarrierTrackingResponse
    {
        if (empty($this->apiKey)) {
            return $this->buildStubResponse($bookingNumber, 'booking');
        }

        $data = $this->makeRequest('/e-service/trackTrace', [
            'searchType'  => 'BOOKING',
            'searchValue' => $bookingNumber,
        ]);

        return $data !== null ? $this->parseResponse($data) : CarrierTrackingResponse::failure('HMM API unavailable');
    }

    private function parseResponse(array $data): CarrierTrackingResponse
    {
        $result   = $data['resultData'] ?? $data;
        $info     = ($result['containerList'] ?? [$result])[0] ?? [];
        $schedule = $info['scheduleList'] ?? [];
        $firstLeg = $schedule[0] ?? [];
        $lastLeg  = !empty($schedule) ? $schedule[count($schedule) - 1] : [];

        $events = [];
        foreach ($info['trackList'] ?? $info['events'] ?? [] as $rawEvent) {
            $events[] = new CarrierTrackingEvent(
                eventType:   $this->mapEventType($rawEvent['actCd'] ?? $rawEvent['eventType'] ?? ''),
                eventDate:   $this->parseDate($rawEvent['actDt'] ?? $rawEvent['eventDate'] ?? '') ?? '',
                location:    $rawEvent['portNm'] ?? $rawEvent['location'] ?? null,
                locode:      $rawEvent['portCd'] ?? null,
                vessel:      $rawEvent['vslNm'] ?? $rawEvent['vesselName'] ?? null,
                voyage:      $rawEvent['voyNo'] ?? $rawEvent['voyage'] ?? null,
                description: $rawEvent['actNm'] ?? $rawEvent['description'] ?? null,
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
