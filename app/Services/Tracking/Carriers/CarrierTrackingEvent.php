<?php

namespace App\Services\Tracking\Carriers;

class CarrierTrackingEvent
{
    public function __construct(
        public readonly string  $eventType,
        public readonly string  $eventDate,
        public readonly ?string $location,
        public readonly ?string $locode,
        public readonly ?string $vessel,
        public readonly ?string $voyage,
        public readonly ?string $description,
    ) {}

    public function toArray(): array
    {
        return [
            'event_type'  => $this->eventType,
            'event_date'  => $this->eventDate,
            'location'    => $this->location,
            'locode'      => $this->locode,
            'vessel'      => $this->vessel,
            'voyage'      => $this->voyage,
            'description' => $this->description,
        ];
    }
}
