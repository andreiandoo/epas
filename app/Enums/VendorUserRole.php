<?php

namespace App\Enums;

enum VendorUserRole: string
{
    case Manager = 'manager';
    case Supervisor = 'supervisor';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Supervisor => 'Supervisor',
            self::Member => 'Member',
        };
    }

    public function canManageProducts(): bool
    {
        return $this === self::Manager;
    }

    public function canViewReports(): bool
    {
        return $this === self::Manager || $this === self::Supervisor;
    }

    public function canManageStaff(): bool
    {
        return $this === self::Manager;
    }

    public function canManageShifts(): bool
    {
        return $this === self::Manager || $this === self::Supervisor;
    }

    public function canImportCsv(): bool
    {
        return $this === self::Manager;
    }

    public function canViewStock(): bool
    {
        return $this === self::Manager || $this === self::Supervisor;
    }
}
