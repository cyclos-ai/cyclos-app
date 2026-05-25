<?php

namespace App\Domain\Container\Enums;

enum ContainerEventType: string
{
    case GATE_IN = 'GATE_IN';
    case LOADED = 'LOADED';
    case DEPARTED = 'DEPARTED';
    case ARRIVED = 'ARRIVED';
    case DISCHARGED = 'DISCHARGED';
    case GATE_OUT = 'GATE_OUT';
    case EMPTY_RETURN = 'EMPTY_RETURN';
    case CUSTOMS_HOLD = 'CUSTOMS_HOLD';
    case CUSTOMS_RELEASE = 'CUSTOMS_RELEASE';
    case FDA_HOLD = 'FDA_HOLD';
    case FDA_RELEASE = 'FDA_RELEASE';
    case USDA_HOLD = 'USDA_HOLD';
    case USDA_RELEASE = 'USDA_RELEASE';
    case EXAM_ORDERED = 'EXAM_ORDERED';
    case EXAM_COMPLETED = 'EXAM_COMPLETED';
    case RAIL_DEPARTURE = 'RAIL_DEPARTURE';
    case RAIL_ARRIVAL = 'RAIL_ARRIVAL';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    case TRANSSHIPMENT = 'TRANSSHIPMENT';
    case VESSEL_DEPARTURE = 'VESSEL_DEPARTURE';
    case VESSEL_ARRIVAL = 'VESSEL_ARRIVAL';
    case DEMURRAGE_START = 'DEMURRAGE_START';
    case DETENTION_START = 'DETENTION_START';
    case PRIORITY_UPDATED = 'PRIORITY_UPDATED';
    case MANUAL_UPDATE = 'MANUAL_UPDATE';

    public function label(): string
    {
        return match($this) {
            self::GATE_IN => 'Gate In',
            self::LOADED => 'Loaded',
            self::DEPARTED => 'Departed',
            self::ARRIVED => 'Arrived',
            self::DISCHARGED => 'Discharged',
            self::GATE_OUT => 'Gate Out',
            self::EMPTY_RETURN => 'Empty Return',
            self::CUSTOMS_HOLD => 'Customs Hold',
            self::CUSTOMS_RELEASE => 'Customs Release',
            self::FDA_HOLD => 'FDA Hold',
            self::FDA_RELEASE => 'FDA Release',
            self::USDA_HOLD => 'USDA Hold',
            self::USDA_RELEASE => 'USDA Release',
            self::EXAM_ORDERED => 'Exam Ordered',
            self::EXAM_COMPLETED => 'Exam Completed',
            self::RAIL_DEPARTURE => 'Rail Departure',
            self::RAIL_ARRIVAL => 'Rail Arrival',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::TRANSSHIPMENT => 'Transshipment',
            self::VESSEL_DEPARTURE => 'Vessel Departure',
            self::VESSEL_ARRIVAL => 'Vessel Arrival',
            self::DEMURRAGE_START => 'Demurrage Start',
            self::DETENTION_START => 'Detention Start',
            self::PRIORITY_UPDATED => 'Priority Updated',
            self::MANUAL_UPDATE => 'Manual Update',
        };
    }
}
