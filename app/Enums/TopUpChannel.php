<?php

namespace App\Enums;

enum TopUpChannel: string
{
    case Online = 'online';
    case Physical = 'physical';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online (App/Website)',
            self::Physical => 'Physical (Stand)',
        };
    }
}
