<?php

namespace App\Filament\Operator\Pages;

use App\Models\Ticket;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class CheckIn extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-qr-code';
    protected static \UnitEnum|string|null $navigationGroup = 'Operațiuni';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Check-in';
    protected static ?string $slug = 'check-in';
    protected string $view = 'filament.operator.check-in';

    public string $scanInput = '';
    public ?array $lastScan = null;
    public ?string $lastError = null;

    public static function shouldRegisterNavigation(): bool
    {
        $teamMember = auth()->user()?->teamMember ?? null;
        return in_array($teamMember?->leisure_role, ['check_in', 'pos_manager', 'admin'], true);
    }

    public function submitScan(): void
    {
        $code = trim($this->scanInput);
        $this->scanInput = '';
        $this->lastError = null;
        $this->lastScan = null;

        if ($code === '') {
            return;
        }

        $tenantId = auth()->user()?->teamMember?->tenant_id;
        $ticket = Ticket::query()
            ->where('code', $code)
            ->whereHas('ticketType.event', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['ticketType:id,name,event_id', 'order:id,customer_email,customer_first_name,customer_last_name'])
            ->first();

        if (! $ticket) {
            $this->lastError = "Bilet inexistent sau aparține altui tenant: {$code}";
            return;
        }

        $alreadyScanned = $ticket->scanned_at !== null;
        if (! $alreadyScanned) {
            $ticket->update([
                'scanned_at' => now(),
                'scanned_by_user_id' => auth()->id(),
                'status' => 'used',
            ]);
        }

        $this->lastScan = [
            'code' => $ticket->code,
            'status' => $ticket->status,
            'ticket_type' => $ticket->ticketType?->name,
            'customer' => trim(($ticket->order?->customer_first_name ?? '') . ' ' . ($ticket->order?->customer_last_name ?? ''))
                ?: ($ticket->order?->customer_email ?? '—'),
            'already_used' => $alreadyScanned,
            'scanned_at' => optional($ticket->scanned_at)->format('H:i:s'),
        ];
    }
}
