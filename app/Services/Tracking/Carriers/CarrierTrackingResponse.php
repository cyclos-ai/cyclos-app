<?php

namespace App\Services\Tracking\Carriers;

class CarrierTrackingResponse
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $containerNumber,
        public readonly ?string $mblNumber,
        public readonly ?string $status,
        public readonly ?string $vesselName,
        public readonly ?string $voyageNumber,
        public readonly ?string $pol,
        public readonly ?string $pod,
        public readonly ?string $eta,
        public readonly ?string $ata,
        public readonly ?string $etd,
        public readonly ?string $atd,
        public readonly ?string $currentLocation,
        public readonly ?string $currentLocationLocode,
        public readonly array   $events = [],
        public readonly array   $rawData = [],
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * Build a successful response from a normalised data array.
     *
     * Expected keys (all optional except where noted):
     *   container_number, mbl_number, status, vessel_name, voyage_number,
     *   pol, pod, eta, ata, etd, atd, current_location, current_location_locode,
     *   events (array of CarrierTrackingEvent), raw_data (array)
     */
    public static function success(array $data): self
    {
        return new self(
            success:               true,
            containerNumber:       $data['container_number']        ?? null,
            mblNumber:             $data['mbl_number']              ?? null,
            status:                $data['status']                  ?? null,
            vesselName:            $data['vessel_name']             ?? null,
            voyageNumber:          $data['voyage_number']           ?? null,
            pol:                   $data['pol']                     ?? null,
            pod:                   $data['pod']                     ?? null,
            eta:                   $data['eta']                     ?? null,
            ata:                   $data['ata']                     ?? null,
            etd:                   $data['etd']                     ?? null,
            atd:                   $data['atd']                     ?? null,
            currentLocation:       $data['current_location']        ?? null,
            currentLocationLocode: $data['current_location_locode'] ?? null,
            events:                $data['events']                  ?? [],
            rawData:               $data['raw_data']                ?? [],
            errorMessage:          null,
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            success:               false,
            containerNumber:       null,
            mblNumber:             null,
            status:                null,
            vesselName:            null,
            voyageNumber:          null,
            pol:                   null,
            pod:                   null,
            eta:                   null,
            ata:                   null,
            etd:                   null,
            atd:                   null,
            currentLocation:       null,
            currentLocationLocode: null,
            events:                [],
            rawData:               [],
            errorMessage:          $message,
        );
    }

    public function toArray(): array
    {
        return [
            'success'                => $this->success,
            'container_number'       => $this->containerNumber,
            'mbl_number'             => $this->mblNumber,
            'status'                 => $this->status,
            'vessel_name'            => $this->vesselName,
            'voyage_number'          => $this->voyageNumber,
            'pol'                    => $this->pol,
            'pod'                    => $this->pod,
            'eta'                    => $this->eta,
            'ata'                    => $this->ata,
            'etd'                    => $this->etd,
            'atd'                    => $this->atd,
            'current_location'       => $this->currentLocation,
            'current_location_locode'=> $this->currentLocationLocode,
            'events'                 => array_map(
                static fn(CarrierTrackingEvent $e) => $e->toArray(),
                $this->events
            ),
            'error_message'          => $this->errorMessage,
        ];
    }
}
