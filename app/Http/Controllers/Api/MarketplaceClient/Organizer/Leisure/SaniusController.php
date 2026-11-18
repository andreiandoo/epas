<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use App\Models\Sanius\SaniusRental;
use App\Models\Sanius\SaniusSetting;
use App\Models\Sanius\SaniusSled;
use App\Models\Sanius\SaniusTab;
use App\Models\Sanius\SaniusTow;
use App\Models\Sanius\SaniusTowShift;
use App\Models\TicketType;
use App\Services\Sanius\ParkingTicketCode;
use App\Services\Sanius\SaniusCircuit;
use App\Services\Sanius\SaniusException;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Circuitul Sanius (Sf. Ana): sanie inchiriata pe tichetul de parcare, tractari
 * numarate offline de soferi, plata la casierie la iesire.
 *
 * Nu atinge nimic din fluxurile existente: scrie doar in tabelele sanius_*.
 * Cu setarea pe „off” (implicit) endpoint-urile operationale raspund 403.
 * In modul „test” plata e simulata: nu se creeaza comenzi.
 */
class SaniusController extends BaseController
{
    /** Plata reala (prin posSale) vine intr-o etapa separata; pana atunci modul Activ e blocat. */
    private const LIVE_AVAILABLE = false;

    // ==================================================================
    // Context: organizator, eveniment, setari, rol
    // ==================================================================

    /**
     * @return array{organizer: MarketplaceOrganizer, event: Event, settings: SaniusSetting, member_id: ?int, role: string}|JsonResponse
     */
    private function context(Request $request, int $event, array $roles, bool $requireRunning = true)
    {
        $organizer = $request->user();
        if (! $organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();
        if (! $eventModel) {
            return $this->error('Event not found', 404);
        }
        if (($eventModel->display_template ?? null) !== 'leisure_venue') {
            return $this->error('Evenimentul nu e o locație de agrement.', 422);
        }

        $settings = SaniusSetting::firstOrNew(
            ['event_id' => $eventModel->id],
            ['marketplace_organizer_id' => $organizer->id, 'mode' => 'off', 'included_tows' => 1, 'ping_timeout_seconds' => 30]
        );

        $memberId = $this->teamMemberId($request, $organizer);
        // Contul principal al organizatorului (fara membru de echipa) are drept de manager.
        $role = $memberId ? $settings->roleOf($memberId) : 'owner';

        if ($requireRunning && $settings->isOff()) {
            return $this->error('Circuitul Sanius nu este pornit.', 403);
        }
        if (! empty($roles) && ! in_array($role, ['owner', 'manager'], true) && ! in_array($role, $roles, true)) {
            return $this->error('Nu ai rol pentru această operațiune în circuitul Sanius.', 403);
        }

        return [
            'organizer' => $organizer,
            'event' => $eventModel,
            'settings' => $settings,
            'member_id' => $memberId,
            'role' => $role ?? 'none',
        ];
    }

    private function teamMemberId(Request $request, MarketplaceOrganizer $organizer): ?int
    {
        $name = $request->user()?->currentAccessToken()?->name ?? '';
        if (! str_starts_with((string) $name, 'team-member-')) {
            return null;
        }
        $id = (int) substr((string) $name, strlen('team-member-'));
        $ok = MarketplaceOrganizerTeamMember::where('id', $id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->exists();

        // Token de membru, dar membrul nu (mai) e al organizatorului: fara drepturi.
        return $ok ? $id : -1;
    }

    private function circuit(array $ctx): SaniusCircuit
    {
        return new SaniusCircuit($ctx['settings'], $ctx['event']);
    }

    private function memberOrNull(array $ctx): ?int
    {
        return ($ctx['member_id'] ?? 0) > 0 ? $ctx['member_id'] : null;
    }

    // ==================================================================
    // Eu / setari
    // ==================================================================

    /** GET .../sanius/me — aplicatia afla daca arata butonul „Circuit Sanius” si cu ce rol. */
    public function me(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, [], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $s = $ctx['settings'];
        // In aplicatie, doar membrii cu rol vad circuitul (tabletele logate pe contul
        // principal nu se schimba nici in modul Test).
        $appRole = ($ctx['member_id'] ?? 0) > 0 ? $s->roleOf($ctx['member_id']) : null;

        return $this->success([
            'enabled' => ! $s->isOff() && $appRole !== null,
            'mode' => $s->mode ?: 'off',
            'role' => $appRole,
            'is_test' => $s->isTestMode(),
            'ping_timeout_seconds' => (int) ($s->ping_timeout_seconds ?: 30),
        ]);
    }

    /** GET .../sanius/settings */
    public function settingsShow(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }

        return $this->success($this->presentSettings($ctx));
    }

    /** PUT .../sanius/settings */
    public function settingsUpdate(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'mode' => 'nullable|in:'.implode(',', SaniusSetting::MODES),
            'members' => 'nullable|array|max:100',
            'members.*.member_id' => 'required|integer',
            'members.*.role' => 'required|in:'.implode(',', SaniusSetting::ROLES),
            'included_tows' => 'nullable|integer|min:0|max:20',
            'products' => 'nullable|array',
            'products.*' => 'nullable|integer',
            'parking' => 'nullable|array',
            'parking.free_minutes' => 'nullable|integer|min:0|max:1440',
            'parking.free_minutes_with_access' => 'nullable|integer|min:0|max:1440',
            'parking.unit_minutes' => 'nullable|integer|min:1|max:1440',
            'ping_timeout_seconds' => 'nullable|integer|min:10|max:300',
        ]);

