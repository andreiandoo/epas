<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query()
            ->with(['tenant', 'eventTypes', 'eventGenres', 'tags', 'artists']);

        $parse = function ($value): array {
            if ($value === null || $value === '') return [[], []];
            if (is_string($value)) $value = array_filter(array_map('trim', explode(',', $value)));
            if (! is_array($value)) $value = [$value];
            $ids = []; $slugs = [];
            foreach ($value as $v) {
                if ($v === '' || $v === null) continue;
                if (is_numeric($v)) $ids[] = (int) $v; else $slugs[] = (string) $v;
            }
            return [$ids, $slugs];
        };

        // Event Types (ex-categories)
        [$typeIds, $typeSlugs] = $parse($request->input('type') ?? $request->input('event_type') ?? $request->input('eventTypes'));
        if ($typeIds || $typeSlugs) {
            $query->whereHas('eventTypes', function (Builder $q) use ($typeIds, $typeSlugs) {
                if ($typeIds)   $q->whereIn('event_types.id', $typeIds);
                if ($typeSlugs) $q->whereIn('event_types.slug', $typeSlugs);
            });
        }

        // Event Genres
        [$egIds, $egSlugs] = $parse($request->input('event_genre') ?? $request->input('eventGenres'));
        if ($egIds || $egSlugs) {
            $query->whereHas('eventGenres', function (Builder $q) use ($egIds, $egSlugs) {
                if ($egIds)   $q->whereIn('event_genres.id', $egIds);
                if ($egSlugs) $q->whereIn('event_genres.slug', $egSlugs);
            });
        }

        // Tags
        [$tagIds, $tagSlugs] = $parse($request->input('tag') ?? $request->input('tags'));
        if ($tagIds || $tagSlugs) {
            $query->whereHas('tags', function (Builder $q) use ($tagIds, $tagSlugs) {
                if ($tagIds)   $q->whereIn('event_tags.id', $tagIds);
                if ($tagSlugs) $q->whereIn('event_tags.slug', $tagSlugs);
            });
        }

        // Location (only if columns exist)
        if ($country = trim((string) $request->input('country', ''))) {
            if (schema_has_column('events', 'country')) $query->where('country', $country);
        }
        if ($state = trim((string) $request->input('state', ''))) {
            if (schema_has_column('events', 'state')) $query->where('state', $state);
        }
        if ($city = trim((string) $request->input('city', ''))) {
            if (schema_has_column('events', 'city')) $query->where('city', $city);
        }

        // Date window
        if ($from = $request->date('from')) {
            $query->where(function (Builder $q) use ($from) {
                if (schema_has_column('events', 'starts_at')) $q->orWhereDate('starts_at', '>=', $from);
                if (schema_has_column('events', 'event_date')) $q->orWhereDate('event_date', '>=', $from);
            });
        }
        if ($to = $request->date('to')) {
            $query->where(function (Builder $q) use ($to) {
                if (schema_has_column('events', 'ends_at')) $q->orWhereDate('ends_at', '<=', $to);
                if (schema_has_column('events', 'event_date')) $q->orWhereDate('event_date', '<=', $to);
            });
        }

        // Price range
        if ($minPrice = $request->input('min_price')) {
            $query->where(function (Builder $q) use ($minPrice) {
                if (schema_has_column('events', 'min_price')) {
                    $q->where('min_price', '>=', $minPrice);
                }
            });
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where(function (Builder $q) use ($maxPrice) {
                if (schema_has_column('events', 'min_price')) {
                    $q->where('min_price', '<=', $maxPrice);
                }
            });
        }

        $events = $query
            ->orderByDesc('created_at')
            ->paginate(24)
            ->appends($request->query());

        return view('public.events.index', [
            'events'  => $events,
            'filters' => $request->all(),
            'eventTypes' => \App\Models\EventType::orderBy('name')->get(),
            'eventGenres' => \App\Models\EventGenre::orderBy('name')->get(),
        ]);
    }
}

/**
 * Safe column-existence check without requiring doctrine/dbal.
 */
if (! function_exists('schema_has_column')) {
    function schema_has_column(string $table, string $column): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
