<?php

namespace App\Domain\User\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case SHIPPER_ADMIN = 'shipper_admin';
    case SHIPPER_USER = 'shipper_user';
    case SHIPPER_VIEWER = 'shipper_viewer';
    case DRAYAGE_ADMIN = 'drayage_admin';
    case DRAYAGE_DISPATCHER = 'drayage_dispatcher';
    case DRAYAGE_DRIVER = 'drayage_driver';

    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::SHIPPER_ADMIN => 'Shipper Admin',
            self::SHIPPER_USER => 'Shipper User',
            self::SHIPPER_VIEWER => 'Shipper Viewer',
            self::DRAYAGE_ADMIN => 'Drayage Admin',
            self::DRAYAGE_DISPATCHER => 'Drayage Dispatcher',
            self::DRAYAGE_DRIVER => 'Drayage Driver',
        };
    }

    public function isShipper(): bool
    {
        return in_array($this, [self::SHIPPER_ADMIN, self::SHIPPER_USER, self::SHIPPER_VIEWER]);
    }

    public function isDrayage(): bool
    {
        return in_array($this, [self::DRAYAGE_ADMIN, self::DRAYAGE_DISPATCHER, self::DRAYAGE_DRIVER]);
    }

    public function canUploadCSV(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::SHIPPER_ADMIN, self::SHIPPER_USER]);
    }

    public function canUpdateDrayageSteps(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::DRAYAGE_ADMIN, self::DRAYAGE_DISPATCHER, self::DRAYAGE_DRIVER]);
    }
}
