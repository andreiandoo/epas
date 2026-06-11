<?php

namespace App\Filament\Operator\Pages;

use App\Models\Leisure\PhysicalResource;
use App\Models\Leisure\ResourceRental;
use App\Models\Ticket;
use App\Services\Leisure\RentalService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ActiveRentals extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';
    protected static \UnitEnum|string|null $navigationGroup = 'Operațiuni';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Rentals active';
    protected static ?string $slug = 'active-rentals';
    protected string $view = 'filament.operator.active-rentals';

    public string $startTicketCode = '';
    public string $startResourceCode = '';
    public string $endRentalCode = '';

    public static function shouldRegisterNavigation(): bool
    {
        $teamMember = auth()->user()?->teamMember ?? null;
        return in_array($teamMember?->leisure_role, ['rental_operator', 'pos_manager', 'admin'], true);
    }

    public function getViewData(): array
    {
        $tenantId = auth()->user()?->teamMember?->tenant_id;
        $active = ResourceRental::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('ended_at')
            ->with(['physicalResource:id,name,resource_type,qr_code', 'ticket:id,code'])
            ->orderBy('started_at')
            ->get();
        return compact('active');
    }

    public function startRental(RentalService $svc): void
    {
        $tenantId = auth()->user()?->teamMember?->tenant_id;
        $ticketCode = trim($this->startTicketCode);
        $resourceCode = trim($this->startResourceCode);

        if (! $ticketCode || ! $resourceCode) {
            Notification::make()->danger()->title('Completează ambele coduri.')->send();
            return;
        }

        $ticket = Ticket::query()
            ->where('code', $ticketCode)
            ->whereHas('ticketType.event', fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        $resource = PhysicalResource::query()
            ->where('tenant_id', $tenantId)
            ->where('qr_code', $resourceCode)
            ->first();

        if (! $ticket) { Notification::make()->danger()->title('Bilet invalid.')->body($ticketCode)->send(); return; }
        if (! $resource) { Notification::make()->danger()->title('Resursă invalidă.')->body($resourceCode)->send(); return; }

        try {
            $rental = $svc->start($ticket, $resource, auth()->id());
            $this->startTicketCode = '';
            $this->startResourceCode = '';
            Notification::make()->success()
                ->title('Rental început')
                ->body("{$resource->name} — planificat sfârșit: " . $rental->planned_end_at->format('H:i'))
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Nu pot porni rental')->body($e->getMessage())->send();
        }
    }

    public function endRental(int $rentalId, RentalService $svc): void
    {
        $tenantId = auth()->user()?->teamMember?->tenant_id;
        $rental = ResourceRental::where('tenant_id', $tenantId)->find($rentalId);
        if (! $rental) {
            Notification::make()->danger()->title('Rental inexistent.')->send();
            return;
        }
        try {
            $updated = $svc->end($rental, auth()->id());
            $msg = "Durată reală: {$updated->elapsed_minutes} min";
            if ($updated->overtime_minutes > 0) {
                $msg .= " · Depășire: {$updated->overtime_minutes} min · Surcharge: " . number_format($updated->overtime_surcharge_cents / 100, 2) . " RON";
            }
            Notification::make()->success()->title('Rental încheiat')->body($msg)->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Nu pot închide rental')->body($e->getMessage())->send();
        }
    }
}
