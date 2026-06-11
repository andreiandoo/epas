<?php

namespace App\Services\ExtendedArtist;

use App\Models\Artist;
use App\Models\ArtistBookingListing;
use App\Models\ArtistBookingMessage;
use App\Models\ArtistBookingRequest;
use App\Models\ArtistBookingUnavailableDate;
use App\Models\MarketplaceArtistAccount;
use App\Notifications\Booking\BookingAcceptedNotification;
use App\Notifications\Booking\BookingMessageReceivedNotification;
use App\Notifications\Booking\BookingRejectedNotification;
use App\Notifications\Booking\BookingRequestReceivedNotification;
use App\Notifications\Booking\BookingRequestSubmittedConfirmation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Booking Marketplace (Modulul 4 din Extended Artist).
 *
 * Flow:
 *   1. Vizitator submitează cererea via /api/public/artist/{slug}/booking-request
 *   2. createRequestFromGuest creează BookingRequest + guest_token + trimite emails
 *   3. Artist primește notificare + email cu link la inbox
 *   4. Artist răspunde cu mesaj/counter/accept/reject — addArtistMessage
 *   5. Guest primește email cu signed URL la conversație
 *   6. Guest răspunde via signed URL — addGuestMessage
 *   7. La accept din ambele părți → status accepted + final_terms snapshot
 */
class BookingService
{
    public const REQUEST_EXPIRY_DAYS = 14;

    /**
     * Get or create the artist's listing.
     */
    public function getListing(Artist $artist): ArtistBookingListing
    {
        return ArtistBookingListing::firstOrCreate(
            ['artist_id' => $artist->id],
            [
                'event_types' => ['concert'],
                'standard_set_length_min' => 60,
                'requires_soundcheck' => true,
                'soundcheck_min_minutes' => 60,
                'requires_catering' => true,
                'response_target_hours' => 24,
                'status' => 'paused',
            ]
        );
    }

    public function updateListing(Artist $artist, array $data): ArtistBookingListing
    {
        $listing = $this->getListing($artist);
        $listing->update($data);
        return $listing->fresh();
    }

