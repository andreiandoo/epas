<?php

namespace App\Filament\Resources\Shorts\Pages;

use App\Filament\Resources\Shorts\ShortResource;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ListShorts extends ListRecords
{
    protected static string $resource = ShortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Owner-type filter tabs above the table. Counts are pulled from
     * a single GROUP BY query (owner_type) cached 30s so the tab
     * badges don't fire five separate COUNT(*) queries on every load.
     *
     * "Editorial" catches rows with owner_type NULL — shorts that
     * were created without an attached owner (curated feed content).
     */
    public function getTabs(): array
    {
        $counts = $this->getOwnerTypeCounts();

        $total = array_sum($counts);

        return [
            'all' => Tab::make('Toate')
                ->badge($total),

            'event' => Tab::make('Event')
                ->badge($counts[Event::class] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('owner_type', Event::class)),

            'artist' => Tab::make('Artist')
                ->badge($counts[Artist::class] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('owner_type', Artist::class)),

            'venue' => Tab::make('Venue')
                ->badge($counts[Venue::class] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('owner_type', Venue::class)),

            'editorial' => Tab::make('Editorial')
                ->badge($counts['__editorial__'] ?? 0)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('owner_type')),
        ];
    }

    /**
     * @return array<string, int>  Map of owner_type class name → count,
     *                             plus the '__editorial__' key for NULLs.
     */
    protected function getOwnerTypeCounts(): array
    {
        return Cache::remember('shorts:owner_type_counts', 30, function () {
            $rows = Short::query()
                ->selectRaw('COALESCE(owner_type, \'__editorial__\') AS bucket, COUNT(*) AS c')
                ->groupBy('bucket')
                ->pluck('c', 'bucket')
                ->toArray();

            return array_map('intval', $rows);
        });
    }
}
