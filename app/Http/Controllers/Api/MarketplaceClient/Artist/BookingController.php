<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Artist;
use App\Models\ArtistBookingMessage;
use App\Models\MarketplaceArtistAccount;
use App\Services\ExtendedArtist\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Booking Marketplace (Modulul 4 din Extended Artist) — endpoints artist.
 *
 * Toate gated cu auth:sanctum + extended.artist middleware.
 */
class BookingController extends BaseController
{
    public function __construct(private readonly BookingService $booking)
    {
    }

    public function listing(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $listing = $this->booking->getListing($artist);
        return $this->success($this->serializeListing($listing));
    }

    public function updateListing(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $validated = $request->validate([
            'min_fee_ron' => 'nullable|integer|min:0|max:10000000',
            'max_fee_ron' => 'nullable|integer|min:0|max:10000000',
            'show_fee_publicly' => 'nullable|boolean',
            'event_types' => 'nullable|array',
            'event_types.*' => 'string|max:30',
            'accepted_genres' => 'nullable|array',
            'standard_set_length_min' => 'nullable|integer|min:15|max:600',
            'standard_min_audience' => 'nullable|integer|min:0',
            'standard_max_audience' => 'nullable|integer|min:0',
            'requires_soundcheck' => 'nullable|boolean',
            'soundcheck_min_minutes' => 'nullable|integer|min:0|max:600',
            'requires_backline' => 'nullable|boolean',
            'requires_catering' => 'nullable|boolean',
            'requires_accommodation' => 'nullable|boolean',
            'requires_transport' => 'nullable|boolean',
            'description' => 'nullable|array',
            'max_distance_km' => 'nullable|integer|min:0|max:10000',
            'response_target_hours' => 'nullable|integer|min:1|max:168',
            'status' => 'nullable|in:active,paused',
        ]);

        $listing = $this->booking->updateListing($artist, $validated);
        return $this->success($this->serializeListing($listing), 'Listing actualizat.');
    }

    public function inbox(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $status = $request->query('status');
        $search = $request->query('search');
        $page = max(1, (int) $request->query('page', 1));
        return $this->success($this->booking->inboxList($artist, $status, $search, $page));
    }

    public function showRequest(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        return $this->success($this->booking->getRequest($artist, $id));
    }

    public function postMessage(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $validated = $request->validate([
            'type' => 'required|in:message,counter',
            'body' => 'nullable|string|max:5000',
            'counter_terms' => 'nullable|array',
            'counter_terms.fee_ron' => 'nullable|integer|min:0|max:10000000',
            'counter_terms.set_length_min' => 'nullable|integer|min:15|max:600',
            'counter_terms.event_date' => 'nullable|date',
            'counter_terms.conditions' => 'nullable|array',
        ]);

        $msg = $this->booking->addArtistMessage(
            $artist,
            $id,
            $validated['type'],
            $validated['body'] ?? null,
            $validated['counter_terms'] ?? null
        );

        return $this->success(['message_id' => $msg->id], 'Mesaj trimis.');
    }

    public function acceptRequest(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $validated = $request->validate([
            'final_terms' => 'nullable|array',
            'final_terms.fee_ron' => 'nullable|integer|min:0',
            'final_terms.set_length_min' => 'nullable|integer|min:15|max:600',
            'final_terms.event_date' => 'nullable|date',
        ]);

        $r = $this->booking->acceptRequest($artist, $id, $validated['final_terms'] ?? null);
        return $this->success(['id' => $r->id, 'status' => $r->status], 'Booking acceptat.');
    }

    public function rejectRequest(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $r = $this->booking->rejectRequest($artist, $id, $validated['reason'] ?? null);
        return $this->success(['id' => $r->id, 'status' => $r->status], 'Cerere refuzată.');
    }

    public function contracts(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        return $this->success(['contracts' => $this->booking->contractsList($artist)]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $from = $request->query('from');
        $to = $request->query('to');
        return $this->success(['dates' => $this->booking->unavailableDates($artist, $from, $to)]);
    }

    public function blockDate(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $validated = $request->validate([
            'date_start' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_start',
            'reason' => 'nullable|string|max:120',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $d = $this->booking->setUnavailableDate(
            $artist,
            $validated['date_start'],
            $validated['date_end'],
            $validated['reason'] ?? null,
            $validated['color'] ?? null
        );
        return $this->success(['id' => $d->id], 'Dată blocată.');
    }

    public function unblockDate(Request $request, int $id): JsonResponse
    {
        $artist = $this->requireArtist($request);
        $this->booking->removeUnavailableDate($artist, $id);
        return $this->success(null, 'Dată deblocată.');
    }

    public function kpis(Request $request): JsonResponse
    {
        $artist = $this->requireArtist($request);
        return $this->success($this->booking->kpis($artist));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function requireArtist(Request $request): Artist
    {
        $account = $request->user();
        if (!$account instanceof MarketplaceArtistAccount) {
            abort(401, 'Artist account required');
        }
        if (!$account->artist_id) {
            abort(403, 'Profilul tău nu este asociat cu un artist.');
        }
        $artist = Artist::find($account->artist_id);
        if (!$artist) {
            abort(404, 'Artist record not found');
        }
        return $artist;
    }

    protected function serializeListing($listing): array
    {
        return [
            'id' => $listing->id,
            'min_fee_ron' => $listing->min_fee_ron,
            'max_fee_ron' => $listing->max_fee_ron,
            'show_fee_publicly' => (bool) $listing->show_fee_publicly,
            'event_types' => $listing->event_types ?? [],
            'accepted_genres' => $listing->accepted_genres ?? [],
            'standard_set_length_min' => $listing->standard_set_length_min,
            'standard_min_audience' => $listing->standard_min_audience,
            'standard_max_audience' => $listing->standard_max_audience,
            'requires_soundcheck' => (bool) $listing->requires_soundcheck,
            'soundcheck_min_minutes' => $listing->soundcheck_min_minutes,
            'requires_backline' => (bool) $listing->requires_backline,
            'requires_catering' => (bool) $listing->requires_catering,
            'requires_accommodation' => (bool) $listing->requires_accommodation,
            'requires_transport' => (bool) $listing->requires_transport,
            'description' => $listing->description ?? [],
            'max_distance_km' => $listing->max_distance_km,
            'response_target_hours' => $listing->response_target_hours,
            'status' => $listing->status,
        ];
    }
}
