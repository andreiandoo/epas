<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\FestivalDay;
use App\Models\FestivalLineupSlot;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * GET /tenant-client/events/{slug}/lineup
 *
 * Programul public al unui festival (modulul festival existent: festival_days,
 * stages, festival_lineup_slots), pentru site-urile de tenant: zilele, scenele
 * și cine cântă unde și când. Doar citire, cacheabil.
 *
 * Traduceri: zilele, scenele și sloturile pot avea meta.i18n.{locale}.{câmp}
 * (name / description); bio-ul artistului e Translatable.
 */
class FestivalLineupController extends Controller
{
    use ResolvesTenant;

    public function show(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;
        $locale = app()->getLocale();

        $event = Event::where('tenant_id', $tenantId)->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Event not found'], 404);
        }

        $days = FestivalDay::where('tenant_id', $tenantId)->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')->orderBy('sort_order')->get();

        $slots = $days->isEmpty() ? collect() : FestivalLineupSlot::whereIn('festival_day_id', $days->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->with(['artist.artistGenres', 'artist.artistTypes'])
            ->orderBy('start_time')
            ->get();

        $stages = Stage::whereIn('id', $slots->pluck('stage_id')->unique())
            ->orderBy('sort_order')->orderBy('id')->get();

        $tr = fn ($model, string $field) => $model->meta['i18n'][$locale][$field] ?? $model->{$field};

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $event->id,
                'days' => $days->map(fn (FestivalDay $d) => [
                    'id' => $d->id,
                    'name' => $tr($d, 'name'),
                    'date' => $d->date?->toDateString(),
                    'gates_open' => $d->gates_open ? substr((string) $d->gates_open, 0, 5) : null,
                    'gates_close' => $d->gates_close ? substr((string) $d->gates_close, 0, 5) : null,
                    'description' => $tr($d, 'description'),
                    'image' => $this->url($d->image_url),
                ])->values(),
                'stages' => $stages->map(fn (Stage $s) => [
                    'id' => $s->id,
                    'name' => $tr($s, 'name'),
                    'slug' => $s->slug,
                    'type' => $s->stage_type,
                    'description' => $tr($s, 'description'),
                    'image' => $this->url($s->image_url),
                    'color' => $s->meta['color'] ?? null,
                    'capacity' => $s->capacity,
                ])->values(),
                'slots' => $slots->map(function (FestivalLineupSlot $slot) use ($tr, $locale) {
                    $a = $slot->is_secret_guest ? null : $slot->artist;
                    return [
                        'id' => $slot->id,
                        'day_id' => $slot->festival_day_id,
                        'stage_id' => $slot->stage_id,
                        'start' => substr((string) $slot->start_time, 0, 5),
                        'end' => substr((string) $slot->end_time, 0, 5),
                        'type' => $slot->slot_type,
                        'tier' => $slot->display_tier,
                        'position' => (int) $slot->display_position,
                        'headliner' => (bool) $slot->is_headliner,
                        'secret' => (bool) $slot->is_secret_guest,
                        'name' => $slot->is_secret_guest ? null : ($a?->name ?? $slot->custom_artist_name),
                        'description' => $tr($slot, 'description'),
                        'image' => $this->url($slot->image_override_url ?: $a?->main_image_url),
                        'artist' => $a ? [
                            'id' => $a->id,
                            'slug' => $a->slug,
                            'bio' => $a->getTranslation('bio_html', $locale),
                            'portrait' => $this->url($a->portrait_url),
                            'country' => $a->country,
                            'genres' => $a->artistGenres->map(fn ($g) => $g->getTranslation('name', $locale))->values(),
                            'spotify_url' => $a->spotify_url,
                            'youtube_url' => $a->youtube_url,
                            'instagram_url' => $a->instagram_url,
                            'youtube_video_id' => collect($a->youtube_videos ?? [])->map(function ($v) {
                                $url = is_array($v) ? ($v['url'] ?? '') : (string) $v;
                                return preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/', $url, $m) ? $m[1] : null;
                            })->filter()->first(),
                        ] : null,
                    ];
                })->values(),
            ],
        ])->header('Cache-Control', 'public, max-age=120, s-maxage=300');
    }

    private function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return preg_match('#^https?://#', $path) ? $path : Storage::disk('public')->url($path);
    }
}
