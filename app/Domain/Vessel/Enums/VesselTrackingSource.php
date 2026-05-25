<?php

namespace App\Domain\Vessel\Enums;

enum VesselTrackingSource: string
{
    case AIS = 'AIS';
    case CARRIER = 'CARRIER';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match($this) {
            self::AIS => 'AIS',
            self::CARRIER => 'Carrier',
            self::MANUAL => 'Manual',
        };
    }
}
