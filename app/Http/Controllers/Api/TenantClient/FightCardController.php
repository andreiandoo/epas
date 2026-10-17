<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventBout;
use App\Models\TenantArtist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Galele de lupte pentru site-urile de tenant:
 *   GET /tenant-client/events/{slug}/fight-card   meciurile galei, pe secțiuni
 *   GET /tenant-client/fighters                   luptătorii, cu record și ultimele meciuri
 * Luptătorii sunt TenantArtist cu role = fighter; profilul sportiv stă în meta
 * (nickname, country, record {w,l,d}, weight_class, discipline, height_cm,
 * reach_cm, stance, team; traduceri în meta.i18n.{locale}).
 */
class FightCardController extends Controller
{
    use ResolvesTenant;

    public function show(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;
        $event = Event::where('tenant_id', $tenantId)->where('slug', $slug)->first();
        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Event not found'], 404);
        }

        $bouts = EventBout::where('event_id', $event->id)->where('status', '!=', 'cancelled')
            ->with(['fighterA', 'fighterB'])
            ->get()
            ->sortBy(fn (EventBout $b) => [EventBout::sectionRank($b->card_section), $b->sort_order])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $event->id,
                'bouts' => $bouts->map(fn (EventBout $b) => $this->bout($b))->values(),
            ],
        ])->header('Cache-Control', 'public, max-age=120, s-maxage=300');
    }

    public function fighters(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;

        $fighters = TenantArtist::where('tenant_id', $tenantId)->where('role', 'fighter')->where('status', 'active')
            ->orderBy('name')->get();

        // Istoricul: meciurile terminate ale fiecărui luptător, cele mai recente primele
        $history = EventBout::where('tenant_id', $tenantId)->where('status', 'completed')
            ->with(['event:id,slug,title,event_date,range_start_date,duration_mode', 'fighterA:id,name', 'fighterB:id,name'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'fighters' => $fighters->map(fn (TenantArtist $f) => $this->fighter($f) + [
                    'fights' => $this->historyFor($f->id, $history),
                ])->values(),
            ],
        ])->header('Cache-Control', 'public, max-age=120, s-maxage=300');
    }

    private function bout(EventBout $b): array
    {
        $locale = app()->getLocale();
        $tr = fn (string $field) => $b->meta['i18n'][$locale][$field] ?? $b->{$field};

        return [
            'id' => $b->id,
            'section' => $b->card_section,
            'order' => $b->sort_order,
            'weight_class' => $tr('weight_class'),
            'discipline' => $b->discipline,
            'rounds' => $b->rounds,
            'round_minutes' => $b->round_minutes,
            'title_fight' => $b->is_title_fight,
            'title_name' => $tr('title_name'),
            'status' => $b->status,
            'result' => $b->status === 'completed' ? [
                'winner' => $b->winner,
                'method' => $tr('method'),
                'round' => $b->result_round,
                'time' => $b->result_time,
            ] : null,
            'a' => $b->fighterA ? $this->fighter($b->fighterA) : ['id' => null, 'name' => $b->fighter_a_name ?: 'TBA'],
            'b' => $b->fighterB ? $this->fighter($b->fighterB) : ['id' => null, 'name' => $b->fighter_b_name ?: 'TBA'],
        ];
    }

    private function fighter(TenantArtist $f): array
    {
        $locale = app()->getLocale();
        $m = (array) ($f->meta ?? []);
        $i18n = (array) ($m['i18n'][$locale] ?? []);
        $rec = (array) ($m['record'] ?? []);

        return [
            'id' => $f->id,
            'slug' => $f->slug,
            'name' => $f->name,
            'nickname' => $i18n['nickname'] ?? ($m['nickname'] ?? null),
            'photo' => $this->url($f->photo_url),
            'cutout' => $this->url($m['cutout_url'] ?? null),
            'country' => $m['country'] ?? null,
            'record' => ['w' => (int) ($rec['w'] ?? 0), 'l' => (int) ($rec['l'] ?? 0), 'd' => (int) ($rec['d'] ?? 0)],
            'weight_class' => $i18n['weight_class'] ?? ($m['weight_class'] ?? null),
            'discipline' => $m['discipline'] ?? null,
            'height_cm' => isset($m['height_cm']) ? (int) $m['height_cm'] : null,
            'reach_cm' => isset($m['reach_cm']) ? (int) $m['reach_cm'] : null,
            'stance' => $i18n['stance'] ?? ($m['stance'] ?? null),
            'team' => $m['team'] ?? null,
            'age' => $f->birth_date ? $f->birth_date->age : null,
            'is_champion' => ! empty($m['is_champion']),
            'bio' => $f->getTranslation('bio', $locale),
        ];
    }

    private function historyFor(int $id, Collection $history): array
    {
        $locale = app()->getLocale();

        return $history->filter(fn ($b) => $b->fighter_a_id === $id || $b->fighter_b_id === $id)
            ->sortByDesc(fn ($b) => $b->event?->event_date ?? $b->event?->range_start_date)
            ->take(5)
            ->map(function (EventBout $b) use ($id, $locale) {
                $side = $b->fighter_a_id === $id ? 'a' : 'b';
                $opp = $side === 'a' ? ($b->fighterB?->name ?? $b->fighter_b_name) : ($b->fighterA?->name ?? $b->fighter_a_name);

                return [
                    'event' => $b->event?->getTranslation('title', $locale),
                    'event_slug' => $b->event?->slug,
                    'date' => ($b->event?->event_date ?? $b->event?->range_start_date)?->toDateString(),
                    'opponent' => $opp,
                    'outcome' => in_array($b->winner, ['draw', 'nc'], true) ? $b->winner : ($b->winner === $side ? 'win' : 'loss'),
                    'method' => $b->meta['i18n'][$locale]['method'] ?? $b->method,
                    'round' => $b->result_round,
                ];
            })->values()->all();
    }

    private function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return preg_match('#^https?://#', $path) ? $path : Storage::disk('public')->url($path);
    }
}