        $s = $ctx['settings'];
        $organizer = $ctx['organizer'];

        if (array_key_exists('members', $v)) {
            $ids = collect($v['members'] ?? [])->pluck('member_id')->map(fn ($id) => (int) $id)->unique();
            $valid = MarketplaceOrganizerTeamMember::where('marketplace_organizer_id', $organizer->id)
                ->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id);
            if ($ids->diff($valid)->isNotEmpty()) {
                return $this->error('Unii membri nu fac parte din echipa ta.', 422);
            }
            $s->members = collect($v['members'] ?? [])
                ->unique('member_id')
                ->map(fn ($m) => ['member_id' => (int) $m['member_id'], 'role' => $m['role']])
                ->values()->all();
        }

        if (array_key_exists('products', $v)) {
            $map = [];
            foreach ((array) $v['products'] as $key => $id) {
                if (! in_array($key, SaniusSetting::PRODUCT_KEYS, true) || ! $id) {
                    continue;
                }
                if (! TicketType::where('id', (int) $id)->where('event_id', $ctx['event']->id)->exists()) {
                    return $this->error('Produsul „'.$key.'” nu aparține acestui eveniment.', 422);
                }
                $map[$key] = (int) $id;
            }
            $s->products = $map;
        }

        foreach (['included_tows', 'ping_timeout_seconds'] as $k) {
            if (isset($v[$k])) {
                $s->{$k} = (int) $v[$k];
            }
        }
        if (array_key_exists('parking', $v)) {
            $s->parking = array_intersect_key((array) $v['parking'], SaniusSetting::PARKING_DEFAULTS);
        }

        if (! empty($v['mode']) && $v['mode'] !== $s->mode) {
            if ($v['mode'] === 'live' && ! self::LIVE_AVAILABLE) {
                return $this->error('Modul Activ se deblochează după etapa de încasare reală. Folosește modul Test.', 422);
            }
            if ($v['mode'] === 'live') {
                $missing = (new SaniusCircuit($s, $ctx['event']))->missingProducts();
                if ($missing) {
                    return $this->error('Lipsesc produsele: '.implode(', ', $missing).'. Generează-le și setează prețurile.', 422);
                }
            }
            // Nu schimbam modul cat timp exista conturi sau inchirieri deschise in modul vechi.
            if (! $s->isOff()) {
                $wasTest = $s->isTestMode();
                $open = SaniusTab::where('event_id', $ctx['event']->id)->where('is_test', $wasTest)->where('status', 'open')
                    ->whereHas('rentals', fn ($q) => $q->whereIn('status', ['active', 'pending_sync']))->exists();
                if ($open) {
                    return $this->error('Există sănii încă închiriate în modul curent. Închide-le înainte de a schimba modul.', 422);
                }
            }
            $s->mode = $v['mode'];
        }

        $s->marketplace_organizer_id = $s->marketplace_organizer_id ?: $organizer->id;
        $s->save();

        return $this->success($this->presentSettings($ctx), 'Setări salvate');
    }

    private function presentSettings(array $ctx): array
    {
        $s = $ctx['settings'];
        $circuit = $this->circuit($ctx);
        $members = MarketplaceOrganizerTeamMember::where('marketplace_organizer_id', $ctx['organizer']->id)
            ->orderBy('name')->get(['id', 'name', 'email', 'leisure_role', 'status']);

        $products = [];
        foreach (SaniusSetting::PRODUCT_KEYS as $key) {
            $id = $s->productId($key);
            $products[$key] = ['ticket_type_id' => $id, 'price' => $id ? $circuit->unitPrice($id) : null];
        }

        return [
            'mode' => $s->mode ?: 'off',
            'live_available' => self::LIVE_AVAILABLE,
            'members' => array_values((array) $s->members),
            'team' => $members->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'email' => $m->email,
                'leisure_role' => $m->leisure_role, 'status' => $m->status,
            ])->values(),
            'included_tows' => (int) $s->included_tows,
            'products' => $products,
            'missing_products' => $circuit->missingProducts(),
            'parking' => $s->parkingRules(),
            'ping_timeout_seconds' => (int) ($s->ping_timeout_seconds ?: 30),
        ];
    }

    /**
     * POST .../sanius/products/bootstrap
     * Creeaza produsele de taxare care lipsesc, ascunse (status hidden): nu apar
     * nici pe site, nici in grila POS. Pretul il pune organizatorul din editorul de produse.
     */
    public function productsBootstrap(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $s = $ctx['settings'];
        $eventId = $ctx['event']->id;

        $defs = [
            'rent' => ['Sanius · Chirie sanie', 'rental', false],
            'tow' => ['Sanius · Tractare', 'rental', false],
            'damage_small' => ['Sanius · Avarie mică', 'rental', false],
            'damage_medium' => ['Sanius · Avarie medie', 'rental', false],
            'damage_large' => ['Sanius · Avarie mare', 'rental', false],
            'lost' => ['Sanius · Sanie pierdută', 'rental', false],
            'parking_car' => ['Sanius · Parcare auto (pe interval)', 'parking', true],
            'parking_bus' => ['Sanius · Parcare autobuz (pe interval)', 'parking', true],
        ];

        $map = (array) $s->products;
        $created = [];
        DB::transaction(function () use ($defs, $eventId, &$map, &$created) {
            $sort = (int) (TicketType::where('event_id', $eventId)->max('sort_order') ?? 0);
            foreach ($defs as $key => [$name, $category, $isParking]) {
                $existing = $map[$key] ?? null;
                if ($existing && TicketType::where('id', $existing)->where('event_id', $eventId)->exists()) {
                    continue;
                }
                $sort += 10;
                $tt = TicketType::create([
                    'event_id' => $eventId,
                    'name' => $name,
                    'service_category' => $category,
                    'is_parking' => $isParking,
                    'price_max' => 0,
                    'currency' => 'RON',
                    'is_active' => false,
                    'sort_order' => $sort,
                    'meta' => ['sanius_circuit' => $key, 'access_requirement' => 'none'],
                ]);
                $map[$key] = $tt->id;
                $created[] = $key;
            }
        });

        $s->products = $map;
        $s->marketplace_organizer_id = $s->marketplace_organizer_id ?: $ctx['organizer']->id;
        $s->save();

        return $this->success($this->presentSettings($ctx), $created
            ? 'Produse create (ascunse): '.implode(', ', $created).'. Setează-le prețurile.'
            : 'Toate produsele există deja.');
    }

    // ==================================================================
    // Sanii
    // ==================================================================

    /** GET .../sanius/sleds?is_test=0|1 */
    public function sledsIndex(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'tow', 'cashier'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $isTest = $request->has('is_test') ? $request->boolean('is_test') : $ctx['settings']->isTestMode();

        $sleds = SaniusSled::where('event_id', $ctx['event']->id)->where('is_test', $isTest)->orderBy('number')->get();
        $active = SaniusRental::whereIn('sled_id', $sleds->pluck('id'))->whereIn('status', ['active', 'pending_sync'])
            ->get()->keyBy('sled_id');

        return $this->success([
            'is_test' => $isTest,
            'sleds' => $sleds->map(fn (SaniusSled $s) => [
                'id' => $s->id,
                'number' => $s->number,
                'name' => $s->displayName(),
                'code' => $s->code,
                'qr' => $s->qrPayload(),
                'status' => $s->status,
                'rental_id' => $active->get($s->id)?->id,
                'rental_status' => $active->get($s->id)?->status,
            ])->values(),
        ]);
    }

    /** POST .../sanius/sleds { count, is_test } — adauga sanii noi, numerotate in continuare. */
    public function sledsStore(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'count' => 'required|integer|min:1|max:200',
            'is_test' => 'nullable|boolean',
        ]);
        $isTest = array_key_exists('is_test', $v) ? (bool) $v['is_test'] : $ctx['settings']->isTestMode();

        $created = DB::transaction(function () use ($ctx, $v, $isTest) {
            $next = (int) SaniusSled::where('event_id', $ctx['event']->id)->where('is_test', $isTest)->max('number');
            $out = [];
            for ($i = 1; $i <= $v['count']; $i++) {
                $out[] = SaniusSled::create([
                    'event_id' => $ctx['event']->id,
                    'number' => $next + $i,
                    'code' => SaniusSled::newCode(),
                    'status' => 'available',
                    'is_test' => $isTest,
                ])->id;
            }

            return $out;
        });

        return $this->success(['created' => count($created), 'is_test' => $isTest], 'Sănii adăugate', 201);
    }

    /** PUT .../sanius/sleds/{sled} { status?, label?, notes? } — ex. reparata -> disponibila. */
    public function sledsUpdate(Request $request, int $event, int $sled): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'status' => 'nullable|in:available,repair,lost,retired',
            'label' => 'nullable|string|max:60',
            'notes' => 'nullable|string|max:500',
        ]);
        $model = SaniusSled::where('id', $sled)->where('event_id', $ctx['event']->id)->first();
        if (! $model) {
            return $this->error('Sania nu există.', 404);
        }
        if (isset($v['status']) && $model->status === 'rented') {
            return $this->error('Sania e închiriată; statusul se schimbă la returnare.', 422);
        }
        $model->fill(array_filter($v, fn ($x) => $x !== null))->save();

        return $this->success(['id' => $model->id, 'status' => $model->status, 'name' => $model->displayName()], 'Sanie actualizată');
    }

    // ==================================================================
    // Ghiseu Sanius: cont, bilete, inchiriere, returnare
    // ==================================================================

    /** POST .../sanius/tabs/resolve { code } — tichetul de parcare scanat. */
    public function tabsResolve(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate(['code' => 'required|string|max:40']);
        $parsed = ParkingTicketCode::parse($v['code']);
        if (! $parsed) {
            return $this->error('Nu e un tichet de parcare valid (cod de 15 cifre).', 422);
        }
        if (! ParkingTicketCode::isFromToday($parsed['entry_at'])) {
            return $this->error('Tichetul de parcare nu este din ziua de azi.', 422);
        }

        $tab = $this->circuit($ctx)->openTab($parsed, $this->memberOrNull($ctx));

        return $this->success($this->circuit($ctx)->tabSummary($tab));
    }

    /** POST .../sanius/tabs/{tab}/link { ticket_code, point? } — leaga biletul (si restul comenzii). */
    public function tabsLink(Request $request, int $event, int $tab): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'ticket_code' => 'required|string|max:200',
            'point' => 'nullable|in:desk,cashier,kiosk',
        ]);
        $tabModel = $this->findTab($ctx, $tab);
        if (! $tabModel) {
            return $this->error('Contul nu există.', 404);
        }
        if (! $tabModel->isOpen()) {
            return $this->error('Contul a fost deja achitat.', 422);
        }

        $circuit = $this->circuit($ctx);
        $ticket = $circuit->findTicket($v['ticket_code']);
        if (! $ticket) {
            return $this->error('Biletul nu a fost găsit pentru această locație.', 404);
        }
        if (! $circuit->ticketIsUsable($ticket)) {
            return $this->error('Biletul nu este valid (anulat, rambursat sau comandă neplătită).', 422);
        }

        $point = $v['point'] ?? ($ctx['role'] === 'cashier' ? 'cashier' : 'desk');
        $result = $circuit->linkTicketWithOrder($tabModel, $ticket, $this->memberOrNull($ctx), $point);

        $msg = count($result['linked']).' bilet(e) legate de tichetul de parcare.';
        if ($result['elsewhere']) {
            $msg .= ' Atenție: '.count($result['elsewhere']).' bilet(e) sunt deja legate de alt tichet de parcare.';
        }

        return $this->success(array_merge(['link' => $result], $circuit->tabSummary($tabModel->fresh())), $msg);
    }

    /** POST .../sanius/tabs/{tab}/vehicle { plate?, type? } */
    public function tabsVehicle(Request $request, int $event, int $tab): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'plate' => 'nullable|string|max:16',
            'type' => 'nullable|in:car,bus',
        ]);
        $tabModel = $this->findTab($ctx, $tab);
        if (! $tabModel || ! $tabModel->isOpen()) {
            return $this->error('Contul nu există sau a fost achitat.', 404);
        }
        if (array_key_exists('plate', $v)) {
            $tabModel->vehicle_plate = $v['plate'] ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v['plate'])) : null;
        }
        if (! empty($v['type'])) {
            $tabModel->vehicle_type = $v['type'];
        }
        $tabModel->save();

        return $this->success($this->circuit($ctx)->tabSummary($tabModel));
    }

    /** POST .../sanius/rentals/start { tab_id, sled_code } */
    public function rentalsStart(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'tab_id' => 'required|integer',
            'sled_code' => 'required|string|max:60',
        ]);
        $tabModel = $this->findTab($ctx, $v['tab_id']);
        if (! $tabModel) {
            return $this->error('Contul nu există.', 404);
        }
        $circuit = $this->circuit($ctx);
        $sled = $circuit->findSled($v['sled_code']);
        if (! $sled) {
            return $this->error('Codul nu aparține unei sănii.', 404);
        }

        try {
            $rental = $circuit->startRental($tabModel, $sled, $this->memberOrNull($ctx));
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(array_merge(
            ['rental_id' => $rental->id, 'sled' => $sled->displayName()],
            $circuit->tabSummary($tabModel->fresh())
        ), $sled->displayName().' predată clientului.');
    }

    /** POST .../sanius/rentals/lookup { sled_code } — ce inchiriere are sania scanata la ghiseu. */
    public function rentalsLookup(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate(['sled_code' => 'required|string|max:60']);
        $circuit = $this->circuit($ctx);
        $sled = $circuit->findSled($v['sled_code']);
        if (! $sled) {
            return $this->error('Codul nu aparține unei sănii.', 404);
        }
        $rental = $circuit->activeRentalForSled($sled);

        return $this->success([
            'sled' => ['id' => $sled->id, 'name' => $sled->displayName(), 'status' => $sled->status, 'is_test' => $sled->is_test],
            'rental' => $rental ? $this->presentRental($rental) : null,
        ]);
    }

    /**
     * POST .../sanius/rentals/return (multipart) { sled_code, condition: good|damaged, damage_level?, photo? }
     * La avarie poza e obligatorie. Daca un sofer are tractari netrimise, returnarea
     * asteapta sincronizarea (status pending_sync) si se inchide singura dupa.
     */
    public function rentalsReturn(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'sled_code' => 'required|string|max:60',
            'condition' => 'required|in:good,damaged',
            'damage_level' => 'required_if:condition,damaged|nullable|in:'.implode(',', SaniusRental::DAMAGE_LEVELS),
            'photo' => 'required_if:condition,damaged|nullable|image|max:10240',
        ]);
        $circuit = $this->circuit($ctx);
        $sled = $circuit->findSled($v['sled_code']);
        if (! $sled) {
            return $this->error('Codul nu aparține unei sănii.', 404);
        }
        $rental = $circuit->activeRentalForSled($sled);
        if (! $rental || $rental->status !== 'active') {
            return $this->error($rental ? 'Returnarea acestei sănii e deja înregistrată.' : $sled->displayName().' nu este închiriată.', 422);
        }
        if ($rental->is_test !== $circuit->isTest()) {
            return $this->error('Sania aparține celuilalt mod (Test/Activ).', 422);
        }

        $photoPath = null;
        if ($v['condition'] === 'damaged' && $request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('sanius/'.$ctx['event']->id.'/damage', 'local');
        }

        try {
            $rental = $circuit->requestReturn($rental, $v['condition'], $v['damage_level'] ?? null, $photoPath, $this->memberOrNull($ctx));
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $closed = $rental->status === 'closed';

        return $this->success(
            ['rental' => $this->presentRental($rental)],
            $closed
                ? 'Sanie primită. De plată la casierie: '.number_format((float) ($rental->amounts['total'] ?? 0), 2, ',', '.').' lei.'
                : 'Sanie primită. Se așteaptă sincronizarea mașinii de tractare; suma apare automat.'
        );
    }

    /** GET .../sanius/rentals/{rental} — ghiseul urmareste o returnare in asteptare. */
    public function rentalsShow(Request $request, int $event, int $rental): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $model = SaniusRental::with('sled')->where('id', $rental)->where('event_id', $ctx['event']->id)->first();
        if (! $model) {
            return $this->error('Închirierea nu există.', 404);
        }
        if ($model->status === 'pending_sync') {
            $this->circuit($ctx)->closePendingReturns();
            $model->refresh();
        }

        return $this->success(['rental' => $this->presentRental($model)]);
    }

    /** GET .../sanius/rentals/{rental}/photo */
    public function rentalsPhoto(Request $request, int $event, int $rental)
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $model = SaniusRental::where('id', $rental)->where('event_id', $ctx['event']->id)->first();
        if (! $model || ! $model->damage_photo_path || ! Storage::disk('local')->exists($model->damage_photo_path)) {
            return $this->error('Poza nu există.', 404);
        }

        return Storage::disk('local')->response($model->damage_photo_path);
    }

    /** POST .../sanius/rentals/{rental}/lost — doar managerul. */
    public function rentalsLost(Request $request, int $event, int $rental): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $model = SaniusRental::where('id', $rental)->where('event_id', $ctx['event']->id)
            ->where('is_test', $ctx['settings']->isTestMode())->first();
        if (! $model) {
            return $this->error('Închirierea nu există.', 404);
        }
        try {
            $model = $this->circuit($ctx)->markLost($model);
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['rental' => $this->presentRental($model)], 'Sanie trecută ca pierdută.');
    }

    private function presentRental(SaniusRental $r): array
    {
        $r->loadMissing('sled');

        return [
            'id' => $r->id,
            'tab_id' => $r->tab_id,
            'sled' => $r->sled?->displayName(),
            'sled_number' => $r->sled?->number,
            'status' => $r->status,
            'started_at' => $r->started_at?->toIso8601String(),
            'returned_at' => $r->returned_at?->toIso8601String(),
            'closed_at' => $r->closed_at?->toIso8601String(),
            'condition' => $r->condition,
            'damage_level' => $r->damage_level,
            'has_photo' => (bool) $r->damage_photo_path,
            'tows_count' => $r->status === 'closed' || $r->status === 'lost'
                ? $r->tows_count
                : SaniusTow::where('rental_id', $r->id)->where('status', 'counted')->count(),
            'amounts' => $r->amounts,
            'is_test' => $r->is_test,
        ];
    }

    private function findTab(array $ctx, int $id): ?SaniusTab
    {
        return SaniusTab::where('id', $id)
            ->where('event_id', $ctx['event']->id)
            ->where('is_test', $ctx['settings']->isTestMode())
            ->first();
    }

    // ==================================================================
    // Sofer tractare
    // ==================================================================

    /** POST .../sanius/tow/shift/start { device_id } — o data pe zi, sus, cu semnal. */
    public function towShiftStart(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['tow']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate(['device_id' => 'required|string|max:64']);
        $isTest = $ctx['settings']->isTestMode();
        $memberId = $this->memberOrNull($ctx);

        $open = SaniusTowShift::where('event_id', $ctx['event']->id)->where('is_test', $isTest)
            ->whereNull('ended_at')
            ->where(fn ($q) => $memberId ? $q->where('member_id', $memberId) : $q->whereNull('member_id'))
            ->get();
        $same = $open->firstWhere('device_id', $v['device_id']);
        if ($same) {
            return $this->success($this->presentShift($same, $ctx), 'Tura e deja deschisă pe acest telefon.');
        }
        if ($open->isNotEmpty()) {
            return $this->error('Ai deja o tură deschisă pe alt telefon. Închide-o acolo sau cere managerului să o închidă.', 409);
        }

        $shift = SaniusTowShift::create([
            'event_id' => $ctx['event']->id,
            'member_id' => $memberId,
            'device_id' => $v['device_id'],
            'started_at' => Carbon::now(),
            'last_ping_at' => Carbon::now(),
            'pending_count' => 0,
            'is_test' => $isTest,
        ]);

        return $this->success($this->presentShift($shift, $ctx), 'Tură începută.', 201);
    }

    /** POST .../sanius/tow/shift/end { device_id, pending_count } — refuzat daca mai sunt scanari netrimise. */
    public function towShiftEnd(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['tow']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'device_id' => 'required|string|max:64',
            'pending_count' => 'required|integer|min:0',
        ]);
        $shift = $this->deviceShift($ctx, $v['device_id']);
        if (! $shift) {
            return $this->error('Nu ai o tură deschisă pe acest telefon.', 404);
        }
        if ((int) $v['pending_count'] > 0) {
            return $this->error('Mai ai '.$v['pending_count'].' scanări netrimise. Așteaptă sincronizarea.', 409);
        }
        $shift->update(['ended_at' => Carbon::now(), 'last_ping_at' => Carbon::now(), 'pending_count' => 0]);
        $this->circuit($ctx)->closePendingReturns();

        return $this->success($this->presentShift($shift->fresh(), $ctx), 'Tură încheiată.');
    }

    /**
     * POST .../sanius/tow/ping { device_id, device_now, pending_after, scans: [{uuid, sled_code, scanned_at}] }
     * Aplicatia il trimite la cateva secunde. Raspunde cu scanarile confirmate (care
     * se pot rescana) si lista de sanii pentru avertizari offline.
     */
    public function towPing(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['tow']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'device_id' => 'required|string|max:64',
            'device_now' => 'nullable|integer',
            'pending_after' => 'nullable|integer|min:0',
            'scans' => 'nullable|array|max:300',
            'scans.*.uuid' => 'required|string|max:64',
            'scans.*.sled_code' => 'required|string|max:60',
            'scans.*.scanned_at' => 'nullable',
            'want_roster' => 'nullable|boolean',
        ]);
        $shift = $this->deviceShift($ctx, $v['device_id']);
        if (! $shift) {
            return $this->error('Nu ai o tură deschisă pe acest telefon.', 404);
        }

        $circuit = $this->circuit($ctx);
        $acked = $circuit->ingestTows($shift, $v['scans'] ?? [], $v['device_now'] ?? null, (int) ($v['pending_after'] ?? 0));
        $closed = $circuit->closePendingReturns();

        $data = [
            'server_now' => (int) round(Carbon::now()->valueOf()),
            'acked' => $acked,
            'closed_returns' => $closed,
            'shift' => $this->presentShift($shift->fresh(), $ctx),
        ];
        if (! empty($v['want_roster'])) {
            $data['roster'] = SaniusSled::where('event_id', $ctx['event']->id)->where('is_test', $shift->is_test)
                ->orderBy('number')->get(['code', 'number', 'status', 'label', 'is_test'])
                ->map(fn ($s) => ['code' => $s->code, 'number' => $s->number, 'name' => $s->displayName(), 'status' => $s->status])
                ->values();
        }

        return $this->success($data);
    }

    private function deviceShift(array $ctx, string $deviceId): ?SaniusTowShift
    {
        $memberId = $this->memberOrNull($ctx);

        return SaniusTowShift::where('event_id', $ctx['event']->id)
            ->where('is_test', $ctx['settings']->isTestMode())
            ->where('device_id', $deviceId)
            ->whereNull('ended_at')
            ->where(fn ($q) => $memberId ? $q->where('member_id', $memberId) : $q->whereNull('member_id'))
            ->first();
    }

    private function presentShift(SaniusTowShift $s, array $ctx): array
    {
        $timeout = (int) ($ctx['settings']->ping_timeout_seconds ?: 30);
        $member = $s->member_id ? MarketplaceOrganizerTeamMember::find($s->member_id) : null;

        return [
            'id' => $s->id,
            'member' => $member?->name ?? 'Cont principal',
            'device_id' => $s->device_id,
            'started_at' => $s->started_at?->toIso8601String(),
            'ended_at' => $s->ended_at?->toIso8601String(),
            'last_ping_at' => $s->last_ping_at?->toIso8601String(),
            'pending_count' => $s->pending_count,
            'blocking' => $s->isBlocking(Carbon::now(), $timeout),
            'force_unlocked_at' => $s->force_unlocked_at?->toIso8601String(),
            'tows_today' => SaniusTow::where('shift_id', $s->id)->where('status', 'counted')->count(),
        ];
    }

    /** GET .../sanius/tow/status — ghiseul vede daca returnarile asteapta vreun sofer. */
    public function towStatus(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['desk', 'cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $circuit = $this->circuit($ctx);
        $shifts = $circuit->activeShifts();

        return $this->success([
            'blocked' => $circuit->isTowSyncBlocked(),
            'shifts' => $shifts->map(fn ($s) => $this->presentShift($s, $ctx))->values(),
            'pending_returns' => SaniusRental::where('event_id', $ctx['event']->id)
                ->where('is_test', $circuit->isTest())->where('status', 'pending_sync')->count(),
        ]);
    }

    /** POST .../sanius/tow/shifts/{shift}/unlock { reason } — telefon descarcat / stricat. Doar managerul. */
    public function towShiftUnlock(Request $request, int $event, int $shift): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate(['reason' => 'required|string|min:3|max:500']);
        $model = SaniusTowShift::where('id', $shift)->where('event_id', $ctx['event']->id)->whereNull('ended_at')->first();
        if (! $model) {
            return $this->error('Tura nu există sau e închisă.', 404);
        }
        $model->update([
            'force_unlocked_at' => Carbon::now(),
            'force_unlocked_by_member_id' => $this->memberOrNull($ctx),
            'force_unlock_reason' => $v['reason'],
        ]);
        $closed = $this->circuit($ctx)->closePendingReturns();

        return $this->success(['closed_returns' => $closed, 'shift' => $this->presentShift($model->fresh(), $ctx)], 'Deblocare forțată înregistrată.');
    }

    /** POST .../sanius/tow/shifts/{shift}/close — managerul inchide tura unui telefon pierdut. */
    public function towShiftClose(Request $request, int $event, int $shift): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate(['reason' => 'required|string|min:3|max:500']);
        $model = SaniusTowShift::where('id', $shift)->where('event_id', $ctx['event']->id)->whereNull('ended_at')->first();
        if (! $model) {
            return $this->error('Tura nu există sau e închisă.', 404);
        }
        $model->update([
            'ended_at' => Carbon::now(),
            'force_unlocked_at' => $model->force_unlocked_at ?? Carbon::now(),
            'force_unlocked_by_member_id' => $this->memberOrNull($ctx),
            'force_unlock_reason' => trim(($model->force_unlock_reason ? $model->force_unlock_reason.' | ' : '').'Închisă de manager: '.$v['reason']),
        ]);
        $closed = $this->circuit($ctx)->closePendingReturns();

        return $this->success(['closed_returns' => $closed], 'Tură închisă de manager.');
    }

    // ==================================================================
    // Casierie
    // ==================================================================

    /** GET .../sanius/tabs/lookup?q=&vehicle_type= — dupa tichet de parcare, bilet, sanie sau nr. auto. */
    public function tabsLookup(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'q' => 'required|string|max:200',
            'vehicle_type' => 'nullable|in:car,bus',
        ]);
        $circuit = $this->circuit($ctx);
        $q = trim($v['q']);
        $isTest = $circuit->isTest();
        $base = fn () => SaniusTab::where('event_id', $ctx['event']->id)->where('is_test', $isTest);

        $tab = null;
        $foundBy = null;
        if ($parsed = ParkingTicketCode::parse($q)) {
            $tab = $base()->where('parking_code', $parsed['code'])->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
                ->orderByDesc('id')->first();
            $foundBy = 'parking';
            if (! $tab) {
                return $this->success([
                    'found' => false,
                    'parking_ticket' => true,
                    'message' => 'Tichetul nu are cont Sanius: nu are nimic de plată la noi.',
                ]);
            }
        }
        if (! $tab && ($sled = $circuit->findSled($q))) {
            $rental = SaniusRental::where('sled_id', $sled->id)->where('is_test', $isTest)->orderByDesc('started_at')->first();
            $tab = $rental ? $base()->whereKey($rental->tab_id)->first() : null;
            $foundBy = 'sled';
        }
        if (! $tab && ($ticket = $circuit->findTicket($q))) {
            $tab = $base()->whereHas('tickets', fn ($t) => $t->where('ticket_id', $ticket->id))
                ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")->orderByDesc('id')->first();
            $foundBy = 'ticket';
        }
        if (! $tab) {
            $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $q));
            if (strlen($plate) >= 4) {
                $tab = $base()->where('vehicle_plate', $plate)->where('status', 'open')->orderByDesc('id')->first();
                $foundBy = 'plate';
            }
        }
        if (! $tab) {
            return $this->success(['found' => false, 'message' => 'Nu am găsit niciun cont Sanius.']);
        }

        return $this->success(array_merge(['found' => true, 'found_by' => $foundBy], $circuit->tabSummary($tab, $v['vehicle_type'] ?? null)));
    }

    /**
     * POST .../sanius/tabs/{tab}/settle { vehicle_type, payment_method }
     * Modul Test: plata simulata. Modul Activ: incasarea reala vine in etapa urmatoare.
     */
    public function tabsSettle(Request $request, int $event, int $tab): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'vehicle_type' => 'required|in:car,bus',
            'payment_method' => 'required|in:cash,card',
        ]);
        $tabModel = $this->findTab($ctx, $tab);
        if (! $tabModel) {
            return $this->error('Contul nu există.', 404);
        }
        if (! $tabModel->isOpen()) {
            return $this->error('Contul a fost deja achitat.', 422);
        }

        $circuit = $this->circuit($ctx);
        if (! $circuit->isTest()) {
            return $this->error('Încasarea reală nu este încă activă.', 409);
        }

        try {
            $tabModel = $circuit->settleSimulated($tabModel, $v['vehicle_type'], $v['payment_method'], $this->memberOrNull($ctx));
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            $circuit->tabSummary($tabModel),
            'TEST: plată simulată. Poți valida tichetul în Mohos.'
        );
    }

    // ==================================================================
    // Rapoarte
    // ==================================================================

    /** GET .../sanius/reports/overview?date=YYYY-MM-DD&is_test=0|1 */
    public function reportsOverview(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['manager'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'date' => 'nullable|date',
            'is_test' => 'nullable|boolean',
        ]);
        $tz = ParkingTicketCode::TZ;
        $day = isset($v['date']) ? Carbon::parse($v['date'], $tz) : Carbon::now($tz);
        $from = $day->copy()->startOfDay()->utc();
        $to = $day->copy()->endOfDay()->utc();
        $isTest = array_key_exists('is_test', $v) ? (bool) $v['is_test'] : $ctx['settings']->isTestMode();
        $eventId = $ctx['event']->id;

        $rentals = SaniusRental::with(['sled', 'tab'])->where('event_id', $eventId)->where('is_test', $isTest)
            ->whereBetween('started_at', [$from, $to])->orderBy('started_at')->get();
        $tows = SaniusTow::where('event_id', $eventId)->where('is_test', $isTest)
            ->whereBetween('scanned_at', [$from, $to])->orderBy('scanned_at')->get();

        // Intervalele dintre tractarile aceleiasi inchirieri: un gol mult peste mediana zilei
        // poate insemna o scanare uitata de sofer.
        $gaps = [];
        foreach ($tows->where('status', 'counted')->groupBy('rental_id') as $rentalId => $list) {
            $prev = $rentals->firstWhere('id', $rentalId)?->started_at;
            foreach ($list as $t) {
                if ($prev) {
                    $gaps[] = ['rental_id' => (int) $rentalId, 'minutes' => $prev->diffInMinutes($t->scanned_at)];
                }
                $prev = $t->scanned_at;
            }
        }
        $minutes = collect($gaps)->pluck('minutes')->sort()->values();
        $median = $minutes->isEmpty() ? null : (float) $minutes->get(intdiv($minutes->count(), 2));
        $suspectGaps = $median ? collect($gaps)->filter(fn ($g) => $g['minutes'] > max(3 * $median, $median + 20))->values() : collect();

        $tabs = SaniusTab::where('event_id', $eventId)->where('is_test', $isTest)
            ->whereBetween('created_at', [$from, $to])->get();

        return $this->success([
            'date' => $day->toDateString(),
            'is_test' => $isTest,
            'totals' => [
                'tabs' => $tabs->count(),
                'tabs_open' => $tabs->where('status', 'open')->count(),
                'tabs_settled' => $tabs->where('status', 'settled')->count(),
                'settled_amount' => round($tabs->where('status', 'settled')->sum(fn ($t) => (float) ($t->settlement['total'] ?? 0)), 2),
                'rentals' => $rentals->count(),
                'rentals_open' => $rentals->whereIn('status', ['active', 'pending_sync'])->count(),
                'damaged' => $rentals->where('condition', 'damaged')->count(),
                'lost' => $rentals->where('status', 'lost')->count(),
                'tows_counted' => $tows->where('status', 'counted')->count(),
                'tows_anomalies' => $tows->where('status', 'anomaly')->count(),
                'median_minutes_between_tows' => $median,
            ],
            'open_rentals' => $rentals->whereIn('status', ['active', 'pending_sync'])->map(fn ($r) => $this->presentRental($r))->values(),
            'anomalies' => $tows->where('status', 'anomaly')->map(fn ($t) => [
                'id' => $t->id, 'sled_code' => $t->sled_code, 'reason' => $t->anomaly_reason,
                'scanned_at' => $t->scanned_at?->toIso8601String(), 'shift_id' => $t->shift_id,
            ])->values(),
            'suspect_gaps' => $suspectGaps,
            'force_unlocks' => SaniusTowShift::where('event_id', $eventId)->where('is_test', $isTest)
                ->whereNotNull('force_unlocked_at')->whereBetween('force_unlocked_at', [$from, $to])
                ->get()->map(fn ($s) => [
                    'shift_id' => $s->id, 'at' => $s->force_unlocked_at?->toIso8601String(),
                    'by_member_id' => $s->force_unlocked_by_member_id, 'reason' => $s->force_unlock_reason,
                ])->values(),
        ]);
    }
}
