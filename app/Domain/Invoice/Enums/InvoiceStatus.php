<?php

namespace App\Domain\Invoice\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case OK_TO_PAY = 'ok_to_pay';
    case PAID = 'paid';
    case DISPUTED = 'disputed';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::OK_TO_PAY => 'OK to Pay',
            self::PAID => 'Paid',
            self::DISPUTED => 'Disputed',
            self::VOIDED => 'Voided',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::OK_TO_PAY => 'blue',
            self::PAID => 'green',
            self::DISPUTED => 'red',
            self::VOIDED => 'gray',
        };
    }
}
