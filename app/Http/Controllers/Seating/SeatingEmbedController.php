<?php

namespace App\Http\Controllers\Seating;

use App\Http\Controllers\Api\MarketplaceClient\Organizer\EventsController as OrganizerEventsController;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Seating\EventSeat;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatingLayout;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the canvas-based seating widget embedded inside a React Native
 * WebView for POS sales. Two endpoints:
 *
 *   POST /api/marketplace-client/seating/embed-token
 *        Issues a short-lived signed URL for the WebView to load. Sanctum
 *        authentication required (the mobile app's existing organizer or
 *        venue-owner token). Returns `{ url }`.
 *
 *   GET  /seating/embed/{event}
 *        Renders the Blade page with all seating data inlined as JSON —
 *        zero network round-trips on first paint. Verifies the HMAC token
 *        from the query string before rendering. Subscribes to Reverb on
 *        `event.{event_id}.seats` for real-time status sync.
 */
class SeatingEmbedController extends Controller
{
    /**
     * Mint a signed embed URL. Token contains {event_id, ticket_type_id?, expires}
     * HMAC'd with APP_KEY. Mobile passes the URL straight to the WebView.
     */
    public function issueToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'ticket_type_id' => 'nullable|integer|exists:ticket_types,id',
        ]);

        $event = Event::find($validated['event_id']);
        if (!$event || !$event->seating_layout_id) {
            return response()->json([
                'success' => false,
                'message' => 'Event has no seating layout',
            ], 404);
        }

        $payload = [
            'event_id' => (int) $validated['event_id'],
            'ticket_type_id' => $validated['ticket_type_id'] ?? null,
            'expires' => now()->addMinutes(30)->timestamp,
        ];

        $token = $this->signToken($payload);
        $baseUrl = rtrim(config('app.url'), '/');
        $qs = http_build_query(array_filter([
            'token' => $token,
            'tt' => $validated['ticket_type_id'] ?? null,
        ]));
        $url = $baseUrl . '/seating/embed/' . $validated['event_id'] . '?' . $qs;

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'expires_at' => date('c', $payload['expires']),
            ],
        ]);
    }

    /**
     * Render the embedded seating widget. All seat geometry + statuses come
     * pre-baked in the Blade view as JSON so the canvas can paint on first
     * frame — no XHR round-trip before the user can see the chart.
     */
    public function show(Request $request, int $event)
    {
        $token = $request->query('token');
        $payload = $this->verifyToken($token);

        if (!$payload || (int) ($payload['event_id'] ?? 0) !== (int) $event) {
            abort(403, 'Invalid or expired seating embed token');
        }

        $ticketTypeId = $request->query('tt');
        $data = $this->buildSeatingData((int) $event, $ticketTypeId ? (int) $ticketTypeId : null);

        if (!$data) {
            abort(404, 'Seating layout not available for this event');
        }

        return response()
            ->view('seating.embed', [
                'eventId' => (int) $event,
                'ticketTypeId' => $ticketTypeId ? (int) $ticketTypeId : null,
                'seating' => $data,
                'reverbConfig' => $this->reverbClientConfig(),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * Build the same data shape that /organizer/events/{id}/seating-map
     * returns, so the canvas renderer can stay agnostic about which API
     * surface it consumes.
     */
    protected function buildSeatingData(int $eventId, ?int $ticketTypeId): ?array
    {
        $eventModel = Event::find($eventId);
        if (!$eventModel || !$eventModel->seating_layout_id) {
            return null;
        }

        $layout = EventSeatingLayout::where('event_id', $eventModel->id)
            ->published()
            ->latest('published_at')
            ->first();

        if (!$layout) {
            return null;
        }

        $geometry = $layout->json_geometry;
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }

        $canvas = $geometry['canvas'] ?? ['width' => 1000, 'height' => 800];
        $sections = $geometry['sections'] ?? [];

        // Fallback: backfill section positions from SeatingLayout if the
        // snapshot is missing them (legacy snapshots before positions were
        // baked into json_geometry).
        $seatingLayout = SeatingLayout::with(['sections.rows'])->find($eventModel->seating_layout_id);
        if (!empty($sections) && $seatingLayout && !isset($sections[0]['x'])) {
            $canvas = [
                'width' => $seatingLayout->canvas_w ?? 1000,
                'height' => $seatingLayout->canvas_h ?? 800,
            ];
            $sectionPositions = [];
            foreach ($seatingLayout->sections as $s) {
                $sectionPositions[$s->name] = [
                    'x' => (int) $s->x_position,
                    'y' => (int) $s->y_position,
                    'width' => (int) $s->width,
                    'height' => (int) $s->height,
                    'rotation' => (float) ($s->rotation ?? 0),
                ];
            }
            foreach ($sections as &$sec) {
                $pos = $sectionPositions[$sec['name']] ?? null;
                if ($pos) {
                    $sec['x'] = $pos['x'];
                    $sec['y'] = $pos['y'];
                    $sec['width'] = $pos['width'];
                    $sec['height'] = $pos['height'];
                    $sec['rotation'] = $pos['rotation'];
                }
            }
            unset($sec);
        }

        // Seat statuses
        $eventSeats = EventSeat::where('event_seating_id', $layout->id)
            ->get(['seat_uid', 'status'])
            ->keyBy('seat_uid');

        // Ticket types with row/section assignments
        $ticketTypes = TicketType::where('event_id', $eventModel->id)
            ->whereIn('status', ['active', 'on_sale', 'published'])
            ->with(['seatingRows:id', 'seatingSections:id,name'])
            ->get();

        $rowIdMap = [];
        $sectionNameToId = [];
        if ($seatingLayout) {
            foreach ($seatingLayout->sections as $s) {
                $sectionNameToId[$s->name] = $s->id;
                foreach ($s->rows as $r) {
                    $rowIdMap[$s->name . '|' . $r->label] = $r->id;
                }
            }
        }

        // Sort so POS ticket types (is_entry_ticket=true) are iterated LAST.
        // Some organizers create twin TTs per category — one online and one
        // POS — assigned to the SAME rows. When both claim a row, the
        // last-write of `$rowIdToTT[$row->id] = $tt` decides which one the
        // mobile sees. PostgreSQL doesn't guarantee row order without an
        // ORDER BY, so without this sort the seats randomly fall on the
        // non-POS twin and become unselectable in the app. By iterating
        // POS LAST we guarantee the POS twin always wins the overwrite.
        $sortedTicketTypes = $ticketTypes->sortBy(fn ($tt) => ($tt->is_entry_ticket ?? false) ? 1 : 0)->values();

        $rowIdToTT = [];
        $sectionNameToTT = [];
        foreach ($sortedTicketTypes as $tt) {
            foreach ($tt->seatingRows as $row) {
                $rowIdToTT[$row->id] = $tt;
            }
            foreach ($tt->seatingSections as $section) {
                $sectionNameToTT[$section->name] = $tt;
            }
        }

        // Merge status + ticket-type id + price into each seat. Frontend
        // colors seats by ticket type and uses the price for the cart total.
        foreach ($sections as &$sec) {
            foreach ($sec['rows'] as &$row) {
                foreach ($row['seats'] as &$seat) {
                    $uid = $seat['seat_uid'];
                    $seat['status'] = $eventSeats->get($uid)?->status ?? 'available';

                    $tt = null;
                    $rowKey = $sec['name'] . '|' . $row['label'];
                    if (isset($rowIdMap[$rowKey]) && isset($rowIdToTT[$rowIdMap[$rowKey]])) {
                        $tt = $rowIdToTT[$rowIdMap[$rowKey]];
                    }
                    if (!$tt && isset($sectionNameToTT[$sec['name']])) {
                        $tt = $sectionNameToTT[$sec['name']];
                    }
                    if ($tt) {
                        $seat['ticket_type_id'] = (int) $tt->id;
                        $seat['price'] = (float) ($tt->display_price ?? (($tt->price_cents ?? 0) / 100));
                        $seat['ticket_type_color'] = $tt->color ?? null;
                    }
                }
                unset($seat);
            }
            unset($row);
        }
        unset($sec);

        // is_entry_ticket defaults to FALSE — only explicitly POS-flagged
        // tiers may be sold from the mobile app. Previously this defaulted
        // to true, which let operators sell e.g. online-only / Tickera-
        // imported tickets through the seating map. Major bug.
        $ticketTypeOut = $ticketTypes->map(fn ($t) => [
            'id' => (int) $t->id,
            'name' => $t->name,
            'price' => (float) ($t->display_price ?? (($t->price_cents ?? 0) / 100)),
            'color' => $t->color ?? null,
            'is_entry_ticket' => (bool) ($t->is_entry_ticket ?? false),
        ])->values()->toArray();

        return [
            'event_seating_id' => $layout->id,
            'canvas' => $canvas,
            'sections' => array_values($sections),
            'ticket_types' => $ticketTypeOut,
            'selected_ticket_type_id' => $ticketTypeId,
        ];
    }

    /**
     * Reverb client config so the embed page can subscribe with the same
     * env vars the daemon uses on the server. Returned as a small JSON
     * object with the public bits (key + host + port + scheme).
     */
    protected function reverbClientConfig(): array
    {
        // Use config() not env() — Laravel's config:cache invalidates env()
        // in the request lifecycle (returns the default), which would leave
        // the embed page stuck on "real-time off" in production.
        return [
            'driver' => config('broadcasting.default'),
            'app_key' => config('reverb-client.app_key'),
            'host' => config('reverb-client.host'),
            'port' => (int) config('reverb-client.port'),
            'scheme' => config('reverb-client.scheme'),
            'path' => config('reverb-client.path'),
        ];
    }

    protected function signToken(array $payload): string
    {
        $encoded = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $encoded, config('app.key'));
        return $encoded . '.' . $signature;
    }

    protected function verifyToken(?string $token): ?array
    {
        if (!$token || !str_contains($token, '.')) {
            return null;
        }
        [$encoded, $signature] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $encoded, config('app.key'));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $payload = json_decode(base64_decode($encoded), true);
        if (!is_array($payload)) {
            return null;
        }
        if (($payload['expires'] ?? 0) < now()->timestamp) {
            return null;
        }
        return $payload;
    }
}
