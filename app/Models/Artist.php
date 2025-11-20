<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Artist extends Model
{
    use HasFactory;
    use Translatable;

    /**
     * Translatable fields
     */
    public array $translatable = ['bio_html'];

    protected $guarded = [];

    protected $fillable = [
        'name', 'slug', 'bio_html',
        'website', 'facebook_url', 'instagram_url', 'tiktok_url',
        'youtube_url', 'youtube_id', 'spotify_url', 'spotify_id',
        'main_image_url', 'logo_url', 'portrait_url',
        'youtube_videos',
        'city', 'country', 'phone', 'email',
        'manager_first_name','manager_last_name','manager_email','manager_phone','manager_website',
        'agent_first_name','agent_last_name','agent_email','agent_phone','agent_website',
        'is_active',
        'facebook_followers','instagram_followers','tiktok_followers','spotify_followers','youtube_followers',
        'followers_facebook','followers_instagram','followers_tiktok','followers_youtube','spotify_monthly_listeners',
    ];

    protected $casts = [
        // folosim dot path în form: bio_html.en
        'bio_html'                 => 'array',
        'youtube_videos'           => 'array',
        'is_active'                => 'bool',
        'facebook_followers'       => 'integer',
        'instagram_followers'      => 'integer',
        'tiktok_followers'         => 'integer',
        'spotify_followers'        => 'integer',
        'youtube_followers'        => 'integer',
        'followers_facebook'       => 'integer',
        'followers_instagram'      => 'integer',
        'followers_tiktok'         => 'integer',
        'followers_youtube'        => 'integer',
        'spotify_monthly_listeners' => 'integer',
    ];

    // --- Relations ---
    public function artistTypes(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\ArtistType::class, 'artist_artist_type');
    }

    public function artistGenres(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\ArtistGenre::class, 'artist_artist_genre');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_artist');
    }

    // --- Helpers pentru statistici / serii ---

    public function eventsLastYearCount(): int
    {
        if (! method_exists($this, 'events')) return 0;
        $since = Carbon::now()->subYear();
        return $this->events()
            ->when(Schema::hasColumn('events', 'event_date'), fn($q) => $q->where('event_date', '>=', $since->toDateString()))
            ->count();
    }

    public function eventsCountBetween(Carbon $from, Carbon $to): int
    {
        return $this->events()
            ->when(Schema::hasColumn('events', 'event_date'), fn($q) =>
                $q->whereBetween('event_date', [$from->toDateString(), $to->toDateString()])
            )
            ->count();
    }

    /**
     * KPI-uri într-un interval.
     * Returnează:
     * [
     *   'events_count'     => int,
     *   'tickets_sold'     => int,
     *   'revenue_minor'    => int,   // în unități minore (bani/centi)
     *   'avg_per_event'    => float, // bilete / eveniment
     *   'avg_ticket_price' => float, // în unități majore (RON/EUR)
     * ]
     */
    public function computeKpis(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from?->copy()->startOfDay() ?? now()->subDays(365)->startOfDay();
        $to   = $to?->copy()->endOfDay()     ?? now()->endOfDay();

        // 1) câte evenimente în interval
        $eventsCount = $this->eventsCountBetween($from, $to);

        // 2) câte bilete vândute (tickets -> ticket_types -> events -> event_artist)
        $sold = 0;
        if (Schema::hasTable('tickets')) {
            $sold = DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->join('events', 'events.id', '=', 'ticket_types.event_id')
                ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                ->where('event_artist.artist_id', $this->id)
                ->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()])
                ->count();
        }

        // 3) venit total (în cenți), rezistent la schema curentă
        $revenueMinor = $this->sumRevenueCentsForRange($from, $to);

        // 4) medii
        $avgPerEvent    = $eventsCount ? round($sold / $eventsCount, 2) : 0.0;
        $avgTicketPrice = $sold ? round(($revenueMinor / $sold) / 100, 2) : 0.0;

        return [
            'events_count'     => $eventsCount,
            'tickets_sold'     => $sold,
            'revenue_minor'    => $revenueMinor,
            'avg_per_event'    => $avgPerEvent,
            'avg_ticket_price' => $avgTicketPrice,
        ];
    }

    public function ticketsSoldLastYear(): array
    {
        // Structură standardizată
        $out = [
            'sold'          => 0,
            'listed'        => 0,   // <- important: cheie prezentă mereu
            'avg_per_event' => 0.0,
            'avg_price'     => null,
        ];

        // Interval: ultimele 365 de zile (poți ajusta ușor)
        $from = now()->subDays(365)->startOfDay();
        $to   = now()->endOfDay();

        // 1) Bilete vândute (număr)
        $sold = DB::table('tickets')
            ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->join('events', 'events.id', '=', 'ticket_types.event_id')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $this->id)
            ->when(Schema::hasColumn('events', 'event_date'), function ($q) use ($from, $to) {
                $q->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->count();

        // 2) Bilete listate (stoc publicat) – detectăm coloana potrivită pe ticket_types
        $listed = 0;
        if (Schema::hasTable('ticket_types')) {
            $stockCol = $this->resolveFirstExistingColumn('ticket_types', [
                'capacity', 'quantity', 'stock', 'max_quantity', 'available', 'listed_quantity',
            ]);

            if ($stockCol) {
                $listed = (int) DB::table('ticket_types')
                    ->join('events', 'events.id', '=', 'ticket_types.event_id')
                    ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                    ->where('event_artist.artist_id', $this->id)
                    ->when(Schema::hasColumn('events', 'event_date'), function ($q) use ($from, $to) {
                        $q->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()]);
                    })
                    ->sum("ticket_types.$stockCol");
            }
        }

        // 3) Medii (evenimentele din perioada aceasta)
        $eventsCount = DB::table('events')
            ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
            ->where('event_artist.artist_id', $this->id)
            ->when(Schema::hasColumn('events', 'event_date'), function ($q) use ($from, $to) {
                $q->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->count();

        $avgPerEvent = $eventsCount ? round($sold / $eventsCount, 2) : 0;

        // 4) Preț mediu bilet (în RON) – folosim aceeași logică de detectare a coloanei de preț ca înainte
        [$priceCol, $unit] = self::detectTicketPriceColumn();
        $avgPrice = null;
        if ($priceCol !== '') {
            $sum = DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->join('events', 'events.id', '=', 'ticket_types.event_id')
                ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                ->where('event_artist.artist_id', $this->id)
                ->when(Schema::hasColumn('events', 'event_date'), function ($q) use ($from, $to) {
                    $q->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()]);
                })
                ->sum($priceCol);

            $soldSafe = max(1, $sold);

            $sumMajor = ($unit === 'major')
                ? (float) $sum               // deja în RON
                : ((int) $sum) / 100.0;      // cenți -> RON

            $avgPrice = round($sumMajor / $soldSafe, 2);
            if ($sold === 0) $avgPrice = 0.0;
        }

        $out['sold']          = (int) $sold;
        $out['listed']        = (int) $listed;
        $out['avg_per_event'] = (float) $avgPerEvent;
        $out['avg_price']     = $avgPrice;

        return $out;
    }

    /**
     * (opțional, dacă vrei variantă separată; dacă nu, folosește resolveFirstExistingColumn cum e mai sus)
     */
    protected static function detectTicketStockColumn(): ?string
    {
        if (! Schema::hasTable('ticket_types')) return null;

        $candidates = ['capacity', 'quantity', 'stock', 'max_quantity', 'available', 'listed_quantity'];
        $cols = Schema::getColumnListing('ticket_types');

        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    /** Serii pe 12 luni: [months[], events[], tickets[], revenue[]] */
    public function buildYearlySeries(): array
    {
        $months  = [];
        $events  = [];
        $tickets = [];
        $revenue = [];

        $start = Carbon::now()->startOfMonth()->subMonths(11);

        for ($i = 0; $i < 12; $i++) {
            $from = (clone $start)->addMonths($i);
            $to   = (clone $from)->endOfMonth();

            $months[] = $from->format('M Y');

            $eventsCount = DB::table('events')
                ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                ->where('event_artist.artist_id', $this->id)
                ->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()])
                ->count();

            $ticketsCount = 0;
            if (Schema::hasTable('tickets')) {
                $ticketsCount = DB::table('tickets')
                    ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                    ->join('events', 'events.id', '=', 'ticket_types.event_id')
                    ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                    ->where('event_artist.artist_id', $this->id)
                    ->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()])
                    ->count();
            }

            $revenueCents = $this->sumRevenueCentsForRange($from, $to);

            $events[]  = $eventsCount;
            $tickets[] = $ticketsCount;
            $revenue[] = $revenueCents / 100; // unități majore
        }

        return [$months, $events, $tickets, $revenue];
    }

    /**
     * Sumează venitul (în cenți) pentru intervalul dat, încercând:
     * 1) preț pe tickets; 2) preț pe order_items; 3) fallback din ticket_types.
     */
    protected function sumRevenueCentsForRange(Carbon $from, Carbon $to): int
    {
        // 1) Încearcă coloane de preț pe tickets
        if (Schema::hasTable('tickets')) {
            $ticketPriceCol = $this->resolveFirstExistingColumn('tickets', [
                'final_price_cents', 'price_cents', 'amount_cents', 'total_cents', 'subtotal_cents',
                'final_price_minor', 'paid_price_minor', 'price_minor', 'amount_minor',
            ]);

            if ($ticketPriceCol) {
                return (int) DB::table('tickets')
                    ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                    ->join('events', 'events.id', '=', 'ticket_types.event_id')
                    ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                    ->where('event_artist.artist_id', $this->id)
                    ->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()])
                    ->sum("tickets.$ticketPriceCol");
            }
        }

        // 2) Caută în order_items (unit price * quantity)
        if (Schema::hasTable('order_items')) {
            $oiPriceCol = $this->resolveFirstExistingColumn('order_items', [
                'final_price_cents', 'unit_price_cents', 'price_cents', 'amount_cents', 'total_cents', 'subtotal_cents',
            ]);
            if ($oiPriceCol) {
                $hasQty = Schema::hasColumn('order_items', 'quantity');

                $hasTicketId     = Schema::hasColumn('order_items', 'ticket_id');
                $hasTicketTypeId = Schema::hasColumn('order_items', 'ticket_type_id');

                $q = DB::table('order_items');

                if ($hasTicketId) {
                    $q->join('tickets', 'tickets.id', '=', 'order_items.ticket_id')
                      ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id');
                } elseif ($hasTicketTypeId) {
                    $q->join('ticket_types', 'ticket_types.id', '=', 'order_items.ticket_type_id');
                } else {
                    return 0;
                }

                $q->join('events', 'events.id', '=', 'ticket_types.event_id')
                  ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                  ->where('event_artist.artist_id', $this->id)
                  ->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()]);

                if ($hasQty) {
                    return (int) $q->sum(DB::raw("order_items.$oiPriceCol * COALESCE(order_items.quantity, 1)"));
                }

                return (int) $q->sum("order_items.$oiPriceCol");
            }
        }

        // 3) Fallback: COUNT(tickets) * ticket_types.price_cents (dacă există)
        $ttPriceCol = Schema::hasTable('ticket_types')
            ? $this->resolveFirstExistingColumn('ticket_types', ['final_price_cents', 'price_cents'])
            : null;

        if ($ttPriceCol && Schema::hasTable('tickets')) {
            $rows = DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->join('events', 'events.id', '=', 'ticket_types.event_id')
                ->join('event_artist', 'event_artist.event_id', '=', 'events.id')
                ->where('event_artist.artist_id', $this->id)
                ->whereBetween('events.event_date', [$from->toDateString(), $to->toDateString()])
                ->selectRaw("COUNT(tickets.id) as cnt, ticket_types.$ttPriceCol as unit_cents")
                ->groupBy("ticket_types.$ttPriceCol")
                ->get();

            $sum = 0;
            foreach ($rows as $r) {
                $sum += ((int) $r->unit_cents) * ((int) $r->cnt);
            }
            return $sum;
        }

        return 0;
    }

    /** Returnează primul nume de coloană existent pe tabel din lista dată, altfel null. */
    protected function resolveFirstExistingColumn(string $table, array $candidates): ?string
    {
        if (! Schema::hasTable($table)) return null;
        $cols = Schema::getColumnListing($table);
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    /**
     * Detectează coloana de preț din tickets și unitatea (minor/major).
     * (Păstrată publică dacă vrei s-o folosești din alte locuri.)
     */
    public static function detectTicketPriceColumn(): array
    {
        $candidatesMinor = [
            'final_price_minor', 'final_price_cents',
            'paid_price_minor',  'paid_price_cents',
            'price_minor',       'price_cents',
            'amount_minor',      'amount_cents',
        ];

        foreach ($candidatesMinor as $col) {
            if (Schema::hasColumn('tickets', $col)) {
                return ["tickets.$col", 'minor'];
            }
        }

        $candidatesMajor = ['final_price', 'paid_price', 'price', 'amount'];
        foreach ($candidatesMajor as $col) {
            if (Schema::hasColumn('tickets', $col)) {
                return ["tickets.$col", 'major'];
            }
        }

        return ['', 'minor'];
    }
}
