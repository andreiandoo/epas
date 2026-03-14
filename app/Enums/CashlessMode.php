<?php

namespace App\Enums;

enum CashlessMode: string
{
    case Nfc = 'nfc';
    case Qr = 'qr';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Nfc => 'NFC (Chip Wristband)',
            self::Qr => 'QR Code',
            self::Hybrid => 'Hybrid (NFC + QR)',
        };
    }

    public function supportsNfc(): bool
    {
        return $this === self::Nfc || $this === self::Hybrid;
    }

    public function supportsQr(): bool
    {
        return $this === self::Qr || $this === self::Hybrid;
    }
}
