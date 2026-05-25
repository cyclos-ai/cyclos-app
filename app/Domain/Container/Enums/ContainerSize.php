<?php

namespace App\Domain\Container\Enums;

enum ContainerSize: string
{
    case TWENTY = '20';
    case FORTY = '40';
    case FORTY_FIVE = '45';

    public function label(): string
    {
        return match($this) {
            self::TWENTY => "20'",
            self::FORTY => "40'",
            self::FORTY_FIVE => "45'",
        };
    }
}
