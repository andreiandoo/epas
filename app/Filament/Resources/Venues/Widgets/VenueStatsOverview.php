<?php

namespace App\Filament\Resources\Venues\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Venue;

class VenueStatsOverview extends BaseWidget
{
    protected function venueId(): int
    {
        $key = request()->route('record');
        $venue = Venue::query()
            ->when(is_numeric($key), fn($q) => $q->where('id', $key), fn($q) => $q->where('slug', $key))
            ->firstOrFail();

        return (int) $venue->id;
    }

    protected function getStats(): array
    {
        $venueId = $this->venueId();

        $eventsCount = (int) DB::table('events')->where('venue_id', $venueId)->count();

        $topCategory = '-';
        if (Schema::hasTable('event_event_category') && Schema::hasTable('event_categories')) {
            $topCategory = DB::table('event_event_category')
                ->join('events', 'events.id', '=', 'event_event_category.event_id')
                ->join('event_categories', 'event_categories.id', '=', 'event_event_category.event_category_id')
                ->where('events.venue_id', $venueId)
                ->select('event_categories.name', DB::raw('COUNT(*) AS c'))
                ->groupBy('event_categories.name')
                ->orderByDesc('c')
                ->value('event_categories.name') ?? '-';
        }

        $topGenre = '-';
        if (Schema::hasTable('event_event_genre') && Schema::hasTable('event_genres')) {
            $topGenre = DB::table('event_event_genre')
                ->join('events', 'events.id', '=', 'event_event_genre.event_id')
                ->join('event_genres', 'event_genres.id', '=', 'event_event_genre.event_genre_id')
                ->where('events.venue_id', $venueId)
                ->select('event_genres.name', DB::raw('COUNT(*) AS c'))
                ->groupBy('event_genres.name')
                ->orderByDesc('c')
                ->value('event_genres.name') ?? '-';
        }

        return [
            Stat::make('Events here', number_format($eventsCount)),
            Stat::make('Top Category', $topCategory),
            Stat::make('Top Genre', $topGenre),
        ];
    }

    protected int|string|array $columnSpan = 'full';
}
