<?php

namespace App\Domain\Drayage\Enums;

enum DrayageType: string
{
    case FULL = 'full';
    case EMPTY = 'empty';

    public function label(): string
    {
        return match($this) {
            self::FULL => 'Full Container',
            self::EMPTY => 'Empty Return',
        };
    }
}
