<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sanius\SaniusContract;
use App\Models\Sanius\SaniusPrepaid;
use App\Models\Sanius\SaniusRental;
use App\Models\Sanius\SaniusSetting;
use App\Models\Sanius\SaniusSled;
use App\Models\Sanius\SaniusTab;
use App\Models\Sanius\SaniusTow;
use App\Models\Sanius\SaniusTowShift;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Sanius\ParkingTicketCode;
use App\Services\Sanius\SaniusCircuit;
use App\Services\Sanius\SaniusException;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
    /** Modul Activ: incasarea reala prin posSale (comanda, sesiunea de casa, comision, societatea emitenta). */
    private const LIVE_AVAILABLE = true;

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
        return new SaniusCircuit($ctx['settings'], $ctx['event'], $ctx['organizer']);
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
            // Rolul pe web (contul principal = owner); leisure-pos arata blocul Sanius dupa el.
            'web_role' => $ctx['role'],
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
            'rental' => 'nullable|array',
            'rental.cycle_minutes' => 'nullable|integer|min:5|max:1440',
            'rental.tows_per_cycle' => 'nullable|integer|min:0|max:100',
            'rental.grace_minutes' => 'nullable|integer|min:0|max:120',
            'rental.weekend_extra_dates' => 'nullable|array|max:120',
            'rental.weekend_extra_dates.*' => 'nullable|date_format:Y-m-d',
            'contract' => 'nullable|array',
            'contract.required' => 'nullable|boolean',
            'contract.title' => 'nullable|string|max:160',
            'contract.body' => 'nullable|string|max:20000',
            'contract.stations' => 'nullable|array|max:20',
            'contract.stations.*' => 'nullable|string|max:40',
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
        if (array_key_exists('rental', $v)) {
            $r = (array) $v['rental'];
            $own = (array) $s->rental;
            foreach (array_keys(SaniusSetting::RENTAL_DEFAULTS) as $k) {
                if (array_key_exists($k, $r)) {
                    $own[$k] = $r[$k] === null || $r[$k] === '' ? null : (int) $r[$k];
                }
            }
            if (array_key_exists('weekend_extra_dates', $r)) {
                $own['weekend_extra_dates'] = SaniusSetting::cleanDates($r['weekend_extra_dates'] ?? []);
            }
            $s->rental = $own;
        }
        if (array_key_exists('contract', $v)) {
            $c = (array) $v['contract'];
            $before = $s->contractRules();
            $own = (array) $s->contract;
            if (array_key_exists('required', $c)) {
                $own['required'] = (bool) $c['required'];
            }
            foreach (['title', 'body'] as $k) {
                if (array_key_exists($k, $c)) {
                    $own[$k] = trim((string) $c[$k]) ?: null;
                }
            }
            if (array_key_exists('stations', $c)) {
                $own['stations'] = array_values(array_unique(array_filter(array_map(
                    fn ($x) => mb_substr(trim((string) $x), 0, 40),
                    (array) ($c['stations'] ?? [])
                ))));
            }
            $s->contract = $own;
            $after = $s->contractRules();
            // Versiunea textului creste la fiecare schimbare, ca fiecare contract semnat sa stie ce text a vazut.
            if ($after['title'] !== $before['title'] || $after['body'] !== $before['body']) {
                $own['version'] = $before['version'] + 1;
                $s->contract = $own;
            }
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
                if (! $wasTest) {
                    $unpaid = SaniusTab::where('event_id', $ctx['event']->id)->where('is_test', false)->where('status', 'open')
                        ->whereHas('rentals')->count();
                    if ($unpaid) {
                        return $this->error('Există '.$unpaid.' cont(uri) cu sănii returnate și neachitate. Încasează-le înainte de a schimba modul.', 422);
                    }
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
            // Cardul din dashboard apare doar la organizatorii din config (sau unde circuitul a fost deja folosit).
            'available' => $s->exists || in_array((int) $ctx['organizer']->id, (array) config('services.sanius.organizer_ids', []), true),
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
            'rental' => $s->rentalRules(),
            'contract' => $s->contractRules(),
            'contract_variables' => ['{nume}', '{act_tip}', '{act_serie}', '{act_numar}', '{telefon}', '{adresa}', '{data}', '{ora}', '{tichet_parcare}', '{sanii}', '{organizator}'],
            'today_day_type' => $circuit->dayType(),
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
            'cycle_weekday' => ['Sanius · Ciclu sanie (luni–vineri)', 'rental', false],
            'cycle_weekend' => ['Sanius · Ciclu sanie (weekend și sărbători)', 'rental', false],
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
        if (! $circuit->isAccessTicket($ticket)) {
            return $this->error('Nu este bilet de acces.', 422);
        }
        $visit = $circuit->ticketVisitDate($ticket);
        if ($visit !== null && $visit !== $circuit->tabDay($tabModel)) {
            return $this->error('Biletul este pentru '.Carbon::parse($visit)->format('d.m.Y').', nu pentru azi.', 422);
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
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }

            return $this->error($e->getMessage(), 422);
        }

        $closed = $rental->status === 'closed';

        return $this->success(
            ['rental' => $this->presentRental($rental)],
            $closed
                ? 'Sanie primită: '.$this->rentalLine($rental).'. Restul se plătește la casierie.'
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
            'day_type' => $r->day_type,
            'minutes' => $r->duration_minutes,
            'cycles_used' => $r->cycles_used,
            'amounts' => $r->amounts,
            'is_test' => $r->is_test,
        ];
    }

    /** „1 h 25 min, 2 cicluri, 5 tractări (2 incluse)” pentru mesajele ghiseului. */
    private function rentalLine(SaniusRental $r): string
    {
        $m = (int) ($r->duration_minutes ?? 0);
        $time = $m >= 60 ? intdiv($m, 60).' h '.($m % 60).' min' : $m.' min';
        $cycles = (int) ($r->cycles_used ?? 1);

        return $time.', '.$cycles.' '.($cycles === 1 ? 'ciclu' : 'cicluri').', '
            .(int) $r->tows_count.' tractări ('.(int) $r->tows_included.' incluse)';
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
            $tab = $base()->where('parking_code', $parsed['code'])->where('status', 'open')->orderByDesc('id')->first()
                ?? $base()->where('parking_code', $parsed['code'])->orderByDesc('id')->first();
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
            $byTicket = fn () => $base()->whereHas('tickets', fn ($t) => $t->where('ticket_id', $ticket->id));
            $tab = $byTicket()->where('status', 'open')->orderByDesc('id')->first()
                ?? $byTicket()->orderByDesc('id')->first();
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
            'expected_total' => 'nullable|numeric|min:0',
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
            return $this->settleLive($request, $ctx, $circuit, $tabModel, $v['vehicle_type'], $v['payment_method'],
                isset($v['expected_total']) ? (float) $v['expected_total'] : null);
        }

        if (isset($v['expected_total'])) {
            $now = (float) $circuit->tabSummary($tabModel, $v['vehicle_type'])['total'];
            if (abs($now - (float) $v['expected_total']) > 0.009) {
                return $this->error('Suma s-a schimbat: acum sunt '.number_format($now, 2, ',', '.').' lei. Nu s-a încasat nimic; confirmă din nou.', 422);
            }
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

    /**
     * Incasarea reala: o vanzare posSale cu produsele circuitului (aceeasi comanda,
     * sesiune de casa, comision si societate emitenta ca orice vanzare POS), totul
     * intr-o singura tranzactie cu inchiderea contului. Daca totalul comenzii nu e
     * exact cel aratat casierului, nu se salveaza nimic.
     */
    private function settleLive(Request $request, array $ctx, SaniusCircuit $circuit, SaniusTab $tab, string $vehicleType, string $paymentMethod, ?float $expectedTotal = null): JsonResponse
    {
        $eventId = $ctx['event']->id;

        try {
            $result = DB::transaction(function () use ($request, $ctx, $circuit, $tab, $vehicleType, $paymentMethod, $eventId, $expectedTotal) {
                $locked = SaniusTab::whereKey($tab->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->isOpen()) {
                    throw new SaniusException('Contul a fost deja achitat.');
                }
                $summary = $circuit->tabSummary($locked, $vehicleType);
                if (! $summary['can_settle']) {
                    throw new SaniusException(implode(' ', $summary['blockers']));
                }
                $charge = $summary['charge'];
                if (empty($charge['lines'])) {
                    // Totul platit la intrare, fara depasiri si fara parcare: se inchide cu 0 lei.
                    $locked->update([
                        'status' => 'settled',
                        'vehicle_type' => $vehicleType,
                        'settled_at' => Carbon::now(),
                        'settled_by_member_id' => $this->memberOrNull($ctx),
                        'settled_simulated' => false,
                        'settlement' => [
                            'payment_method' => null,
                            'rentals_total' => 0,
                            'parking' => $summary['parking'],
                            'charge' => $charge,
                            'total' => 0,
                        ],
                    ]);

                    return ['tab' => $locked->fresh(), 'sale' => null];
                }
                // Parcarea poate trece in intervalul urmator intre afisare si plata.
                if ($expectedTotal !== null && abs($expectedTotal - (float) $charge['total']) > 0.009) {
                    throw new SaniusException('Suma s-a schimbat: acum sunt '.number_format((float) $charge['total'], 2, ',', '.')
                        .' lei. Nu s-a încasat nimic; confirmă din nou.');
                }

                $label = 'Sanius · tichet parcare nr. '.($locked->parking_number ?: $locked->parking_code);
                $sub = Request::create($request->getUri(), 'POST', [
                    'items' => array_map(fn ($l) => ['ticket_type_id' => $l['ticket_type_id'], 'qty' => $l['qty']], $charge['lines']),
                    'customer' => array_filter([
                        'name' => $label,
                        'vehicle_plate' => $locked->vehicle_plate,
                    ]),
                    'payment_method' => $paymentMethod,
                ]);
                $sub->setUserResolver(fn () => $request->user());
                $sub->attributes->set('sanius_settle_ticket_type_ids', array_values(array_unique(array_map(
                    fn ($l) => (int) $l['ticket_type_id'],
                    $charge['lines']
                ))));

                try {
                    $response = app(LeisureController::class)->posSale($sub, $eventId);
                } catch (ValidationException $e) {
                    throw new SaniusException(collect($e->errors())->flatten()->first() ?: 'Date invalide pentru vânzare.');
                }
                $payload = $response->getData(true);
                if (! $response->isSuccessful() || empty($payload['success'])) {
                    throw new SaniusException($payload['message'] ?? 'Vânzarea POS a eșuat.');
                }

                $order = $payload['data']['order'] ?? [];
                $orderTotal = round((float) ($order['total'] ?? 0), 2);
                if (abs($orderTotal - (float) $charge['total']) > 0.009) {
                    throw new SaniusException('Totalul comenzii ('.number_format($orderTotal, 2, ',', '.').' lei) diferă de cel afișat ('
                        .number_format((float) $charge['total'], 2, ',', '.').' lei). Nu s-a încasat nimic; reîncarcă contul.');
                }

                $orderModel = Order::find($order['id'] ?? 0);
                if ($orderModel) {
                    $meta = is_array($orderModel->meta) ? $orderModel->meta : [];
                    $meta['sanius_tab_id'] = $locked->id;
                    $meta['sanius_parking_code'] = $locked->parking_code;
                    $orderModel->meta = $meta;
                    $orderModel->save();
                }

                $locked->update([
                    'status' => 'settled',
                    'vehicle_type' => $vehicleType,
                    'settled_at' => Carbon::now(),
                    'settled_by_member_id' => $this->memberOrNull($ctx),
                    'settled_simulated' => false,
                    'order_id' => $order['id'] ?? null,
                    'settlement' => [
                        'payment_method' => $paymentMethod,
                        'rentals_total' => $summary['rentals_total'],
                        'parking' => $summary['parking'],
                        'charge' => $charge,
                        'total' => $orderTotal,
                        'order_number' => $order['order_number'] ?? null,
                    ],
                ]);

                return ['tab' => $locked->fresh(), 'sale' => $payload['data']];
            });
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        if ($result['sale'] === null) {
            return $this->success($circuit->tabSummary($result['tab']), 'Nimic de plată. Contul e închis.');
        }

        return $this->success(array_merge(
            $circuit->tabSummary($result['tab']),
            ['sale' => [
                'order' => $result['sale']['order'] ?? null,
                'items' => $result['sale']['items'] ?? [],
                'issuer' => $result['sale']['issuer'] ?? null,
            ]]
        ), 'Încasat. Poți valida tichetul în Mohos.');
    }

    // ==================================================================
    // Plata la intrare (casierie, leisure-pos)
    // ==================================================================

    /** GET .../sanius/prepay/quote?sleds=&cycles= — pretul ciclului azi, pentru blocul din leisure-pos. */
    public function prepayQuote(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'sleds' => 'nullable|integer|min:0|max:50',
            'cycles' => 'nullable|integer|min:0|max:24',
        ]);
        $circuit = $this->circuit($ctx);
        $q = $circuit->prepayQuote((int) ($v['sleds'] ?? 1), (int) ($v['cycles'] ?? 1));
        $q['available'] = (bool) $q['ticket_type_id'] && TicketType::where('id', $q['ticket_type_id'])->where('event_id', $ctx['event']->id)->exists();
        $q['mode'] = $ctx['settings']->mode;
        $q['is_test'] = $circuit->isTest();
        $q['contract'] = [
            'required' => $ctx['settings']->contractRules()['required'],
            'stations' => $ctx['settings']->contractRules()['stations'],
        ];

        return $this->success($q);
    }

    /**
     * POST .../sanius/prepay { parking_code, sleds, cycles, expected_unit_price?, sale: {corpul posSale} }
     *
     * Biletele din cos + ciclurile de sanie intr-o singura vanzare posSale (un bon).
     * Ciclurile se trec pe contul tichetului de parcare ca si credit, iar biletele de
     * acces vandute se leaga de cont. In modul Test biletele se vand normal, dar
     * ciclurile se trec pe cont fara sa fie incasate.
     */
    public function prepay(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'parking_code' => 'required|string|max:40',
            'sleds' => 'required|integer|min:1|max:50',
            'cycles' => 'required|integer|min:1|max:24',
            'expected_unit_price' => 'nullable|numeric|min:0',
            'sale' => 'required|array',
            'sale.items' => 'nullable|array|max:48',
            'sale.items.*.ticket_type_id' => 'required|integer',
            'sale.items.*.qty' => 'required|integer|min:1|max:100',
            'sale.payment_method' => 'required|in:cash,card',
            'sale.date' => 'nullable|date',
        ]);

        $parsed = ParkingTicketCode::parse($v['parking_code']);
        if (! $parsed) {
            return $this->error('Nu e un tichet de parcare valid (cod de 15 cifre).', 422);
        }
        if (! ParkingTicketCode::isFromToday($parsed['entry_at'])) {
            return $this->error('Tichetul de parcare nu este din ziua de azi.', 422);
        }
        $today = Carbon::now(ParkingTicketCode::TZ)->toDateString();
        if (! empty($v['sale']['date']) && Carbon::parse($v['sale']['date'])->toDateString() !== $today) {
            return $this->error('Săniile se plătesc doar pentru ziua de azi. Schimbă data vizitei pe azi sau scoate săniile din coș.', 422);
        }

        $eventId = $ctx['event']->id;
        $circuit = $this->circuit($ctx);
        $quote = $circuit->prepayQuote((int) $v['sleds'], (int) $v['cycles']);
        if (! $quote['ticket_type_id'] || ! TicketType::where('id', $quote['ticket_type_id'])->where('event_id', $eventId)->exists()) {
            return $this->error('Lipsește produsul pentru ciclul de sanie ('.($quote['day_type'] === 'weekend' ? 'weekend' : 'luni–vineri').'). Generează-l din setările Sanius.', 422);
        }
        if ($quote['unit_price'] <= 0) {
            return $this->error('Ciclul de sanie nu are preț. Setează prețul produsului înainte de vânzare.', 422);
        }
        if (isset($v['expected_unit_price']) && abs((float) $v['expected_unit_price'] - $quote['unit_price']) > 0.009) {
            return $this->error('Prețul ciclului s-a schimbat: acum e '.number_format($quote['unit_price'], 2, ',', '.').' lei. Nu s-a încasat nimic; verifică și încearcă din nou.', 422);
        }

        // Liniile intregi din cerere (variante, ore, extra-uri); posSale le valideaza mai departe.
        $saleItems = array_values(array_filter((array) $request->input('sale.items', []), 'is_array'));
        $cycleIds = array_filter([$circuit->settings->productId('cycle_weekday'), $circuit->settings->productId('cycle_weekend')]);
        $types = TicketType::whereIn('id', collect($saleItems)->pluck('ticket_type_id')->map(fn ($id) => (int) $id))
            ->where('event_id', $eventId)->get()->keyBy('id');
        $accessInCart = false;
        foreach ($saleItems as $row) {
            if (in_array((int) $row['ticket_type_id'], $cycleIds, true)) {
                return $this->error('Ciclurile de sanie se adaugă doar din blocul Sanius.', 422);
            }
            $tt = $types->get((int) $row['ticket_type_id']);
            if ($tt && in_array($tt->effective_service_category ?? 'access', ['access', 'package'], true)) {
                $accessInCart = true;
            }
        }

        $memberId = $this->memberOrNull($ctx);
        $tab = $circuit->openTab($parsed, $memberId);
        if (! $accessInCart && ! $circuit->tabHasAccessTicket($tab)) {
            return $this->error('Fără bilet de acces nu se închiriază sănii. Adaugă biletele de acces în coș sau leagă biletul cumpărat online de tichetul de parcare.', 422);
        }

        $live = ! $circuit->isTest();
        $items = $saleItems;
        if ($live) {
            $n = $quote['sled_cycles'];
            while ($n > 0) {
                $chunk = min(100, $n);
                $items[] = ['ticket_type_id' => $quote['ticket_type_id'], 'qty' => $chunk];
                $n -= $chunk;
            }
        }
        if (count($items) > 50) {
            return $this->error('Prea multe linii în coș.', 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $v, $items, $live, $quote, $circuit, $tab, $memberId, $eventId) {
                $locked = SaniusTab::whereKey($tab->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->isOpen()) {
                    throw new SaniusException('Contul acestui tichet a fost deja închis la ieșire. Nu s-a încasat nimic.');
                }
                $payload = null;
                $orderId = null;
                if ($items) {
                    $body = (array) $request->input('sale', []);
                    $body['items'] = $items;
                    $sub = Request::create($request->getUri(), 'POST', $body);
                    $sub->setUserResolver(fn () => $request->user());
                    if ($live) {
                        $sub->attributes->set('sanius_settle_ticket_type_ids', [(int) $quote['ticket_type_id']]);
                    }
                    try {
                        $response = app(LeisureController::class)->posSale($sub, $eventId);
                    } catch (ValidationException $e) {
                        throw new SaniusException(collect($e->errors())->flatten()->first() ?: 'Date invalide pentru vânzare.');
                    }
                    $payload = $response->getData(true);
                    if (! $response->isSuccessful() || empty($payload['success'])) {
                        throw new SaniusException($payload['message'] ?? 'Vânzarea POS a eșuat.');
                    }
                    $orderId = (int) ($payload['data']['order']['id'] ?? 0) ?: null;

                    if ($live) {
                        $lines = OrderItem::where('order_id', $orderId)->where('ticket_type_id', $quote['ticket_type_id'])->get();
                        $unit = (float) ($lines->first()->unit_price ?? -1);
                        if ((int) $lines->sum('quantity') !== $quote['sled_cycles'] || abs($unit - $quote['unit_price']) > 0.009) {
                            throw new SaniusException('Prețul ciclului din comandă diferă de cel afișat. Nu s-a încasat nimic; reîncarcă pagina.');
                        }
                    }

                    if ($orderId) {
                        $first = Ticket::with(['ticketType', 'order'])->where('order_id', $orderId)->get()
                            ->first(fn ($t) => $circuit->isAccessTicket($t) && $circuit->ticketIsUsable($t));
                        if ($first) {
                            $circuit->linkTicketWithOrder($tab, $first, $memberId, 'cashier');
                        }
                        $order = Order::find($orderId);
                        if ($order) {
                            $meta = is_array($order->meta) ? $order->meta : [];
                            $meta['sanius_tab_id'] = $tab->id;
                            $meta['sanius_parking_code'] = $tab->parking_code;
                            $meta['sanius_prepaid_sled_cycles'] = $live ? $quote['sled_cycles'] : 0;
                            $order->meta = $meta;
                            $order->save();
                        }
                    }
                }

                $circuit->recordPrepaid($tab, $quote, $live ? $orderId : null, $v['sale']['payment_method'], ! $live, $memberId);

                return ['payload' => $payload, 'tab' => $tab->fresh()];
            });
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $data = $result['payload']['data'] ?? ['order' => null, 'items' => [], 'tickets' => []];
        if (! empty($data['order']['id']) && ! empty($data['tickets'])) {
            // Ciclurile sunt credit pe tichetul de parcare, nu bilete: nu le tiparim pe termica.
            $cycleTicketIds = Ticket::where('order_id', $data['order']['id'])
                ->where('ticket_type_id', $quote['ticket_type_id'])->pluck('id')->map(fn ($id) => (int) $id)->all();
            $data['tickets'] = array_values(array_filter($data['tickets'], fn ($t) => ! in_array((int) ($t['id'] ?? 0), $cycleTicketIds, true)));
        }
        $data['sanius'] = $circuit->tabSummary($result['tab']);
        $data['sanius_prepay'] = [
            'simulated' => ! $live,
            'sleds' => $quote['sleds'],
            'cycles' => $quote['cycles'],
            'sled_cycles' => $quote['sled_cycles'],
            'day_type' => $quote['day_type'],
            'amount' => $quote['subtotal'],
        ];

        $what = $quote['sleds'].' '.($quote['sleds'] === 1 ? 'sanie' : 'sănii').' × '.$quote['cycles'].' '.($quote['cycles'] === 1 ? 'ciclu' : 'cicluri');

        return $this->success($data, $live
            ? 'Încasat. '.$what.' trecute pe tichetul de parcare.'
            : 'TEST: '.$what.' trecute pe tichet fără încasare'.($result['payload'] ? '; biletele din coș s-au vândut normal.' : '.'));
    }

    // ==================================================================
    // Contract (semnat pe tableta sau pe hartie)
    // ==================================================================

    /**
     * POST .../sanius/contracts { tab_id?|parking_code?, signer_name, doc_type, doc_series?, doc_number, phone?, address?, method: tablet|paper, station? }
     */
    public function contractsStore(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'tab_id' => 'nullable|integer',
            'parking_code' => 'nullable|string|max:40',
            'signer_name' => 'required|string|max:120',
            'doc_type' => 'required|in:'.implode(',', SaniusContract::DOC_TYPES),
            'doc_series' => 'nullable|string|max:12',
            'doc_number' => 'required|string|max:20',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'method' => 'required|in:tablet,paper',
            'station' => 'nullable|string|max:40',
        ]);
        $circuit = $this->circuit($ctx);
        $tab = null;
        if (! empty($v['tab_id'])) {
            $tab = $this->findTab($ctx, (int) $v['tab_id']);
        } elseif (! empty($v['parking_code'])) {
            $parsed = ParkingTicketCode::parse($v['parking_code']);
            if (! $parsed || ! ParkingTicketCode::isFromToday($parsed['entry_at'])) {
                return $this->error('Tichetul de parcare nu e valid sau nu e din ziua de azi.', 422);
            }
            $tab = $circuit->openTab($parsed, $this->memberOrNull($ctx));
        }
        if (! $tab || ! $tab->isOpen()) {
            return $this->error('Scanează întâi tichetul de parcare (contul trebuie să fie deschis).', 422);
        }

        $rules = $ctx['settings']->contractRules();
        $station = null;
        if ($v['method'] === 'tablet') {
            $station = in_array($v['station'] ?? null, $rules['stations'], true) ? $v['station'] : $rules['stations'][0];
        }
        $data = [
            'signer_name' => trim($v['signer_name']),
            'doc_type' => $v['doc_type'],
            'doc_series' => isset($v['doc_series']) ? strtoupper(trim($v['doc_series'])) : null,
            'doc_number' => strtoupper(trim($v['doc_number'])),
            'phone' => isset($v['phone']) ? trim($v['phone']) : null,
            'address' => isset($v['address']) ? trim($v['address']) : null,
        ];
        $body = $circuit->renderContractBody($tab, $data);

        $contract = DB::transaction(function () use ($ctx, $tab, $v, $station, $data, $rules, $body, $circuit) {
            // Un singur contract in asteptare pe cont: cel vechi nesemnat se anuleaza.
            SaniusContract::where('tab_id', $tab->id)->whereIn('status', ['awaiting_signature', 'awaiting_paper'])->update(['status' => 'void']);
            // Pe tableta sta un singur contract: ce astepta acolo (alt client) se anuleaza,
            // ca nimeni sa nu vada sau sa semneze datele altcuiva.
            if ($station !== null) {
                SaniusContract::where('event_id', $ctx['event']->id)->where('station', $station)
                    ->where('status', 'awaiting_signature')->update(['status' => 'void']);
            }

            return SaniusContract::create(array_merge($data, [
                'event_id' => $ctx['event']->id,
                'tab_id' => $tab->id,
                'status' => $v['method'] === 'paper' ? 'awaiting_paper' : 'awaiting_signature',
                'station' => $station,
                'title' => $rules['title'],
                'body' => $body,
                'template_version' => $rules['version'],
                'body_hash' => hash('sha256', $rules['title']."\n".$body),
                'created_by_member_id' => $this->memberOrNull($ctx),
                'is_test' => $circuit->isTest(),
            ]));
        });

        return $this->success(['contract' => $this->presentContractFull($contract)], $v['method'] === 'paper'
            ? 'Contract pregătit. Tipărește-l și dă-l clientului să-l semneze.'
            : 'Contract trimis la tableta „'.$station.'”.');
    }

    /** GET .../sanius/contracts/pending?station= — tableta de semnat intreaba daca are ceva de semnat. */
    public function contractsPending(Request $request, int $event): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate(['station' => 'nullable|string|max:40']);
        $rules = $ctx['settings']->contractRules();
        $station = $v['station'] ?? $rules['stations'][0];
        $c = SaniusContract::where('event_id', $ctx['event']->id)
            ->where('is_test', $ctx['settings']->isTestMode())
            ->where('status', 'awaiting_signature')
            ->where('station', $station)
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->orderByDesc('id')
            ->first();

        return $this->success([
            'station' => $station,
            'stations' => $rules['stations'],
            'is_test' => $ctx['settings']->isTestMode(),
            'contract' => $c ? $this->presentContractFull($c) : null,
        ]);
    }

    /** GET .../sanius/contracts/{contract} — casiera urmareste semnarea. */
    public function contractsShow(Request $request, int $event, int $contract): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $c = SaniusContract::where('id', $contract)->where('event_id', $ctx['event']->id)->first();
        if (! $c) {
            return $this->error('Contractul nu există.', 404);
        }

        return $this->success(['contract' => $this->presentContractFull($c)]);
    }

    /** POST .../sanius/contracts/{contract}/sign { signature: data:image/png;base64,..., accepted: true } */
    public function contractsSign(Request $request, int $event, int $contract): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $v = $request->validate([
            'signature' => 'required|string|max:700000',
            'accepted' => 'accepted',
        ]);
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $v['signature'], $m)) {
            return $this->error('Semnătura nu e o imagine validă.', 422);
        }
        $png = base64_decode($m[1], true);
        if ($png === false || strlen($png) < 200 || ! str_starts_with($png, "\x89PNG")) {
            return $this->error('Semnătura nu e o imagine validă.', 422);
        }

        $eventId = $ctx['event']->id;
        try {
            $c = DB::transaction(function () use ($contract, $eventId, $png, $request) {
                $c = SaniusContract::where('id', $contract)->where('event_id', $eventId)->lockForUpdate()->first();
                if (! $c) {
                    throw new SaniusException('Contractul nu există.');
                }
                if ($c->status !== 'awaiting_signature') {
                    throw new SaniusException($c->isSigned() ? 'Contractul e deja semnat.' : 'Contractul a fost anulat.');
                }
                $dir = 'sanius/'.$eventId.'/contracts';
                $sigPath = $dir.'/'.$c->id.'-semnatura.png';
                Storage::disk('local')->put($sigPath, $png);
                $c->forceFill([
                    'status' => 'signed',
                    'signature_path' => $sigPath,
                    'signed_at' => Carbon::now(),
                    'signed_ip' => $request->ip(),
                    'signed_device' => mb_substr((string) $request->userAgent(), 0, 160),
                ])->save();
                $pdfPath = $dir.'/'.$c->id.'.pdf';
                Storage::disk('local')->put($pdfPath, $this->contractPdf($c));
                $c->forceFill(['pdf_path' => $pdfPath])->save();

                return $c;
            });
        } catch (SaniusException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['contract' => $this->presentContractFull($c)], 'Contract semnat.');
    }

    /** POST .../sanius/contracts/{contract}/paper — clientul a semnat exemplarul tiparit. */
    public function contractsPaper(Request $request, int $event, int $contract): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $c = SaniusContract::where('id', $contract)->where('event_id', $ctx['event']->id)->first();
        if (! $c || ! in_array($c->status, ['awaiting_paper', 'awaiting_signature'], true)) {
            return $this->error('Contractul nu așteaptă semnătura.', 422);
        }
        $c->forceFill([
            'status' => 'signed_paper',
            'station' => null,
            'signed_at' => Carbon::now(),
            'signed_ip' => $request->ip(),
            'signed_device' => 'hartie',
        ])->save();

        return $this->success(['contract' => $this->presentContractFull($c)], 'Contract semnat pe hârtie. Păstrează exemplarul semnat.');
    }

    /** POST .../sanius/contracts/{contract}/void */
    public function contractsVoid(Request $request, int $event, int $contract): JsonResponse
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk']);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $c = SaniusContract::where('id', $contract)->where('event_id', $ctx['event']->id)->first();
        if (! $c || $c->status === 'void') {
            return $this->error('Contractul nu există.', 404);
        }
        if ($c->isSigned() && ! in_array($ctx['role'], ['owner', 'manager'], true)) {
            return $this->error('Un contract semnat îl poate anula doar managerul.', 403);
        }
        $c->forceFill(['status' => 'void'])->save();

        return $this->success(['contract' => $this->presentContractFull($c)], 'Contract anulat.');
    }

    /** GET .../sanius/contracts/{contract}/pdf — semnat (arhivat) sau de tiparit pentru semnatura pe hartie. */
    public function contractsPdf(Request $request, int $event, int $contract)
    {
        $ctx = $this->context($request, $event, ['cashier', 'desk'], false);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        $c = SaniusContract::where('id', $contract)->where('event_id', $ctx['event']->id)->first();
        if (! $c) {
            return $this->error('Contractul nu există.', 404);
        }
        if ($c->pdf_path && Storage::disk('local')->exists($c->pdf_path)) {
            $pdf = Storage::disk('local')->get($c->pdf_path);
        } else {
            $pdf = $this->contractPdf($c);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contract-sanius-'.$c->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function contractPdf(SaniusContract $c): string
    {
        $signature = null;
        if ($c->signature_path && Storage::disk('local')->exists($c->signature_path)) {
            $signature = 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($c->signature_path));
        }
        $c->loadMissing('tab');

        return Pdf::loadView('pdf.sanius-contract', [
            'contract' => $c,
            'signature' => $signature,
            'signedAt' => $c->signed_at?->copy()->setTimezone(ParkingTicketCode::TZ),
            'createdAt' => $c->created_at?->copy()->setTimezone(ParkingTicketCode::TZ),
        ])->setPaper('a4')->output();
    }

    private function presentContractFull(SaniusContract $c): array
    {
        $c->loadMissing('tab');

        return [
            'id' => $c->id,
            'tab_id' => $c->tab_id,
            'parking_number' => $c->tab?->parking_number ?: $c->tab?->parking_code,
            'status' => $c->status,
            'station' => $c->station,
            'signer_name' => $c->signer_name,
            'doc_type' => $c->doc_type,
            'doc_series' => $c->doc_series,
            'doc_number' => $c->doc_number,
            'phone' => $c->phone,
            'address' => $c->address,
            'title' => $c->title,
            'body' => $c->body,
            'template_version' => $c->template_version,
            'signed_at' => $c->signed_at?->toIso8601String(),
            'is_test' => $c->is_test,
        ];
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
        $prepaids = SaniusPrepaid::where('event_id', $eventId)->where('is_test', $isTest)->where('status', 'paid')
            ->whereBetween('created_at', [$from, $to])->get();
        $finished = $rentals->whereIn('status', ['closed', 'lost']);

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
                'prepaid_sled_cycles' => (int) $prepaids->sum('sled_cycles'),
                'prepaid_amount' => round((float) $prepaids->where('simulated', false)->sum('amount'), 2),
                'cycles_used' => (int) $finished->sum(fn ($r) => $r->cycles_used ?? 1),
                'weekend_rentals' => $rentals->where('day_type', 'weekend')->count(),
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
