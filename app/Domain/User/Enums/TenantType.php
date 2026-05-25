<?php

namespace App\Domain\User\Enums;

enum TenantType: string
{
    case SHIPPER = 'shipper';
    case DRAYAGE = 'drayage';
    case BOTH = 'both';

    public function label(): string
    {
        return match($this) {
            self::SHIPPER => 'Shipper / Importer',
            self::DRAYAGE => 'Drayage / Motor Carrier',
            self::BOTH => 'Shipper & Drayage',
        };
    }
}
