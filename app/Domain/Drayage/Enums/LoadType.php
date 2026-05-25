<?php

namespace App\Domain\Drayage\Enums;

enum LoadType: string
{
    case LIVE = 'live';
    case DROP = 'drop';

    public function label(): string
    {
        return match($this) {
            self::LIVE => 'Live Unload',
            self::DROP => 'Drop & Pick',
        };
    }
}
