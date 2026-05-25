<?php

namespace App\Domain\Container\Enums;

enum ContainerType: string
{
    case DRY = 'DRY';
    case REEFER = 'REEFER';
    case FLAT_RACK = 'FLAT_RACK';
    case OPEN_TOP = 'OPEN_TOP';
    case TANK = 'TANK';

    public function label(): string
    {
        return match($this) {
            self::DRY => 'Dry',
            self::REEFER => 'Reefer',
            self::FLAT_RACK => 'Flat Rack',
            self::OPEN_TOP => 'Open Top',
            self::TANK => 'Tank',
        };
    }
}
