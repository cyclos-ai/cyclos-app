<?php

namespace App\Domain\Container\Enums;

enum ContainerStatus: string
{
    case NOT_TRACKING = 'NOT_TRACKING';
    case AT_ORIGIN = 'AT_ORIGIN';
    case LOADED_ON_VESSEL = 'LOADED_ON_VESSEL';
    case ON_WATER = 'ON_WATER';
    case AWAITING_DISCHARGE = 'AWAITING_DISCHARGE';
    case AT_OCEAN_TERMINAL = 'AT_OCEAN_TERMINAL';
    case ON_RAIL = 'ON_RAIL';
    case ARRIVED_AT_RAIL_TERMINAL = 'ARRIVED_AT_RAIL_TERMINAL';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case EMPTY_RETURNED = 'EMPTY_RETURNED';
    case DROPPED = 'DROPPED';

    public function label(): string
    {
        return match($this) {
            self::NOT_TRACKING => 'Not Tracking',
            self::AT_ORIGIN => 'At Origin',
            self::LOADED_ON_VESSEL => 'Loaded on Vessel',
            self::ON_WATER => 'On Water',
            self::AWAITING_DISCHARGE => 'Awaiting Discharge',
            self::AT_OCEAN_TERMINAL => 'At Ocean Terminal',
            self::ON_RAIL => 'On Rail',
            self::ARRIVED_AT_RAIL_TERMINAL => 'Arrived at Rail Terminal',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::EMPTY_RETURNED => 'Empty Returned',
            self::DROPPED => 'Dropped',
        };
    }

    public function isActive(): bool
    {
        return !in_array($this, [self::NOT_TRACKING, self::EMPTY_RETURNED, self::DROPPED]);
    }
}
