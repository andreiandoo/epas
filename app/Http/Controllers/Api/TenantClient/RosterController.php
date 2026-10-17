<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TenantArtist;
use App\Models\TenantBookingRequest;
use App\Services\TenantMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Rosterul unei agenții de artiști, pentru site-urile de tenant:
 *   GET  /tenant-client/roster              artiștii (profil scurt + următorul concert)
 *   GET  /tenant-client/roster/{slug}       un artist: bio, galerie, linkuri, video, concerte
 *   POST /tenant-client/booking-requests    cerere de booking (pentru un artist sau generală)
 * Artiștii sunt TenantArtist (fără rolurile „fighter”); profilul stă în meta: kind, genres,
 * city, country, tagline, territories, bookable, featured, hero_url, links{}, youtube_id,
 * quote{text,source}, listeners; traducerile în meta.i18n.{locale}. Concertele vin din
 * pivotul event_tenant_artist.
 */
class RosterController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenantId = $resolved['tenant']->id;
        $locale = app()->getLocale();
        $artists = TenantArtist::where('tenant_id', $tenantId)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', 'fighter'))
            ->orderBy('name')->get();
        $next = $this->nextShows($artists->pluck('id')->all());

        return response()->json(['success' => true, 'data' => [
            'artists' => $artists->map(fn ($a) => $this->profile($a, $locale) + ['next_show' => $next[$a->id] ?? null])->values(),
        ]])->header('Cache-Control', 'public, max-age=120, s-maxage=300');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $locale = app()->getLocale();
        $a = TenantArtist::where('tenant_id', $resolved['tenant']->id)->where('status', 'active')->where('slug', $slug)->first();
        if (! $a) {
            return response()->json(['success' => false, 'error' => 'Artist not found'], 404);
        }
        $events = $a->events()->where('is_published', true)->with(['venue', 'ticketTypes' => fn ($q) => $q->where('status', 'active')])->get();
        $today = now()->toDateString();
        $upcoming = $events->filter(fn ($e) => $this->day($e) >= $today)->sortBy(fn ($e) => $this->day($e))->values();
        $past = $events->filter(fn ($e) => $this->day($e) < $today)->sortByDesc(fn ($e) => $this->day($e))->take(12)->values();

        return response()->json(['success' => true, 'data' => $this->profile($a, $locale, true) + [
            'upcoming' => $upcoming->map(fn ($e) => $this->event($e, $locale))->all(),
            'past' => $past->map(fn ($e) => $this->event($e, $locale))->all(),
        ]]);
    }

    public function booking(Request $request): JsonResponse
    {
        $data = $request->validate([
            'artist' => 'nullable|string|max:190',
            'name' => 'required|string|max:150',
            'email' => 'required|email:rfc|max:190',
            'phone' => 'nullable|string|max:40',
            'company' => 'nullable|string|max:190',
            'event_type' => 'nullable|in:' . implode(',', array_keys(TenantBookingRequest::EVENT_TYPES)),
            'event_date' => 'nullable|date|after_or_equal:today',
            'city' => 'nullable|string|max:120',
            'venue' => 'nullable|string|max:190',
            'capacity' => 'nullable|integer|min:1|max:500000',
            'budget' => 'nullable|string|max:60',
            'message' => 'nullable|string|max:3000',
            'locale' => 'nullable|in:ro,en',
            'consent' => 'accepted',
        ]);
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $tenant = $resolved['tenant'];
        $artist = ! empty($data['artist'])
            ? TenantArtist::where('tenant_id', $tenant->id)->where('slug', $data['artist'])->first()
            : null;

        $req = TenantBookingRequest::create([
            'tenant_id' => $tenant->id, 'tenant_artist_id' => $artist?->id, 'artist_name' => $artist?->name,
            'name' => trim($data['name']), 'email' => mb_strtolower(trim($data['email'])), 'phone' => $data['phone'] ?? null,
            'company' => $data['company'] ?? null, 'event_type' => $data['event_type'] ?? null, 'event_date' => $data['event_date'] ?? null,
            'city' => $data['city'] ?? null, 'venue' => $data['venue'] ?? null, 'capacity' => $data['capacity'] ?? null,
            'budget' => $data['budget'] ?? null, 'message' => $data['message'] ?? null, 'locale' => $data['locale'] ?? 'ro',
            'status' => 'new', 'consent_ip' => $request->header('X-Visitor-IP') ?: $request->ip(),
        ]);
        $this->notify($tenant, $req);

        return response()->json(['success' => true]);
    }

    /* ---------------------------------------------------------------- formatare */

    private function profile(TenantArtist $a, string $locale, bool $detail = false): array
    {
        $m = (array) ($a->meta ?? []);
        $tr = (array) ($m['i18n'][$locale] ?? []);
        $url = fn ($p) => $p ? (preg_match('#^https?://#', $p) ? $p : Storage::disk('public')->url($p)) : null;
        $out = [
            'id' => $a->id,
            'slug' => $a->slug ?: (string) $a->id,
            'name' => $a->name,
            'kind' => $m['kind'] ?? 'solo',
            'genres' => array_values(array_filter((array) ($tr['genres'] ?? $m['genres'] ?? []))),
            'city' => $m['city'] ?? null,
            'country' => $m['country'] ?? null,
            'tagline' => $tr['tagline'] ?? ($m['tagline'] ?? null),
            'territories' => array_values(array_filter((array) ($tr['territories'] ?? $m['territories'] ?? []))),
            'bookable' => (bool) ($m['bookable'] ?? true),
            'featured' => (bool) ($m['featured'] ?? false),
            'photo' => $url($a->photo_url),
            'hero' => $url($m['hero_url'] ?? null) ?: $url($a->photo_url),
            'listeners' => isset($m['listeners']) ? (int) $m['listeners'] : null,
        ];
        if ($detail) {
            $out += [
                'bio' => $a->getTranslation('bio', $locale) ?: $a->getTranslation('bio', 'ro'),
                'gallery' => collect($a->gallery ?? [])->filter()->map($url)->values()->all(),
                'links' => array_filter((array) ($m['links'] ?? []), fn ($u) => is_string($u) && preg_match('#^https?://#i', $u)),
                'youtube_id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($m['youtube_id'] ?? '')) ?: null,
                'quote' => ! empty($m['quote']['text']) ? ['text' => $tr['quote'] ?? $m['quote']['text'], 'source' => $m['quote']['source'] ?? null] : null,
            ];
        }
        return $out;
    }

    private function event(Event $e, string $locale): array
    {
        $types = $e->ticketTypes->filter(fn ($t) => ! ($t->meta['is_invitation'] ?? false));
        return [
            'id' => $e->id, 'slug' => $e->slug, 'title' => $e->getTranslation('title', $locale),
            'start_date' => $this->day($e), 'start_time' => $e->start_time ? substr((string) $e->start_time, 0, 5) : null,
            'venue' => $e->venue ? ['name' => $e->venue->getTranslation('name', $locale), 'city' => $e->venue->city, 'country' => $e->venue->country] : null,
            'poster_url' => $e->poster_url ? (preg_match('#^https?://#', $e->poster_url) ? $e->poster_url : Storage::disk('public')->url($e->poster_url)) : null,
            'is_sold_out' => (bool) $e->is_sold_out, 'is_cancelled' => (bool) $e->is_cancelled,
            'external_ticket_url' => $e->external_ticket_url ?: null, 'external_ticket_label' => $e->external_ticket_label ?: null,
            'price_from' => $types->isEmpty() ? null : (float) $types->min('price_max'),
        ];
    }

    private function day(Event $e): string
    {
        return (string) (($e->event_date ?? $e->start_date)?->toDateString() ?? '');
    }

    /** Următorul concert al fiecărui artist: [artist_id => {slug, date, city}]. */
    private function nextShows(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $rows = Event::query()->join('event_tenant_artist as p', 'p.event_id', '=', 'events.id')
            ->whereIn('p.tenant_artist_id', $ids)->where('events.is_published', true)
            ->whereDate('events.event_date', '>=', now()->toDateString())
            ->orderBy('events.event_date')->with('venue')
            ->get(['events.*', 'p.tenant_artist_id as roster_artist_id']);
        $out = [];
        foreach ($rows as $e) {
            $aid = (int) $e->roster_artist_id;
            if (! isset($out[$aid])) {
                $out[$aid] = ['slug' => $e->slug, 'date' => $this->day($e), 'city' => $e->venue?->city];
            }
        }
        return $out;
    }

    /** Agenția primește cererea pe email; solicitantul, o confirmare. Eșecul nu pierde cererea (e salvată). */
    private function notify($tenant, TenantBookingRequest $r): void
    {
        $name = $tenant->public_name ?: $tenant->name;
        $to = $tenant->contact_email ?: $tenant->owner?->email;
        $rows = [
            'Artist' => $r->artist_name ?: '—', 'Nume' => $r->name, 'Email' => $r->email, 'Telefon' => $r->phone, 'Companie' => $r->company,
            'Tip eveniment' => TenantBookingRequest::EVENT_TYPES[$r->event_type] ?? $r->event_type, 'Data' => $r->event_date?->format('d.m.Y'),
            'Oraș' => $r->city, 'Locație' => $r->venue, 'Capacitate' => $r->capacity, 'Buget' => $r->budget,
        ];
        $table = '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:15px;color:#1B2140;">';
        foreach (array_filter($rows, fn ($v) => $v !== null && $v !== '') as $k => $v) {
            $table .= '<tr><td style="padding:7px 0;color:#5B6488;width:38%;vertical-align:top;">' . e($k) . '</td><td style="padding:7px 0;">' . e((string) $v) . '</td></tr>';
        }
        $table .= '</table>' . ($r->message ? '<p style="margin:18px 0 0;font-size:15px;line-height:1.6;color:#1B2140;white-space:pre-line;">' . e($r->message) . '</p>' : '');
        try {
            if ($to) {
                $html = DemoStorefrontController::mailLayout($tenant, 'Cerere de booking nouă', $table, null, null, 'Răspunde direct la acest email: ajunge la ' . e($r->email) . '. Cererea e și în panou, la Cereri de booking.');
                app(TenantMailService::class)->send($tenant, function ($m) use ($to, $html, $r, $name) {
                    $m->to($to)->from(config('mail.from.address'), $name)->replyTo($r->email, $r->name)
                        ->subject('Cerere de booking' . ($r->artist_name ? ' — ' . $r->artist_name : '') . ($r->event_date ? ', ' . $r->event_date->format('d.m.Y') : ''))
                        ->html($html);
                });
            }
            $en = $r->locale === 'en';
            $body = '<p style="margin:0;font-size:16px;line-height:1.6;color:#1B2140;">' . ($en
                ? 'Thanks, ' . e($r->name) . '! We got your booking request' . ($r->artist_name ? ' for <b>' . e($r->artist_name) . '</b>' : '') . ' and we’ll get back to you within two working days.'
                : 'Mulțumim, ' . e($r->name) . '! Am primit cererea ta de booking' . ($r->artist_name ? ' pentru <b>' . e($r->artist_name) . '</b>' : '') . ' și revenim în cel mult două zile lucrătoare.') . '</p>';
            $html2 = DemoStorefrontController::mailLayout($tenant, $en ? 'We got your request' : 'Am primit cererea', $body);
            app(TenantMailService::class)->send($tenant, function ($m) use ($r, $html2, $name, $en) {
                $m->to($r->email)->from(config('mail.from.address'), $name)
                    ->subject($en ? 'Your booking request — ' . $name : 'Cererea ta de booking — ' . $name)->html($html2);
            });
        } catch (\Throwable $e) {
            Log::warning('Booking request email failed', ['request_id' => $r->id, 'error' => $e->getMessage()]);
        }
    }
}
