<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\Event;
use App\Models\MarketplaceEventDateCapacity;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class DailyCapacities extends Page
{
    use InteractsWithRecord;
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;
    protected static ?string $title = 'Capacitate Zilnică';

    protected string $view = 'filament.marketplace.resources.event-resource.pages.daily-capacities';

    public string $selectedMonth = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $marketplace = static::getMarketplaceClient();
        if ($this->record->marketplace_client_id !== $marketplace?->id) {
            abort(403);
        }

        if (($this->record->display_template ?? 'standard') !== 'leisure_venue') {
            redirect(EventResource::getUrl('edit', ['record' => $this->record]));
            return;
        }

        $this->selectedMonth = now()->format('Y-m');
    }

    public function getViewData(): array
    {
        $event = $this->record;
        $start = Carbon::parse($this->selectedMonth . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Get ticket types with daily capacity
        $ticketTypes = $event->ticketTypes()
            ->whereNotNull('daily_capacity')
            ->orderBy('sort_order')
            ->get();

        // Get existing capacity rows for this month
        $capacities = MarketplaceEventDateCapacity::where('marketplace_event_id', $event->id)
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy(fn ($r) => $r->visit_date->format('Y-m-d'))
            ->map(fn ($rows) => $rows->keyBy('marketplace_ticket_type_id'));

        // Build grid data
        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateStr = $cursor->toDateString();
            $dayData = ['date' => $dateStr, 'label' => $cursor->format('d'), 'weekday' => $cursor->format('D'), 'ticket_types' => []];

            $isOpen = $event->isDateOpen($dateStr);
            $dayData['is_open'] = $isOpen;
            $dayData['is_past'] = $cursor->lt(now()->startOfDay());

            foreach ($ticketTypes as $tt) {
                $cap = $capacities->get($dateStr)?->get($tt->id);
                $dayData['ticket_types'][$tt->id] = [
                    'capacity' => $cap?->capacity ?? $tt->daily_capacity,
                    'sold' => $cap?->sold ?? 0,
                    'reserved' => $cap?->reserved ?? 0,
                    'is_closed' => $cap?->is_closed ?? false,
                    'price_override' => $cap?->price_override,
                    'exists' => $cap !== null,
                ];
            }

            $days[] = $dayData;
            $cursor->addDay();
        }

        return [
            'event' => $event,
            'ticketTypes' => $ticketTypes,
            'days' => $days,
            'monthLabel' => Carbon::parse($this->selectedMonth . '-01')->translatedFormat('F Y'),
        ];
    }

    public function previousMonth(): void
    {
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->addMonth()->format('Y-m');
    }

    public function toggleClosed(string $date, int $ticketTypeId): void
    {
        $event = $this->record;
        $tt = $event->ticketTypes()->find($ticketTypeId);
        if (!$tt) return;

        $cap = MarketplaceEventDateCapacity::getOrCreate($event->id, $ticketTypeId, $date, $tt->daily_capacity);
        $cap->update(['is_closed' => !$cap->is_closed]);

        Notification::make()
            ->title($cap->is_closed ? "Închis pe {$date}" : "Deschis pe {$date}")
            ->success()
            ->send();
    }

    public function updateCapacity(string $date, int $ticketTypeId, int $newCapacity): void
    {
        $event = $this->record;
        $tt = $event->ticketTypes()->find($ticketTypeId);
        if (!$tt || $newCapacity < 0) return;

        $cap = MarketplaceEventDateCapacity::getOrCreate($event->id, $ticketTypeId, $date, $tt->daily_capacity);
        $cap->update(['capacity' => $newCapacity]);

        Notification::make()->title("Capacitate actualizată: {$newCapacity}")->success()->send();
    }

    public function updatePriceOverride(string $date, int $ticketTypeId, ?float $price): void
    {
        $event = $this->record;
        $tt = $event->ticketTypes()->find($ticketTypeId);
        if (!$tt) return;

        $cap = MarketplaceEventDateCapacity::getOrCreate($event->id, $ticketTypeId, $date, $tt->daily_capacity);
        $cap->update(['price_override' => $price ?: null]);

        Notification::make()->title($price ? "Preț override: {$price} RON" : 'Override preț eliminat')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Înapoi la eveniment')
                ->url(EventResource::getUrl('edit', ['record' => $this->record]))
                ->icon('heroicon-o-arrow-left')
                ->color('gray'),
        ];
    }
}
