<?php

namespace App\Domain\Booking\Enums;

enum BookingStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::CONFIRMED => 'Confirmed',
            self::SHIPPED => 'Shipped',
            self::COMPLETED => 'Completed',
            self::CANCELED => 'Canceled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::SHIPPED => 'indigo',
            self::COMPLETED => 'green',
            self::CANCELED => 'red',
        };
    }
}
