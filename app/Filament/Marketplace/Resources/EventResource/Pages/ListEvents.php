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
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;
    use \App\Filament\Marketplace\Concerns\HasListChrome;

    /** The resource table sets its own Filtre / Coloane buttons. */
    protected function listLabelsTriggers(): bool
    {
        return false;
    }

    protected static string $resource = EventResource::class;

    public function getHeading(): string|Htmlable
    {
        $query = static::getResource()::getEloquentQuery();
        $count = number_format($query->count(), 0, ',', '.');
        return new HtmlString(e(__('Evenimente')) . " <span class=\"ep-lst-count\">{$count}</span>");
    }

    public function getSubheading(): string|Htmlable|null
    {
        $marketplaceAdmin = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user();
        if (!$marketplaceAdmin) return null;

        $expiring = \App\Models\Event::expiringWithoutReplacement($marketplaceAdmin->marketplace_client_id);

        if ($expiring->isEmpty()) return null;

        $items = $expiring->map(function ($e) {
            $title = is_array($e->title) ? ($e->title['ro'] ?? $e->title['en'] ?? reset($e->title) ?: 'Eveniment') : ($e->title ?? 'Eveniment');
            $url = static::getResource()::getUrl('edit', ['record' => $e->id]) . '?tab=bilete';
            return '<li><a href="' . e($url) . '" style="color:#92400e;text-decoration:underline;font-weight:600;">' . e($title) . '</a></li>';
        })->implode('');

        return new HtmlString(
            '<div style="margin-top:12px;padding:12px 16px;border:1px solid #f59e0b;background:#fffbeb;border-radius:8px;color:#92400e;">'
            . '<div style="display:flex;align-items:center;gap:8px;font-weight:700;margin-bottom:6px;">'
            . '<svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            . 'Atenție: ' . $expiring->count() . ' eveniment(e) au tipuri de bilete care expiră în 24h fără înlocuitor'
            . '</div>'
            . '<ul style="margin:4px 0 0 24px;font-size:13px;list-style:disc;">' . $items . '</ul>'
            . '</div>'
        );
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

    /** Tab-urile din "De completat" (aceleasi interogari, doar grupate in meniu). */
    protected function listTodoTabKeys(): array
    {
        return ['no_venue', 'no_artists', 'no_category', 'no_genre'];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import')
                ->label(__('Importă'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('import')),
            Actions\CreateAction::make()
                ->label(__('Eveniment nou'))
                ->icon('heroicon-m-plus')
                ->extraAttributes([
                    'id' => 'create-event-btn',
                ]),
        ];
    }

    public function getTabs(): array
    {
        $now = now();

        return [
            'all' => Tab::make(__('Toate'))->badge(fn () => number_format($this->getResource()::getEloquentQuery()->count(), 0, ',', '.')),
            'active' => Tab::make(__('Active'))
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
            'unpublished' => Tab::make(__('Nepublicate'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_published', false))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('is_published', false)->count())
                ->badgeColor('warning'),
            'ended' => Tab::make(__('Încheiate'))
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
            'no_venue' => Tab::make(__('Fără locație'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('venue_id'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereNull('venue_id')->count())
                ->badgeColor('danger'),
            'no_artists' => Tab::make(__('Fără artiști'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('artists'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereDoesntHave('artists')->count())
                ->badgeColor('danger'),
            'no_category' => Tab::make(__('Fără categorie'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('marketplace_event_category_id'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereNull('marketplace_event_category_id')->count())
                ->badgeColor('danger'),
            'no_genre' => Tab::make(__('Fără gen'))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('eventGenres'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereDoesntHave('eventGenres')->count())
                ->badgeColor('danger'),
        ];
    }
}
