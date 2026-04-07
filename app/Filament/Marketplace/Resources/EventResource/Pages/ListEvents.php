<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function getHeading(): string|Htmlable
    {
        $query = static::getResource()::getEloquentQuery();
        $count = number_format($query->count());
        return new HtmlString("Evenimente <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    public function mount(): void
    {
        parent::mount();

        // Check for organizer query parameter and apply filter
        $organizerId = request()->query('organizer');
        if ($organizerId) {
            $this->tableFilters['marketplace_organizer_id']['value'] = $organizerId;
        }
    }

    /**
     * Override tabs component to move it inline with the page header.
     * Adds x-init Alpine directive on the wrapper div.fi-sc-tabs
     * which physically moves itself into the fi-header element.
     */
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
                    // Move create button into toolbar, before tabs
                    const createBtn = document.getElementById('create-event-btn');
                    if (createBtn) {
                        toolbar.prepend(nav);
                        toolbar.prepend(createBtn);
                        createBtn.style.order = '-2';
                        nav.style.order = '-1';
                    } else {
                        toolbar.prepend(nav);
                        nav.style.order = '-1';
                    }
                    const style = document.createElement('style');
                    style.textContent = '.fi-ta-header-toolbar .fi-ta-bulk-actions { order: -3; }';
                    document.head.appendChild(style);
                })",
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label('Import Events')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('import')),
            Actions\CreateAction::make()
                ->extraAttributes([
                    'id' => 'create-event-btn',
                ]),
        ];
    }

    public function getTabs(): array
    {
        $now = now();

        return [
            'all' => Tab::make('Toate'),
            'unpublished' => Tab::make('Nepublicate')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_published', false))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('is_published', false)->count())
                ->badgeColor('warning'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(function (Builder $query) use ($now) {
                    return $query->where(function ($q) use ($now) {
                        // Range mode: end date in future
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
                        // Single day mode
                        ->orWhere(function ($qq) use ($now) {
                            $qq->where('duration_mode', 'single_day')
                                ->whereDate('event_date', '>=', $now);
                        })
                        // Multi-day mode
                        ->orWhere(function ($qq) use ($now) {
                            $qq->where('duration_mode', 'multi_day')
                                ->whereRaw(
                                    DB::getDriverName() === 'pgsql'
                                        ? "multi_slots->0->>'date' >= ?"
                                        : "JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[*].date')) >= ?",
                                    [$now->format('Y-m-d')]
                                );
                        })
                        // Fallback for null duration_mode
                        ->orWhere(function ($qq) use ($now) {
                            $qq->whereNull('duration_mode')
                                ->whereDate('event_date', '>=', $now);
                        });
                    });
                })
                ->badge(function () {
                    $now = now();
                    return $this->getResource()::getEloquentQuery()
                        ->where(function ($q) use ($now) {
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
                                $qq->where('duration_mode', 'multi_day')
                                    ->whereRaw(
                                        DB::getDriverName() === 'pgsql'
                                            ? "multi_slots->0->>'date' >= ?"
                                            : "JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[*].date')) >= ?",
                                        [$now->format('Y-m-d')]
                                    );
                            })
                            ->orWhere(function ($qq) use ($now) {
                                $qq->whereNull('duration_mode')
                                    ->whereDate('event_date', '>=', $now);
                            });
                        })
                        ->count();
                })
                ->badgeColor('success'),
            'ended' => Tab::make('Încheiate')
                ->modifyQueryUsing(function (Builder $query) use ($now) {
                    return $query->where(function ($q) use ($now) {
                        // Range mode: end date in past
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
                        // Single day mode
                        ->orWhere(function ($qq) use ($now) {
                            $qq->where('duration_mode', 'single_day')
                                ->whereDate('event_date', '<', $now);
                        })
                        // Fallback for null duration_mode
                        ->orWhere(function ($qq) use ($now) {
                            $qq->whereNull('duration_mode')
                                ->whereDate('event_date', '<', $now);
                        });
                    });
                })
                ->badge(function () {
                    $now = now();
                    return $this->getResource()::getEloquentQuery()
                        ->where(function ($q) use ($now) {
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
                        })
                        ->count();
                })
                ->badgeColor('gray'),
            'no_venue' => Tab::make('Fără venue')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('venue_id'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereNull('venue_id')->count())
                ->badgeColor('danger'),
            'no_artists' => Tab::make('Fără artiști')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('artists'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereDoesntHave('artists')->count())
                ->badgeColor('danger'),
            'no_category' => Tab::make('Fără categorie')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('marketplace_event_category_id'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereNull('marketplace_event_category_id')->count())
                ->badgeColor('danger'),
            'no_genre' => Tab::make('Fără gen')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('eventGenres'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereDoesntHave('eventGenres')->count())
                ->badgeColor('danger'),
        ];
    }
}
