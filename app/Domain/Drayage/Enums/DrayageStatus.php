<?php

namespace App\Domain\Drayage\Enums;

enum DrayageStatus: string
{
    case PENDING = 'pending';
    case TENDERED = 'tendered';
    case CONFIRMED = 'confirmed';
    case DENIED = 'denied';
    case DISPATCHED = 'dispatched';
    case AT_TERMINAL = 'at_terminal';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT_DELIVERY = 'in_transit_delivery';
    case ARRIVED_AT_DELIVERY = 'arrived_at_delivery';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case EMPTY_AT_DELIVERY = 'empty_at_delivery';
    case PICKED_UP_EMPTY = 'picked_up_empty';
    case IN_TRANSIT_RETURN = 'in_transit_return';
    case EMPTY_RETURNED = 'empty_returned';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending Assignment',
            self::TENDERED => 'Tendered to Carrier',
            self::CONFIRMED => 'Confirmed by Carrier',
            self::DENIED => 'Denied by Carrier',
            self::DISPATCHED => 'Dispatched',
            self::AT_TERMINAL => 'At Terminal',
            self::PICKED_UP => 'Picked Up from Terminal',
            self::IN_TRANSIT_DELIVERY => 'In Transit to Delivery',
            self::ARRIVED_AT_DELIVERY => 'Arrived at Delivery',
            self::DELIVERING => 'Unloading',
            self::DELIVERED => 'Delivered',
            self::EMPTY_AT_DELIVERY => 'Empty at Delivery Site',
            self::PICKED_UP_EMPTY => 'Empty Picked Up',
            self::IN_TRANSIT_RETURN => 'In Transit to Terminal',
            self::EMPTY_RETURNED => 'Empty Returned to Terminal',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function step(): int
    {
        return match($this) {
            self::PENDING => 1,
            self::TENDERED => 2,
            self::CONFIRMED => 3,
            self::DENIED => 0,
            self::DISPATCHED => 4,
            self::AT_TERMINAL => 5,
            self::PICKED_UP => 6,
            self::IN_TRANSIT_DELIVERY => 7,
            self::ARRIVED_AT_DELIVERY => 8,
            self::DELIVERING => 9,
            self::DELIVERED => 10,
            self::EMPTY_AT_DELIVERY => 11,
            self::PICKED_UP_EMPTY => 12,
            self::IN_TRANSIT_RETURN => 13,
            self::EMPTY_RETURNED => 14,
            self::COMPLETED => 15,
            self::CANCELLED => 0,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::DENIED]);
    }
}
