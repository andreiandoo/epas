<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
use App\Models\Event;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    public function mount(): void
    {
        parent::mount();

        // Apply event_id filter from URL query parameter
        $eventId = request()->query('event_id');
        if ($eventId) {
            $this->tableFilters['event_id']['event_id'] = $eventId;
        }
    }

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        $title = "Comenzi <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>";

        $eventId = request()->query('event_id');
        if ($eventId && ($event = Event::with('venue')->find($eventId))) {
            $locale = app()->getLocale();
            $eventTitle = is_array($event->title)
                ? ($event->title[$locale] ?? $event->title['ro'] ?? $event->title['en'] ?? reset($event->title))
                : $event->title;

            $venueName = null;
            $venueCity = null;
            if ($event->venue) {
                $venueName = is_array($event->venue->name)
                    ? ($event->venue->name[$locale] ?? $event->venue->name['ro'] ?? $event->venue->name['en'] ?? reset($event->venue->name))
                    : $event->venue->name;
                $venueCity = $event->venue->city ?? null;
            }

            $dateStr = '';
            if ($event->duration_mode === 'range' && $event->range_start_date) {
                $start = $event->range_start_date;
                $end = $event->range_end_date;
                if ($start && $end) {
                    if ($start->format('m Y') === $end->format('m Y')) {
                        $dateStr = $start->format('d') . '–' . $end->format('d M Y');
                    } else {
                        $dateStr = $start->format('d M Y') . ' – ' . $end->format('d M Y');
                    }
                } else {
                    $dateStr = $start->format('d M Y');
                }
            } elseif ($event->duration_mode === 'multi_slots' && !empty($event->multi_slots)) {
                $slots = is_array($event->multi_slots) ? $event->multi_slots : [];
                $dates = array_filter(array_map(fn ($s) => $s['date'] ?? null, $slots));
                if (!empty($dates)) {
                    sort($dates);
                    try {
                        $first = new \DateTime($dates[0]);
                        $last = new \DateTime(end($dates));
                        $dateStr = $first->format('d M Y') . ' – ' . $last->format('d M Y');
                    } catch (\Throwable $e) {
                        $dateStr = (string) $dates[0];
                    }
                }
            } elseif ($event->event_date) {
                $dateStr = $event->event_date->format('d M Y');
                if ($event->start_time) {
                    $dateStr .= ' • ' . \Illuminate\Support\Str::of($event->start_time)->substr(0, 5);
                }
            }

            $bits = array_filter([
                $eventTitle ? '<span class="font-medium">' . e($eventTitle) . '</span>' : null,
                $venueName ? e($venueName) : null,
                $venueCity ? e($venueCity) : null,
                $dateStr ? '<span class="text-primary-600 dark:text-primary-400">' . e($dateStr) . '</span>' : null,
            ]);

            if (!empty($bits)) {
                $title .= '<div class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400 flex flex-wrap items-center gap-x-2 gap-y-0.5">'
                    . implode('<span class="text-gray-300 dark:text-gray-600">•</span>', $bits)
                    . '</div>';
            }
        }

        return new HtmlString($title);
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => { const toolbar = document.querySelector('.fi-ta-header-toolbar'); if (!toolbar) return; toolbar.prepend(\$el); \$el.style.flex = 'none'; \$el.style.minWidth = '0'; const nav = \$el.querySelector('.fi-tabs'); if (nav) { nav.style.marginInline = '0'; } })",
            ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Toate'),
            'completed' => Tab::make('Finalizate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'completed')->count())
                ->badgeColor('success'),
            'paid' => Tab::make('Plătite')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'paid')->count())
                ->badgeColor('success'),
            'pending' => Tab::make('În așteptare')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'failed' => Tab::make('Eșuate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'failed')->count())
                ->badgeColor('danger'),
            'cancelled' => Tab::make('Anulate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'cancelled')->count())
                ->badgeColor('gray'),
            'refunded' => Tab::make('Rambursate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'refunded'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'refunded')->count())
                ->badgeColor('gray'),
            'expired' => Tab::make('Expirate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'expired'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'expired')->count())
                ->badgeColor('gray'),
        ];
    }
}
