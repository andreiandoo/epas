<?php

namespace App\Enums;

enum NfcChipType: string
{
    case DesfireEv3 = 'desfire_ev3';
    case Ntag213 = 'ntag213';

    public function label(): string
    {
        return match ($this) {
            self::DesfireEv3 => 'MIFARE DESFire EV3 (recommended)',
            self::Ntag213 => 'NTAG213 (economic)',
        };
    }

    public function balanceOnChip(): bool
    {
        return $this === self::DesfireEv3;
    }

    public function requiresKeyManagement(): bool
    {
        return $this === self::DesfireEv3;
    }

    public function supportsOfflineCharge(): bool
    {
        return $this === self::DesfireEv3;
    }
}
