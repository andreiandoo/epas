<?php

namespace App\Enums;

enum CashoutChannel: string
{
    case Online = 'online';
    case Physical = 'physical';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online (Bank Transfer/Card Refund)',
            self::Physical => 'Physical (Cash/Card at Stand)',
        };
    }
}