    public function inboxList(Artist $artist, ?string $status = null, ?string $search = null, int $page = 1, int $perPage = 20): array
    {
        $query = ArtistBookingRequest::where('artist_id', $artist->id);

        if ($status === 'archive') {
            $query->whereIn('status', ArtistBookingRequest::ARCHIVE_STATUSES);
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where('guest_name', 'ILIKE', $term)
                  ->orWhere('guest_company', 'ILIKE', $term)
                  ->orWhere('event_city', 'ILIKE', $term)
                  ->orWhere('event_venue_name', 'ILIKE', $term);
            });
        }

        $total = $query->count();
        $rows = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'requests' => $rows->map(fn ($r) => $this->serializeListItem($r))->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function getRequest(Artist $artist, int $id): array
    {
        $request = ArtistBookingRequest::where('artist_id', $artist->id)
            ->with('messages')
            ->findOrFail($id);

        // Auto-mark first viewed
        if (!$request->first_viewed_at) {
            $request->first_viewed_at = now();
            if ($request->status === ArtistBookingRequest::STATUS_NEW) {
                $request->status = ArtistBookingRequest::STATUS_VIEWED;
            }
            $request->save();
        }

        // Mark unread guest messages as read
        $request->messages()
            ->where('sender_type', ArtistBookingMessage::SENDER_GUEST)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->serializeFull($request);
    }

    public function createRequestFromGuest(Artist $artist, array $payload): ArtistBookingRequest
    {
        $request = DB::transaction(function () use ($artist, $payload) {
            return ArtistBookingRequest::create([
                'artist_id' => $artist->id,
                'guest_name' => $payload['guest_name'],
                'guest_email' => $payload['guest_email'],
                'guest_phone' => $payload['guest_phone'] ?? null,
                'guest_company' => $payload['guest_company'] ?? null,
                'guest_company_type' => $payload['guest_company_type'] ?? null,
                'event_date' => $payload['event_date'],
                'event_time' => $payload['event_time'] ?? null,
                'event_venue_name' => $payload['event_venue_name'] ?? null,
                'event_city' => $payload['event_city'],
                'event_country' => $payload['event_country'] ?? 'RO',
                'event_type' => $payload['event_type'],
                'audience_size' => $payload['audience_size'] ?? null,
                'proposed_fee_ron' => $payload['proposed_fee_ron'],
                'proposed_set_length_min' => $payload['proposed_set_length_min'] ?? 60,
                'conditions' => $payload['conditions'] ?? [],
                'initial_message' => $payload['initial_message'],
                'status' => ArtistBookingRequest::STATUS_NEW,
                'guest_token' => ArtistBookingRequest::generateGuestToken(),
                'expires_at' => now()->addDays(self::REQUEST_EXPIRY_DAYS),
            ]);
        });

        $this->notifyArtistOfNewRequest($request);
        $this->notifyGuestOfSubmission($request);

        return $request;
    }

    public function addArtistMessage(Artist $artist, int $requestId, string $type, ?string $body = null, ?array $counterTerms = null): ArtistBookingMessage
    {
        $request = ArtistBookingRequest::where('artist_id', $artist->id)->findOrFail($requestId);

        if (in_array($request->status, [ArtistBookingRequest::STATUS_ACCEPTED, ArtistBookingRequest::STATUS_REJECTED], true)) {
            abort(422, 'Cererea e deja finalizată.');
        }

        $msg = $request->messages()->create([
            'sender_type' => ArtistBookingMessage::SENDER_ARTIST,
            'type' => $type,
            'body' => $body,
            'counter_terms' => $counterTerms,
        ]);

        $request->last_artist_response_at = now();
        if ($request->status === ArtistBookingRequest::STATUS_NEW || $request->status === ArtistBookingRequest::STATUS_VIEWED) {
            $request->status = ArtistBookingRequest::STATUS_NEGOTIATING;
        }
        $request->save();

        $this->notifyGuestOfReply($request, $msg);

        return $msg;
    }

    public function addGuestMessage(string $token, string $type, ?string $body = null, ?array $counterTerms = null): array
    {
        $request = ArtistBookingRequest::where('guest_token', $token)->firstOrFail();

        if (in_array($request->status, [ArtistBookingRequest::STATUS_ACCEPTED, ArtistBookingRequest::STATUS_REJECTED], true)) {
            abort(422, 'Cererea e deja finalizată.');
        }

        $msg = $request->messages()->create([
            'sender_type' => ArtistBookingMessage::SENDER_GUEST,
            'type' => $type,
            'body' => $body,
            'counter_terms' => $counterTerms,
        ]);

        if ($request->status === ArtistBookingRequest::STATUS_VIEWED) {
            $request->status = ArtistBookingRequest::STATUS_NEGOTIATING;
            $request->save();
        }

        $this->notifyArtistOfReply($request, $msg);

        return ['request' => $this->serializeFull($request->fresh('messages')), 'message' => $msg];
    }

    public function acceptRequest(Artist $artist, int $requestId, ?array $finalTerms = null): ArtistBookingRequest
    {
        $request = ArtistBookingRequest::where('artist_id', $artist->id)->findOrFail($requestId);

        // Snapshot termeni finali — fie din ultima counter-ofertă, fie din proposal-ul inițial
        $terms = $finalTerms ?? $this->resolveFinalTerms($request);

        $request->status = ArtistBookingRequest::STATUS_ACCEPTED;
        $request->accepted_at = now();
        $request->final_terms = $terms;
        $request->save();

        $request->messages()->create([
            'sender_type' => ArtistBookingMessage::SENDER_ARTIST,
            'type' => ArtistBookingMessage::TYPE_ACCEPT,
            'body' => 'Termenii au fost acceptați. Booking confirmat.',
            'counter_terms' => $terms,
        ]);

        $this->notifyBothOfAcceptance($request);

        return $request;
    }

    public function rejectRequest(Artist $artist, int $requestId, ?string $reason = null): ArtistBookingRequest
    {
        $request = ArtistBookingRequest::where('artist_id', $artist->id)->findOrFail($requestId);

        $request->status = ArtistBookingRequest::STATUS_REJECTED;
        $request->rejected_at = now();
        $request->rejection_reason = $reason;
        $request->save();

        $request->messages()->create([
            'sender_type' => ArtistBookingMessage::SENDER_ARTIST,
            'type' => ArtistBookingMessage::TYPE_REJECT,
            'body' => $reason ?: 'Cererea a fost refuzată.',
        ]);

        $this->notifyGuestOfRejection($request);

        return $request;
    }

    public function kpis(Artist $artist): array
    {
        $now = now();
        $yearStart = $now->copy()->startOfYear();
        $lastYearStart = $yearStart->copy()->subYear();

        $active = ArtistBookingRequest::where('artist_id', $artist->id)
            ->whereIn('status', ArtistBookingRequest::ACTIVE_STATUSES)
            ->count();
        $newCount = ArtistBookingRequest::where('artist_id', $artist->id)
            ->where('status', ArtistBookingRequest::STATUS_NEW)
            ->count();
        $negotiatingCount = ArtistBookingRequest::where('artist_id', $artist->id)
            ->where('status', ArtistBookingRequest::STATUS_NEGOTIATING)
            ->count();

        $thisYear = ArtistBookingRequest::where('artist_id', $artist->id)
            ->where('status', ArtistBookingRequest::STATUS_ACCEPTED)
            ->where('accepted_at', '>=', $yearStart)
            ->count();
        $lastYear = ArtistBookingRequest::where('artist_id', $artist->id)
            ->where('status', ArtistBookingRequest::STATUS_ACCEPTED)
            ->whereBetween('accepted_at', [$lastYearStart, $yearStart])
            ->count();
        $yearTrend = $lastYear > 0 ? round((($thisYear - $lastYear) / $lastYear) * 100) : ($thisYear > 0 ? 100 : 0);

        $totalDecided = ArtistBookingRequest::where('artist_id', $artist->id)
            ->whereIn('status', [ArtistBookingRequest::STATUS_ACCEPTED, ArtistBookingRequest::STATUS_REJECTED])
            ->count();
        $accepted = ArtistBookingRequest::where('artist_id', $artist->id)
            ->where('status', ArtistBookingRequest::STATUS_ACCEPTED)
            ->count();
        $acceptanceRate = $totalDecided > 0 ? round(($accepted / $totalDecided) * 100) : 0;

        // Avg response time = avg(last_artist_response_at - created_at) pe cele cu response
        $responseTimes = ArtistBookingRequest::where('artist_id', $artist->id)
            ->whereNotNull('last_artist_response_at')
            ->select(DB::raw('EXTRACT(EPOCH FROM (last_artist_response_at - created_at)) as response_seconds'))
            ->pluck('response_seconds')
            ->filter()
            ->all();
        $avgResponseHours = count($responseTimes) > 0
            ? round((array_sum($responseTimes) / count($responseTimes)) / 3600, 1)
            : null;

        return [
            'active' => $active,
            'new' => $newCount,
            'negotiating' => $negotiatingCount,
            'this_year' => $thisYear,
            'last_year' => $lastYear,
            'year_trend' => $yearTrend,
            'acceptance_rate' => $acceptanceRate,
            'avg_response_hours' => $avgResponseHours,
            'total_decided' => $totalDecided,
        ];
    }

    public function contractsList(Artist $artist): array
    {
        return ArtistBookingRequest::where('artist_id', $artist->id)
            ->where('status', ArtistBookingRequest::STATUS_ACCEPTED)
            ->orderBy('event_date')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'guest_name' => $r->guest_name,
                'guest_company' => $r->guest_company,
                'event_date' => $r->event_date?->toDateString(),
                'event_time' => $r->event_time,
                'event_venue_name' => $r->event_venue_name,
                'event_city' => $r->event_city,
                'event_type' => $r->event_type,
                'final_fee_ron' => (int) ($r->final_terms['fee_ron'] ?? $r->proposed_fee_ron),
                'final_set_length_min' => (int) ($r->final_terms['set_length_min'] ?? $r->proposed_set_length_min),
                'accepted_at' => $r->accepted_at?->toIso8601String(),
            ])
            ->all();
    }

    public function unavailableDates(Artist $artist, ?string $from = null, ?string $to = null): array
    {
        $q = ArtistBookingUnavailableDate::where('artist_id', $artist->id);
        if ($from) $q->where('date_end', '>=', $from);
        if ($to) $q->where('date_start', '<=', $to);

        return $q->orderBy('date_start')->get()->map(fn ($d) => [
            'id' => $d->id,
            'date_start' => $d->date_start?->toDateString(),
            'date_end' => $d->date_end?->toDateString(),
            'reason' => $d->reason,
            'color' => $d->color,
        ])->all();
    }

    /**
     * Calendar overlay items (booking-uri + cereri) intre [from, to].
     *  - status=accepted → kind='booking' (verde)
     *  - status in [new, viewed, negotiating] → kind='pending' (amber)
     */
    public function calendarOverlay(Artist $artist, ?string $from = null, ?string $to = null): array
    {
        $q = ArtistBookingRequest::where('artist_id', $artist->id);
        if ($from) $q->where('event_date', '>=', $from);
        if ($to) $q->where('event_date', '<=', $to);

        return $q->orderBy('event_date')->get()->map(function ($r) {
            $isAccepted = $r->status === ArtistBookingRequest::STATUS_ACCEPTED;
            $isPending = in_array($r->status, [
                ArtistBookingRequest::STATUS_NEW,
                ArtistBookingRequest::STATUS_VIEWED,
                ArtistBookingRequest::STATUS_NEGOTIATING,
            ], true);
            if (!$isAccepted && !$isPending) return null;

            $eventDate = $r->event_date?->toDateString();
            $finalDate = $r->final_terms['event_date'] ?? null;
            $date = $finalDate ?: $eventDate;
            return [
                'request_id' => $r->id,
                'kind' => $isAccepted ? 'booking' : 'pending',
                'status' => $r->status,
                'date' => $date,
                'guest_name' => $r->guest_name,
                'guest_company' => $r->guest_company,
                'event_city' => $r->event_city,
                'event_venue' => $r->event_venue_name,
                'event_type' => $r->event_type,
                'fee_ron' => (int) ($r->final_terms['fee_ron'] ?? $r->proposed_fee_ron),
            ];
        })->filter()->values()->all();
    }

    public function setUnavailableDate(Artist $artist, string $start, string $end, ?string $reason = null, ?string $color = null): ArtistBookingUnavailableDate
    {
        return ArtistBookingUnavailableDate::create([
            'artist_id' => $artist->id,
            'date_start' => $start,
            'date_end' => $end,
            'reason' => $reason,
            'color' => $color ?: '#94A3B8',
        ]);
    }

    public function removeUnavailableDate(Artist $artist, int $id): void
    {
        ArtistBookingUnavailableDate::where('artist_id', $artist->id)->where('id', $id)->delete();
    }

    /**
     * Construiește feed-ul ICS pentru calendar-ul artistului. Include:
     *  - Booking-uri acceptate (status=accepted, CONFIRMED)
     *  - Cereri în negociere (status=new/viewed/negotiating, TENTATIVE)
     *  - Zile blocate manual (DTSTART din date_start până în date_end+1, FREEBUSY=BUSY)
     *
     * Filtrează la 2 ani în trecut și 5 ani în viitor pentru a fi pragmatic.
     */
    public function buildIcsFeed(Artist $artist): string
    {
        $listing = $this->getListing($artist);
        $artistName = $artist->name;
        if (is_array($artistName)) {
            $artistName = $artistName['ro'] ?? $artistName['en'] ?? reset($artistName) ?: 'Artist';
        }

        $from = now()->subYears(2)->toDateString();
        $to = now()->addYears(5)->toDateString();

        $requests = ArtistBookingRequest::where('artist_id', $artist->id)
            ->whereBetween('event_date', [$from, $to])
            ->whereIn('status', [
                ArtistBookingRequest::STATUS_NEW,
                ArtistBookingRequest::STATUS_VIEWED,
                ArtistBookingRequest::STATUS_NEGOTIATING,
                ArtistBookingRequest::STATUS_ACCEPTED,
            ])
            ->orderBy('event_date')
            ->get();

        $blocks = ArtistBookingUnavailableDate::where('artist_id', $artist->id)
            ->where('date_end', '>=', $from)
            ->where('date_start', '<=', $to)
            ->orderBy('date_start')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Tixello//Booking ' . $artist->id . '//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->icsEscape('Booking ' . $artistName),
            'X-WR-CALDESC:' . $this->icsEscape('Calendar booking ' . $artistName . ' (sync via Tixello)'),
            'X-WR-TIMEZONE:Europe/Bucharest',
        ];

        $now = now()->utc()->format('Ymd\THis\Z');
        $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'tixello.com';

        foreach ($requests as $r) {
            $isAccepted = $r->status === ArtistBookingRequest::STATUS_ACCEPTED;
            $eventDate = $r->final_terms['event_date'] ?? $r->event_date->toDateString();
            $startTime = $r->event_time ?: '20:00:00';
            // Normalize HH:MM or HH:MM:SS
            if (strlen($startTime) === 5) $startTime .= ':00';
            $start = $eventDate . ' ' . $startTime;
            try {
                $startDt = \Illuminate\Support\Carbon::parse($start, 'Europe/Bucharest');
            } catch (\Throwable $e) {
                $startDt = \Illuminate\Support\Carbon::parse($eventDate . ' 20:00:00', 'Europe/Bucharest');
            }
            $setLengthMin = (int) ($r->final_terms['set_length_min'] ?? $r->proposed_set_length_min ?? 60);
            // Allow buffer around the set: artist arrives 2h before, leaves 1h after
            $endDt = $startDt->copy()->addMinutes($setLengthMin)->addHour();
            $startDt = $startDt->copy()->subHours(2);

            $summary = ($isAccepted ? '✅ ' : '❓ ') . $r->guest_name
                . ($r->event_city ? ' · ' . $r->event_city : '')
                . ($r->event_venue_name ? ' (' . $r->event_venue_name . ')' : '');

            $description = [];
            if ($r->guest_company) $description[] = 'Organizator: ' . $r->guest_company;
            $description[] = 'Tip: ' . $r->event_type;
            if ($r->audience_size) $description[] = 'Audiență: ' . $r->audience_size;
            $fee = (int) ($r->final_terms['fee_ron'] ?? $r->proposed_fee_ron);
            if ($fee) $description[] = 'Buget: ' . number_format($fee, 0, ',', '.') . ' RON';
            $description[] = 'Status: ' . ucfirst($r->status);
            $description[] = '';
            $description[] = 'Vezi în Tixello: ' . rtrim(config('app.marketplace_url') ?? config('app.url'), '/') . '/artist/cont/extended-artist/booking?tab=inbox&request=' . $r->id;

            $location = trim(($r->event_venue_name ? $r->event_venue_name . ', ' : '') . ($r->event_city ?: ''));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:booking-' . $r->id . '@' . $domain;
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'DTSTART;TZID=Europe/Bucharest:' . $startDt->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=Europe/Bucharest:' . $endDt->format('Ymd\THis');
            $lines[] = 'SUMMARY:' . $this->icsEscape($summary);
            $lines[] = 'DESCRIPTION:' . $this->icsEscape(implode("\n", $description));
            if ($location) $lines[] = 'LOCATION:' . $this->icsEscape($location);
            $lines[] = 'STATUS:' . ($isAccepted ? 'CONFIRMED' : 'TENTATIVE');
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'CATEGORIES:Booking,' . ($isAccepted ? 'Confirmed' : 'Pending');
            $lines[] = 'LAST-MODIFIED:' . ($r->updated_at ? $r->updated_at->utc()->format('Ymd\THis\Z') : $now);
            $lines[] = 'END:VEVENT';
        }

        foreach ($blocks as $b) {
            // All-day events for blocked ranges (DTEND e exclusiv în iCal, deci +1 zi)
            $startDate = \Illuminate\Support\Carbon::parse($b->date_start);
            $endDate = \Illuminate\Support\Carbon::parse($b->date_end ?: $b->date_start)->addDay();
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:block-' . $b->id . '@' . $domain;
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'DTSTART;VALUE=DATE:' . $startDate->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $endDate->format('Ymd');
            $lines[] = 'SUMMARY:' . $this->icsEscape('🚫 ' . ($b->reason ?: 'Indisponibil'));
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'CATEGORIES:Booking,Blocked';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // Fold lines longer than 75 octets per RFC 5545 (continuation cu space)
        $folded = [];
        foreach ($lines as $line) {
            if (strlen($line) <= 75) {
                $folded[] = $line;
                continue;
            }
            $folded[] = substr($line, 0, 75);
            $rest = substr($line, 75);
            while (strlen($rest) > 74) {
                $folded[] = ' ' . substr($rest, 0, 74);
                $rest = substr($rest, 74);
            }
            if ($rest !== '') $folded[] = ' ' . $rest;
        }

        return implode("\r\n", $folded) . "\r\n";
    }

    protected function icsEscape(string $value): string
    {
        // RFC 5545: escape commas, semicolons, backslashes, newlines
        $value = str_replace(["\\", ",", ";"], ["\\\\", "\\,", "\\;"], $value);
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
        return $value;
    }

    public function expireStaleRequests(): int
    {
        return ArtistBookingRequest::whereIn('status', [
                ArtistBookingRequest::STATUS_NEW,
                ArtistBookingRequest::STATUS_VIEWED,
                ArtistBookingRequest::STATUS_NEGOTIATING,
            ])
            ->where('expires_at', '<', now())
            ->update(['status' => ArtistBookingRequest::STATUS_EXPIRED]);
    }

    // -----------------------------------------------------------------------
    // Helpers + serialization
    // -----------------------------------------------------------------------

    protected function resolveFinalTerms(ArtistBookingRequest $request): array
    {
        // Prioritate: ultima counter-ofertă, fie din artist fie din guest. Dacă nu există, folosim propunerea inițială.
        $lastCounter = $request->messages()->where('type', ArtistBookingMessage::TYPE_COUNTER)->latest('created_at')->first();
        if ($lastCounter && $lastCounter->counter_terms) {
            return [
                'fee_ron' => $lastCounter->counter_terms['fee_ron'] ?? $request->proposed_fee_ron,
                'set_length_min' => $lastCounter->counter_terms['set_length_min'] ?? $request->proposed_set_length_min,
                'event_date' => $lastCounter->counter_terms['event_date'] ?? $request->event_date->toDateString(),
                'conditions' => $lastCounter->counter_terms['conditions'] ?? ($request->conditions ?? []),
            ];
        }
        return [
            'fee_ron' => $request->proposed_fee_ron,
            'set_length_min' => $request->proposed_set_length_min,
            'event_date' => $request->event_date->toDateString(),
            'conditions' => $request->conditions ?? [],
        ];
    }

    protected function serializeListItem(ArtistBookingRequest $r): array
    {
        $unread = $r->status === ArtistBookingRequest::STATUS_NEW;
        return [
            'id' => $r->id,
            'status' => $r->status,
            'unread' => $unread,
            'received_ago' => $r->created_at?->diffForHumans(),
            'guest' => [
                'name' => $r->guest_name,
                'company' => $r->guest_company,
                'company_type' => $r->guest_company_type,
                'initials' => $this->makeInitials($r->guest_name, $r->guest_company),
            ],
            'event' => [
                'date_iso' => $r->event_date?->toDateString(),
                'date' => $r->event_date?->translatedFormat('j M Y'),
                'time' => $r->event_time,
                'venue' => $r->event_venue_name,
                'city' => $r->event_city,
                'country' => $r->event_country,
                'type' => $r->event_type,
                'audience' => $r->audience_size,
                'fee_ron' => $r->proposed_fee_ron,
                'set_length_min' => $r->proposed_set_length_min,
            ],
            'preview' => mb_substr($r->initial_message, 0, 220),
        ];
    }

    protected function serializeFull(ArtistBookingRequest $r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status,
            'received_ago' => $r->created_at?->diffForHumans(),
            'expires_at' => $r->expires_at?->toIso8601String(),
            'guest' => [
                'name' => $r->guest_name,
                'email' => $r->guest_email,
                'phone' => $r->guest_phone,
                'company' => $r->guest_company,
                'company_type' => $r->guest_company_type,
                'initials' => $this->makeInitials($r->guest_name, $r->guest_company),
            ],
            'event' => [
                'date_iso' => $r->event_date?->toDateString(),
                'date' => $r->event_date?->translatedFormat('j M Y'),
                'time' => $r->event_time,
                'venue' => $r->event_venue_name,
                'city' => $r->event_city,
                'country' => $r->event_country,
                'type' => $r->event_type,
                'audience' => $r->audience_size,
                'fee_ron' => $r->proposed_fee_ron,
                'set_length_min' => $r->proposed_set_length_min,
                'conditions' => $r->conditions ?? [],
            ],
            'initial_message' => $r->initial_message,
            'final_terms' => $r->final_terms,
            'rejection_reason' => $r->rejection_reason,
            'thread' => $r->messages->map(fn ($m) => [
                'id' => $m->id,
                'sender_type' => $m->sender_type,
                'type' => $m->type,
                'body' => $m->body,
                'counter_terms' => $m->counter_terms,
                'time' => $m->created_at?->translatedFormat('j M, H:i'),
                'time_iso' => $m->created_at?->toIso8601String(),
                'initials' => $m->sender_type === 'artist'
                    ? $this->makeInitials($r->artist?->name ?? 'Artist')
                    : $this->makeInitials($r->guest_name, $r->guest_company),
            ])->all(),
        ];
    }

    protected function makeInitials(?string $first, ?string $second = null): string
    {
        $parts = [];
        if ($first) $parts = preg_split('/\s+/', trim($first));
        $a = !empty($parts[0]) ? mb_strtoupper(mb_substr($parts[0], 0, 1)) : '';
        $b = !empty($parts[1]) ? mb_strtoupper(mb_substr($parts[1], 0, 1)) : ($second ? mb_strtoupper(mb_substr(trim($second), 0, 1)) : '');
        return ($a . $b) ?: '?';
    }

    // -----------------------------------------------------------------------
    // Notifications
    // -----------------------------------------------------------------------

    protected function notifyArtistOfNewRequest(ArtistBookingRequest $request): void
    {
        try {
            $accounts = MarketplaceArtistAccount::where('artist_id', $request->artist_id)->get();
            foreach ($accounts as $account) {
                $account->notify(new BookingRequestReceivedNotification($request));
            }
        } catch (\Throwable $e) {
            Log::warning('booking: notifyArtistOfNewRequest failed', ['error' => $e->getMessage(), 'request_id' => $request->id]);
        }
    }

    protected function notifyGuestOfSubmission(ArtistBookingRequest $request): void
    {
        try {
            Notification::route('mail', $request->guest_email)
                ->notify(new BookingRequestSubmittedConfirmation($request));
        } catch (\Throwable $e) {
            Log::warning('booking: notifyGuestOfSubmission failed', ['error' => $e->getMessage(), 'request_id' => $request->id]);
        }
    }

    protected function notifyArtistOfReply(ArtistBookingRequest $request, ArtistBookingMessage $msg): void
    {
        try {
            $accounts = MarketplaceArtistAccount::where('artist_id', $request->artist_id)->get();
            foreach ($accounts as $account) {
                $account->notify(new BookingMessageReceivedNotification($request, $msg, 'artist'));
            }
        } catch (\Throwable $e) {
            Log::warning('booking: notifyArtistOfReply failed', ['error' => $e->getMessage()]);
        }
    }

    protected function notifyGuestOfReply(ArtistBookingRequest $request, ArtistBookingMessage $msg): void
    {
        try {
            Notification::route('mail', $request->guest_email)
                ->notify(new BookingMessageReceivedNotification($request, $msg, 'guest'));
        } catch (\Throwable $e) {
            Log::warning('booking: notifyGuestOfReply failed', ['error' => $e->getMessage()]);
        }
    }

    protected function notifyBothOfAcceptance(ArtistBookingRequest $request): void
    {
        try {
            $accounts = MarketplaceArtistAccount::where('artist_id', $request->artist_id)->get();
            foreach ($accounts as $account) {
                $account->notify(new BookingAcceptedNotification($request, 'artist'));
            }
            Notification::route('mail', $request->guest_email)
                ->notify(new BookingAcceptedNotification($request, 'guest'));
        } catch (\Throwable $e) {
            Log::warning('booking: notifyBothOfAcceptance failed', ['error' => $e->getMessage()]);
        }
    }

    protected function notifyGuestOfRejection(ArtistBookingRequest $request): void
    {
        try {
            Notification::route('mail', $request->guest_email)
                ->notify(new BookingRejectedNotification($request));
        } catch (\Throwable $e) {
            Log::warning('booking: notifyGuestOfRejection failed', ['error' => $e->getMessage()]);
        }
    }
}
