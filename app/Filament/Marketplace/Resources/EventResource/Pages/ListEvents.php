<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function mount(): void
    {
        parent::mount();

        // Check for organizer query parameter and apply filter
        $organizerId = request()->query('organizer');
        if ($organizerId) {
            $this->tableFilters['marketplace_organizer_id']['value'] = $organizerId;
        }

        // Move tabs inline with header (next to "New Event" button)
        // Filament 4 renders getTabs() as: fi-page-content > div.fi-sc-tabs > nav.fi-tabs
        $this->js("
            if (!window.__eventsTabsMover) {
                window.__eventsTabsMover = true;
                const moveTabs = () => {
                    const header = document.querySelector('.fi-header');
                    const tabsWrapper = document.querySelector('.fi-page-content > .fi-sc-tabs');
                    if (!header || !tabsWrapper || tabsWrapper.closest('.fi-header')) return;
                    const actions = header.querySelector('.fi-header-actions-ctn');
                    if (actions) header.insertBefore(tabsWrapper, actions);
                    else header.appendChild(tabsWrapper);
                    tabsWrapper.style.flex = '1';
                    tabsWrapper.style.minWidth = '0';
                };
                requestAnimationFrame(moveTabs);
                const content = document.querySelector('.fi-page-content');
                if (content) new MutationObserver(() => requestAnimationFrame(moveTabs)).observe(content, { childList: true });
            }
        ");
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

        return [
            'all' => Tab::make('Toate'),
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
                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[*].date')) >= ?", [$now->format('Y-m-d')]);
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
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[*].date')) >= ?", [$now->format('Y-m-d')]);
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
        ];
    }
}
