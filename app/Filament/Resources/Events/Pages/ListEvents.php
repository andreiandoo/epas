<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Events <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => {
                    const toolbar = document.querySelector('.fi-ta-header-toolbar');
                    if (!toolbar) return;
                    const nav = \$el.querySelector('.fi-tabs');
                    if (!nav) return;
                    toolbar.prepend(nav);
                    nav.style.order = '-1';
                    const style = document.createElement('style');
                    style.textContent = '.fi-ta-header-toolbar .fi-ta-bulk-actions { order: -3; }';
                    document.head.appendChild(style);
                })",
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $now = now();

        $activeQuery = function (Builder $query) use ($now) {
            return $query->where(function ($q) use ($now) {
                $q->where(function ($qq) use ($now) {
                    $qq->where('duration_mode', 'range')
                        ->where(function ($qqq) use ($now) {
                            $qqq->whereDate('range_end_date', '>=', $now)
                                ->orWhere(function ($qqqq) use ($now) {
                                    $qqqq->whereNull('range_end_date')
                                        ->whereDate('range_start_date', '>=', $now);
                                });
                        });
                })
                ->orWhere(function ($qq) use ($now) {
                    $qq->where('duration_mode', 'single_day')
                        ->whereDate('event_date', '>=', $now);
                })
                ->orWhere(function ($qq) use ($now) {
                    $qq->whereNull('duration_mode')
                        ->whereDate('event_date', '>=', $now);
                });
            });
        };

        $endedQuery = function (Builder $query) use ($now) {
            return $query->where(function ($q) use ($now) {
                $q->where(function ($qq) use ($now) {
                    $qq->where('duration_mode', 'range')
                        ->where(function ($qqq) use ($now) {
                            $qqq->whereDate('range_end_date', '<', $now)
                                ->orWhere(function ($qqqq) use ($now) {
                                    $qqqq->whereNull('range_end_date')
                                        ->whereDate('range_start_date', '<', $now);
                                });
                        });
                })
                ->orWhere(function ($qq) use ($now) {
                    $qq->where('duration_mode', 'single_day')
                        ->whereDate('event_date', '<', $now);
                })
                ->orWhere(function ($qq) use ($now) {
                    $qq->whereNull('duration_mode')
                        ->whereDate('event_date', '<', $now);
                });
            });
        };

        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing($activeQuery)
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where(fn ($q) => $activeQuery($q))->count())
                ->badgeColor('success'),
            'ended' => Tab::make('Ended')
                ->modifyQueryUsing($endedQuery)
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where(fn ($q) => $endedQuery($q))->count())
                ->badgeColor('gray'),
            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_cancelled', true))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('is_cancelled', true)->count())
                ->badgeColor('danger'),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'draft')->count())
                ->badgeColor('warning'),
        ];
    }
}
