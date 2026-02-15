<?php

namespace App\Filament\Marketplace\Resources\AffiliateEventResource\Pages;

use App\Filament\Marketplace\Resources\AffiliateEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAffiliateEvents extends ListRecords
{
    protected static string $resource = AffiliateEventResource::class;

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => { const header = document.querySelector('.fi-header'); if (!header) return; const actions = header.querySelector('.fi-header-actions-ctn'); if (actions) header.insertBefore(\$el, actions); else header.appendChild(\$el); \$el.style.flex = '1'; \$el.style.minWidth = '0'; const nav = \$el.querySelector('.fi-tabs'); if (nav) { nav.style.marginInline = 'unset'; nav.style.marginLeft = 'auto'; nav.style.marginRight = '0'; } })",
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

        return [
            'all' => Tab::make('Toate'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(function (Builder $query) use ($now) {
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
                            $qq->where('duration_mode', 'multi_day')
                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[*].date')) >= ?", [$now->format('Y-m-d')]);
                        })
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
