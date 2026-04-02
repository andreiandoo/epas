<?php

namespace App\Enums;

enum WristbandType: string
{
    case General = 'general';
    case Vip = 'vip';
    case Staff = 'staff';
    case Artist = 'artist';
    case Sponsor = 'sponsor';
    case Media = 'media';
    case VendorStaff = 'vendor_staff';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Vip => 'VIP',
            self::Staff => 'Staff',
            self::Artist => 'Artist',
            self::Sponsor => 'Sponsor',
            self::Media => 'Media',
            self::VendorStaff => 'Vendor Staff',
        };
    }

    public function receivesFestivalCredit(): bool
    {
        return in_array($this, [self::Staff, self::Artist, self::Sponsor]);
    }

    public function hasUnlimitedBalance(): bool
    {
        return in_array($this, [self::Staff, self::Artist, self::Sponsor]);
    }

    public function accessZones(): array
    {
        return match ($this) {
            self::General => ['standard'],
            self::Vip => ['standard', 'vip'],
            self::Staff => ['standard', 'vip', 'backstage', 'operations'],
            self::Artist => ['standard', 'vip', 'backstage', 'artist'],
            self::Sponsor => ['standard', 'vip', 'sponsor'],
            self::Media => ['standard', 'media', 'press'],
            self::VendorStaff => ['standard', 'vendor'],
        };
    }
}
