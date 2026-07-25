<?php

namespace App\Filament\Tenant\Resources\SeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\SeatingLayoutResource\Pages\DesignerSeatingLayout as BaseDesigner;
use App\Filament\Tenant\Resources\SeatingLayoutResource;

/**
 * Reuses the full Konva seating designer (generic, operates on the layout's
 * sections/rows/seats and reads $this->seatingLayout->tenant_id). Only the
 * owning resource differs, so URLs resolve inside the tenant panel.
 */
class DesignerSeatingLayout extends BaseDesigner
{
    protected static string $resource = SeatingLayoutResource::class;
}
