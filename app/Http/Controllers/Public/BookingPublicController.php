<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\ArtistBookingListing;
use App\Models\ArtistBookingRequest;
use App\Services\ExtendedArtist\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint-uri publice pentru Booking Marketplace:
 *  - Submit form de booking de pe profilul public al artistului (no auth)
 *  - Guest-side conversation reply (auth via signed guest_token)
 */
class BookingPublicController extends Controller
{
    public function __construct(private readonly BookingService $booking)
    {
    }

    /**
     * GET /api/marketplace-client/public/artist/{slug}/booking-status
     * Returns whether the artist has an active booking listing (used by
     * artist-single page to conditionally show the "Cere booking" CTA).
     */
    public function listingStatus(Request $request, string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)->first();
        if (!$artist) {
            return response()->json(['active' => false], 200);
        }
        $listing = ArtistBookingListing::where('artist_id', $artist->id)->first();
        $isActive = $listing && $listing->status === 'active';

        if (!$isActive) {
            return response()->json(['active' => false]);
        }

        $description = $listing->description;
        if (is_array($description)) {
            $description = $description['ro'] ?? $description['en'] ?? reset($description) ?: null;
        }

        return response()->json([
            'active' => true,
            'response_target_hours' => $listing->response_target_hours,
            'min_fee_ron' => $listing->show_fee_publicly ? $listing->min_fee_ron : null,
            'max_fee_ron' => $listing->show_fee_publicly ? $listing->max_fee_ron : null,
            'show_fee_publicly' => (bool) $listing->show_fee_publicly,
            'event_types' => $listing->event_types ?? [],
            'standard_set_length_min' => $listing->standard_set_length_min,
            'standard_min_audience' => $listing->standard_min_audience,
            'standard_max_audience' => $listing->standard_max_audience,
            'requires_soundcheck' => (bool) $listing->requires_soundcheck,
            'soundcheck_min_minutes' => $listing->soundcheck_min_minutes,
            'requires_backline' => (bool) $listing->requires_backline,
            'requires_catering' => (bool) $listing->requires_catering,
            'requires_accommodation' => (bool) $listing->requires_accommodation,
            'requires_transport' => (bool) $listing->requires_transport,
            'max_distance_km' => $listing->max_distance_km,
            'description' => $description,
        ]);
    }

    /**
     * POST /api/public/artist/{slug}/booking-request
     * Throttle: 5/1 (vezi routes/api.php)
     */
    public function submitRequest(Request $request, string $slug): JsonResponse
    {
        // Honeypot — hidden form input that bots auto-fill. Silent success
        // response so the bot can't tell the submission was dropped.
        if (!empty(trim((string) $request->input('website_url', '')))) {
            return response()->json([
                'message' => 'Cererea a fost trimisă cu succes. Verifică emailul pentru confirmare.',
            ]);
        }

        $artist = Artist::where('slug', $slug)->first();
        if (!$artist) {
            return response()->json(['error' => 'Artist not found'], 404);
        }

        $listing = ArtistBookingListing::where('artist_id', $artist->id)->first();
        if (!$listing || $listing->status !== 'active') {
            return response()->json(['error' => 'Artistul nu primește cereri momentan.'], 422);
        }

        $validated = $request->validate([
            'guest_name' => 'required|string|max:120',
            'guest_email' => 'required|email|max:180',
            'guest_phone' => 'nullable|string|max:40',
            'guest_company' => 'nullable|string|max:180',
            'guest_company_type' => 'nullable|in:organizator,agentie,venue,persoana',
            'event_date' => 'required|date|after:today',
            'event_time' => 'nullable|string|max:8',
            'event_venue_name' => 'nullable|string|max:180',
            'event_city' => 'required|string|max:120',
            'event_country' => 'nullable|string|max:60',
            'event_type' => 'required|in:concert,festival,private,corporate,wedding,club',
            'audience_size' => 'nullable|integer|min:1|max:200000',
            'proposed_fee_ron' => 'required|integer|min:0|max:10000000',
            'proposed_set_length_min' => 'nullable|integer|min:15|max:600',
            'conditions' => 'nullable|array',
            'conditions.*' => 'string|max:30',
            'initial_message' => 'required|string|min:10|max:5000',
            'consent' => 'required|accepted', // GDPR consent checkbox
        ]);

        unset($validated['consent']);

        try {
            $bookingRequest = $this->booking->createRequestFromGuest($artist, $validated);
            return response()->json([
                'success' => true,
                'message' => 'Cererea a fost trimisă cu succes. Vei primi un email de confirmare.',
                'guest_token' => $bookingRequest->guest_token,
                'conversation_url' => '/booking/conversation/' . $bookingRequest->guest_token,
            ]);
        } catch (\Throwable $e) {
            Log::error('booking submit failed', ['error' => $e->getMessage(), 'artist' => $slug]);
            return response()->json(['error' => 'Nu am putut trimite cererea. Reîncearcă în câteva momente.'], 500);
        }
    }

    /**
     * GET conversație guest (verificare token + thread).
     */
    public function viewConversation(Request $request, string $token): JsonResponse
    {
        $req = ArtistBookingRequest::where('guest_token', $token)
            ->with('messages', 'artist')
            ->first();
        if (!$req) {
            return response()->json(['error' => 'Conversație inexistentă sau expirată.'], 404);
        }

        $artistName = $req->artist?->name ?? 'Artist';
        if (is_array($artistName)) {
            $artistName = $artistName['ro'] ?? $artistName['en'] ?? reset($artistName) ?: 'Artist';
        }

        return response()->json([
            'data' => [
                'id' => $req->id,
                'status' => $req->status,
                'artist' => [
                    'name' => $artistName,
                    'slug' => $req->artist?->slug,
                ],
                'guest' => [
                    'name' => $req->guest_name,
                    'email' => $req->guest_email,
                ],
                'event' => [
                    'date_iso' => $req->event_date?->toDateString(),
                    'date' => $req->event_date?->translatedFormat('j M Y'),
                    'time' => $req->event_time,
                    'venue' => $req->event_venue_name,
                    'city' => $req->event_city,
                    'country' => $req->event_country,
                    'type' => $req->event_type,
                    'audience' => $req->audience_size,
                    'fee_ron' => $req->proposed_fee_ron,
                    'set_length_min' => $req->proposed_set_length_min,
                    'conditions' => $req->conditions ?? [],
                ],
                'initial_message' => $req->initial_message,
                'final_terms' => $req->final_terms,
                'thread' => $req->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'sender_type' => $m->sender_type,
                    'type' => $m->type,
                    'body' => $m->body,
                    'counter_terms' => $m->counter_terms,
                    'time' => $m->created_at?->translatedFormat('j M, H:i'),
                ])->all(),
            ],
        ]);
    }

    /**
     * GET ICS feed pentru calendarul artistului. Token-ul e secret în URL.
     * Subscribed direct de Google Calendar / Apple Calendar / Outlook.
     * Returnează text/calendar.
     */
    public function icalFeed(Request $request, string $token)
    {
        $listing = ArtistBookingListing::where('ical_token', $token)->first();
        if (!$listing) {
            return response('NOT FOUND', 404);
        }
        $artist = Artist::find($listing->artist_id);
        if (!$artist) {
            return response('NOT FOUND', 404);
        }

        $body = $this->booking->buildIcsFeed($artist);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="booking-' . $artist->id . '.ics"',
            'Cache-Control' => 'public, max-age=900', // 15 min cache pentru clienții iCal
        ]);
    }

    /**
     * POST guest message via signed token.
     * Throttle: 30/1 (vezi routes/api.php)
     */
    public function postGuestMessage(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:message,counter,accept,reject',
            'body' => 'nullable|string|max:5000',
            'counter_terms' => 'nullable|array',
            'counter_terms.fee_ron' => 'nullable|integer|min:0|max:10000000',
            'counter_terms.set_length_min' => 'nullable|integer|min:15|max:600',
            'counter_terms.event_date' => 'nullable|date',
        ]);

        try {
            $result = $this->booking->addGuestMessage(
                $token,
                $validated['type'],
                $validated['body'] ?? null,
                $validated['counter_terms'] ?? null
            );
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Log::warning('booking guest message failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
