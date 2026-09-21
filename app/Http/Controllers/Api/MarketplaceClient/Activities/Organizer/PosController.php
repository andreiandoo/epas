<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\Activities\LoadsCatalog;
use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Models\ActivityBooking;
use App\Models\ActivityCashSession;
use App\Models\ActivityLocation;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\Activities\ActivityCartException;
use App\Services\Activities\ActivityCommission;
use App\Services\Activities\ActivityOrderBuilder;
use App\Services\Activities\ActivityOrderEmail;
use App\Services\Activities\BookingDescriber;
use App\Services\Activities\OrganizerCatalog;
use App\Services\Activities\OrganizerCatalogPresenter;
use App\Services\Activities\ProductAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The operator's cash desk (POS) at one of its locations, activities module:
 * the products sold there with their POS prices, what is left on a date, the
 * cash session (open with the cash in the drawer, close with the cash counted)
 * and the sale itself: paid on the spot (cash or card), tickets issued at once,
 * the operator's commission applied as online, an e-mail only when asked.
 */
class PosController extends BaseController
{
    use ResolvesOrganizer;
    use LoadsCatalog;

    /** GET organizer/activities-module/pos/catalog?location_id= */
    public function catalog(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $location  = $this->deskLocation($organizer, $request->query('location_id'));
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }

        return $this->success([
            'location' => ['id' => $location->id, 'name' => OrganizerCatalogPresenter::ro($location->name), 'approved' => $location->review_status === ActivityLocation::REVIEW_APPROVED],
            'commission' => [
                'rate'  => (float) $organizer->getEffectiveCommissionRate(),
                'mode'  => $organizer->getEffectiveCommissionMode(),
                'floor' => ActivityCommission::floor($organizer),
            ],
            'products' => $this->deskProducts($organizer, $location)->map(fn ($p) => $this->deskProduct($p))->values(),
        ]);
    }

    /** GET organizer/activities-module/pos/day?location_id=&date= — what is left, as at the desk (no lead time). */
    public function day(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $location  = $this->deskLocation($organizer, $request->query('location_id'));
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        $date = $this->dateParam($request->query('date')) ?? CarbonImmutable::now(ProductAvailability::TIMEZONE)->startOfDay();
        $availability = (new ProductAvailability())->atDesk();

        $products = [];
        foreach ($this->deskProducts($organizer, $location) as $product) {
            $products[] = ['id' => $product->id] + $this->productDay($availability, $product, $date);
        }

        return $this->success([
            'date'     => $date->toDateString(),
            'hours'    => $location->hoursOn($date),
            'products' => $products,
        ]);
    }

    /** GET organizer/activities-module/pos/session?location_id= — the open session and what it sold. */
    public function session(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $location  = $this->deskLocation($organizer, $request->query('location_id'));
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        $session = $this->openSession($organizer, $location);

        return $this->success(['session' => $session ? $this->sessionRow($session) : null]);
    }

    /** POST organizer/activities-module/pos/session {location_id, opening_cash} */
    public function open(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $data = $request->validate([
            'location_id'  => 'required|integer',
            'opening_cash' => 'nullable|numeric|min:0|max:1000000',
        ]);
        $location = $this->deskLocation($organizer, $data['location_id']);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        if ($this->openSession($organizer, $location)) {
            return $this->error('Casa e deja deschisă la această locație.', 409);
        }
        $session = ActivityCashSession::create([
            'marketplace_client_id'    => $organizer->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'location_id'              => $location->id,
            'opened_by'                => $this->who($request, $organizer),
            'opened_at'                => now(),
            'opening_cash_cents'       => (int) round(((float) ($data['opening_cash'] ?? 0)) * 100),
        ]);

        return $this->success(['session' => $this->sessionRow($session)], 'Casa e deschisă.', 201);
    }

    /** POST organizer/activities-module/pos/session/{id}/close {counted_cash, notes} */
    public function close(Request $request, int $id): JsonResponse
    {
        $organizer = $this->organizer($request);
        $data = $request->validate([
            'counted_cash' => 'nullable|numeric|min:0|max:10000000',
            'notes'        => 'nullable|string|max:1000',
        ]);
        $session = ActivityCashSession::where('marketplace_organizer_id', $organizer->id)->whereKey($id)->first();
        if (!$session) {
            return $this->error('Sesiunea nu există', 404);
        }
        if (!$session->isOpen()) {
            return $this->error('Casa e deja închisă.', 409);
        }
        $session->update([
            'closed_by'          => $this->who($request, $organizer),
            'closed_at'          => now(),
            'counted_cash_cents' => isset($data['counted_cash']) ? (int) round(((float) $data['counted_cash']) * 100) : null,
            'notes'              => $data['notes'] ?? null,
        ]);

        return $this->success(['session' => $this->sessionRow($session->fresh())], 'Casa e închisă.');
    }

    /**
     * POST organizer/activities-module/pos/sale
     * {location_id, date?, payment_method: cash|card, items: [{activity_id, variant_id, quantity, slot_start_time?, addons?, components?, meta?}],
     *  customer?: {name, email, phone}, send_email?}
     */
    public function sale(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $client    = $this->requireClient($request);
        $data = $request->validate([
            'location_id'        => 'required|integer',
            'date'               => 'nullable|date_format:Y-m-d',
            'payment_method'     => 'required|in:cash,card',
            'items'              => 'required|array|min:1|max:40',
            'items.*.activity_id' => 'required|integer',
            'items.*.variant_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1|max:500',
            'items.*.slot_start_time' => 'nullable|string|max:8',
            'items.*.addons'     => 'nullable|array',
            'items.*.components' => 'nullable|array',
            'items.*.meta'       => 'nullable|array',
            'customer.name'      => 'nullable|string|max:160',
            'customer.email'     => 'nullable|email|max:190',
            'customer.phone'     => 'nullable|string|max:40',
            'customer.notes'     => 'nullable|string|max:500',
            // Buying as a company: the invoice is made out to these (ANAF fills them in on the desk screen).
            'company.name'           => 'nullable|string|max:200',
            'company.cui'            => 'nullable|string|max:30',
            'company.reg_no'         => 'nullable|string|max:60',
            'company.address'        => 'nullable|string|max:255',
            'company.iban'           => 'nullable|string|max:34',
            'company.contact_person' => 'nullable|string|max:120',
            'generate_invoice'   => 'nullable|boolean',
            'send_email'         => 'nullable|boolean',
        ]);

        // The invoice number is reserved at the sale and nowhere else, so company details without the tick would
        // leave an order nobody can invoice afterwards.
        $company = array_filter((array) ($data['company'] ?? []), fn ($v) => $v !== null && trim((string) $v) !== '');
        if ($company && empty($data['generate_invoice'])) {
            return $this->error('Ai completat datele firmei, dar nu ai bifat „Emite factură". Bifează sau șterge datele firmei.', 422);
        }
        $location = $this->deskLocation($organizer, $data['location_id']);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        $session = $this->openSession($organizer, $location);
        if (!$session) {
            return $this->error('Casa e închisă. Deschide casa înainte de prima vânzare.', 409);
        }

        $date  = $data['date'] ?? CarbonImmutable::now(ProductAvailability::TIMEZONE)->toDateString();
        $items = array_map(fn ($i) => $i + ['booking_date' => $date], $data['items']);
        $name  = trim((string) ($data['customer']['name'] ?? ''));
        $email = strtolower(trim((string) ($data['customer']['email'] ?? '')));

        try {
            DB::beginTransaction();

            $builder = app(ActivityOrderBuilder::class)->atDesk($organizer);
            $builder->lockProducts($items);
            $staged = $builder->stage($items, $client);

            $customer = null;
            if ($email !== '') {
                [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
                $customer = MarketplaceCustomer::firstOrCreate(
                    ['marketplace_client_id' => $client->id, 'email' => $email],
                    ['first_name' => $first ?: 'Client', 'last_name' => $last, 'phone' => $data['customer']['phone'] ?? null, 'status' => 'active']
                );
            }

            $total = round($staged['subtotal'] + $staged['commission_on_top'], 2);
            $order = Order::create([
                'marketplace_client_id'    => $client->id,
                'marketplace_organizer_id' => $organizer->id,
                'marketplace_customer_id'  => $customer?->id,
                'tenant_id'                => null,
                'event_id'                 => null,
                'marketplace_event_id'     => null,
                'order_number'             => 'POS-' . strtoupper(Str::random(8)),
                'status'                   => 'completed',
                'payment_status'           => 'paid',
                'payment_processor'        => 'pos',
                'payment_reference'        => 'pos-' . $data['payment_method'],
                'subtotal'                 => $staged['subtotal'],
                'discount_amount'          => 0,
                'commission_rate'          => 0,
                'commission_amount'        => $staged['commission'],
                'total'                    => $total,
                'currency'                 => $client->currency ?? 'RON',
                'source'                   => 'pos',
                'customer_email'           => $email !== '' ? $email : null,
                'customer_name'            => $name !== '' ? $name : 'Vânzare la casă',
                'customer_phone'           => $data['customer']['phone'] ?? null,
                'paid_at'                  => now(),
                'meta' => [
                    'order_type'              => 'activity',
                    'pos'                     => true,
                    'payment_method'          => $data['payment_method'],
                    'cash_session_id'         => $session->id,
                    'location_id'             => $location->id,
                    'cashier'                 => $this->who($request, $organizer),
                    'activity_ids'            => array_values(array_unique(array_map(fn ($l) => $l['activity']->id, $staged['lines']))),
                    'commission_details'      => array_map(fn ($l) => [
                        'activity'        => $l['title'],
                        'variant'         => $l['variant_name'],
                        'participants'    => $l['quantity'],
                        'unit_price'      => $l['unit_price'],
                        'addons_total'    => $l['addons_total'],
                        'line_total'      => $l['line_total'],
                        'commission'      => $l['commission'],
                        'commission_rate' => $l['commission_rate'],
                        'commission_mode' => $l['commission_mode'],
                        'organizer_id'    => $l['organizer_id'],
                    ], $staged['lines']),
                    'commission_added_on_top' => $staged['commission_on_top'],
                    'notes'                   => trim((string) ($data['customer']['notes'] ?? '')) ?: null,
                ] + ($company ? ['company_billing' => $company, 'invoice_requested' => true] : []),
            ]);

            $count = array_sum(array_map(fn ($l) => (int) $l['quantity'], $staged['lines']));
            $beneficiaries = $name !== '' ? array_fill(0, max(1, $count), ['name' => $name, 'email' => $email ?: null]) : [];
            $builder->persist($order, $staged, $customer, $beneficiaries, true, false, null);

            if ($company) {
                $this->reserveInvoice($order, $organizer, $staged['lines']);
            }

            DB::commit();
        } catch (ActivityCartException $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 409, ['code' => 'activity_unavailable']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('marketplace')->error('Activities POS sale failed', ['organizer_id' => $organizer->id, 'error' => $e->getMessage()]);
            return $this->error('Vânzarea nu s-a putut înregistra. Încearcă din nou.', 500);
        }

        if ($email !== '' && !empty($data['send_email'])) {
            try {
                app(ActivityOrderEmail::class)->send($order->fresh());
            } catch (\Throwable $e) {
                Log::channel('marketplace')->warning('Activities POS e-mail failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return $this->success(['sale' => $this->saleRow($order->fresh(), true)], 'Vânzarea a fost înregistrată.', 201);
    }

    /**
     * Take the next invoice number for a desk sale made out to a company. Which of the operator's companies issues it
     * follows the products sold: the second one only when everything sold belongs to it. A failure here never undoes
     * the sale — the invoice can still be issued by hand.
     */
    private function reserveInvoice(Order $order, MarketplaceOrganizer $organizer, array $lines): void
    {
        try {
            $issuers = array_map(fn ($l) => $l['activity']->issuing_company ?: 'primary', $lines);
            $onlySecondary = $issuers && !in_array('primary', $issuers, true) && $organizer->has_secondary_issuer;
            $issuer = $onlySecondary ? 'secondary' : 'primary';

            $meta = is_array($order->meta) ? $order->meta : [];
            $meta['invoice_number'] = $organizer->reserveNextInvoiceNumber($issuer);
            $meta['invoice_company'] = $issuer;
            $meta['invoice_issued_at'] = CarbonImmutable::now()->toIso8601String();
            $order->meta = $meta;
            $order->save();
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Activities POS invoice number failed', [
                'order_id' => $order->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** GET organizer/activities-module/pos/sales?session_id= | location_id=&date= */
    public function sales(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $query = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('source', 'pos')
            ->where('meta->order_type', 'activity');
        if ($sid = (int) $request->query('session_id')) {
            $query->where('meta->cash_session_id', $sid);
        } else {
            $location = $this->deskLocation($organizer, $request->query('location_id'));
            if (!$location) {
                return $this->error('Locația nu există', 404);
            }
            $date = $this->dateParam($request->query('date')) ?? CarbonImmutable::now(ProductAvailability::TIMEZONE)->startOfDay();
            $query->where('meta->location_id', $location->id)
                ->whereBetween('created_at', [$date->startOfDay()->utc(), $date->endOfDay()->utc()]);
        }
        $orders = $query->orderByDesc('id')->limit(200)->get();

        return $this->success(['sales' => $orders->map(fn ($o) => $this->saleRow($o, false))->values()]);
    }

    // ------------------------------------------------------------------

    private function deskLocation(MarketplaceOrganizer $organizer, $id): ?ActivityLocation
    {
        return (int) $id > 0 ? (new OrganizerCatalog($organizer))->ownLocation((int) $id) : null;
    }

    /** What the desk sells at a location: the operator's products there that passed review (online or POS-only). */
    private function deskProducts(MarketplaceOrganizer $organizer, ActivityLocation $location)
    {
        return Activity::where('marketplace_organizer_id', $organizer->id)
            ->where('location_id', $location->id)
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->with($this->productRelations())
            ->orderBy('id')
            ->get();
    }

    private function deskProduct(Activity $p): array
    {
        $price = fn ($v) => $v->pos_price_cents !== null ? (int) $v->pos_price_cents : (int) $v->price_cents;

        return [
            'id'                    => $p->id,
            'title'                 => OrganizerCatalogPresenter::ro($p->title),
            'type'                  => $p->product_type,
            'icon'                  => $p->icon,
            'booking_mode'          => $p->booking_mode,
            'display_category'      => $p->display_category,
            'access_requirement'    => $p->access_requirement ?: 'none',
            'requires_vehicle_info' => (bool) $p->requires_vehicle_info,
            'pos_only'              => (bool) $p->pos_only,
            'variants'              => $p->variants->where('is_active', true)->sortBy('sort_order')->map(fn ($v) => [
                'id'            => $v->id,
                'name'          => OrganizerCatalogPresenter::ro($v->name),
                'price_cents'   => $price($v),
                'online_cents'  => (int) $v->price_cents,
                'price_type'    => $v->price_type ?: 'per_person',
                'persons_max'   => $v->persons_max,
                'is_child'      => (bool) $v->is_child,
                'min_per_order' => max(1, (int) $v->min_per_order),
                'max_per_order' => (int) ($v->max_per_order ?: 0) ?: null,
                'step_qty'      => $v->step_qty ?: null,
                'duration_minutes' => $v->duration_minutes,
                'validity_days' => max(1, (int) ($v->validity_days ?: 1)),
                'pos_only'      => (bool) $v->pos_only,
            ])->values()->all(),
            'addons'                => $p->addons->where('is_active', true)->map(fn ($a) => [
                'id' => $a->id, 'name' => OrganizerCatalogPresenter::ro($a->name), 'price_cents' => (int) $a->price_cents,
                'included_qty' => (int) $a->included_qty, 'max_per_unit' => (int) $a->max_per_unit,
            ])->values()->all(),
            'components'            => $p->isPackage() ? $p->packageItems->map(fn ($i) => [
                'item_id' => $i->id, 'title' => OrganizerCatalogPresenter::ro($i->component?->title), 'booking_mode' => $i->component?->booking_mode,
                'variant' => OrganizerCatalogPresenter::ro($i->componentVariant?->name), 'quantity' => (int) $i->quantity,
            ])->values()->all() : [],
        ];
    }

    private function openSession(MarketplaceOrganizer $organizer, ActivityLocation $location): ?ActivityCashSession
    {
        return ActivityCashSession::where('marketplace_organizer_id', $organizer->id)
            ->where('location_id', $location->id)
            ->whereNull('closed_at')
            ->orderByDesc('id')
            ->first();
    }

    private function sessionRow(ActivityCashSession $s): array
    {
        $orders = Order::where('marketplace_organizer_id', $s->marketplace_organizer_id)
            ->where('source', 'pos')
            ->where('meta->cash_session_id', $s->id)
            ->get(['id', 'total', 'meta']);
        $cash = $orders->filter(fn ($o) => ($o->meta['payment_method'] ?? '') === 'cash')->sum('total');
        $card = $orders->filter(fn ($o) => ($o->meta['payment_method'] ?? '') === 'card')->sum('total');
        $expected = round($s->opening_cash_cents / 100 + $cash, 2);

        return [
            'id'            => $s->id,
            'location_id'   => $s->location_id,
            'open'          => $s->isOpen(),
            'opened_at'     => $s->opened_at?->toIso8601String(),
            'opened_by'     => $s->opened_by,
            'opening_cash'  => round($s->opening_cash_cents / 100, 2),
            'closed_at'     => $s->closed_at?->toIso8601String(),
            'closed_by'     => $s->closed_by,
            'counted_cash'  => $s->counted_cash_cents !== null ? round($s->counted_cash_cents / 100, 2) : null,
            'difference'    => $s->counted_cash_cents !== null ? round($s->counted_cash_cents / 100 - $expected, 2) : null,
            'notes'         => $s->notes,
            'sales'         => $orders->count(),
            'total_cash'    => round((float) $cash, 2),
            'total_card'    => round((float) $card, 2),
            'expected_cash' => $expected,
        ];
    }

    private function saleRow(Order $order, bool $withTickets): array
    {
        $row = [
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'created_at'     => $order->created_at?->toIso8601String(),
            'payment_method' => $order->meta['payment_method'] ?? null,
            'subtotal'       => (float) $order->subtotal,
            'commission'     => (float) ($order->meta['commission_added_on_top'] ?? 0),
            'total'          => (float) $order->total,
            'customer'       => ['name' => $order->customer_name, 'email' => $order->customer_email],
            // a sale made out to a company: the desk prints these on the receipt and shows the invoice number
            'company'        => is_array($order->meta['company_billing'] ?? null) ? $order->meta['company_billing'] : null,
            'invoice_number' => $order->meta['invoice_number'] ?? null,
            'notes'          => $order->meta['notes'] ?? null,
            'lines'          => array_map(fn ($l) => [
                'title' => $l['title'], 'variant' => $l['variant'], 'quantity' => $l['quantity'],
                'date_label' => $l['date_label'], 'time_label' => $l['time_label'], 'total' => $l['total'],
            ], BookingDescriber::lines($order)),
        ];
        if ($withTickets) {
            $tickets = Ticket::where('order_id', $order->id)
                ->with(['activityBooking.activity.location.city', 'activityBooking.variant', 'activityBooking.packageBooking.activity'])
                ->orderBy('id')
                ->get()
                ->reject(fn ($t) => is_array($t->meta ?? null) && !empty($t->meta['is_package_umbrella']));
            $row['tickets'] = $tickets->map(function (Ticket $t) {
                $info = BookingDescriber::ticket($t) ?? [];
                return [
                    'code'        => $t->code,
                    'barcode'     => $t->barcode ?? $t->code,
                    'title'       => $info['name'] ?? null,
                    'ticket_type' => $info['ticket_type'] ?? null,
                    'package'     => $info['package'] ?? null,
                    'date_label'  => $info['date_label'] ?? null,
                    'time_label'  => $info['time_label'] ?? null,
                    'venue'       => $info['venue'] ?? null,
                    'plate'       => $info['vehicle_plate'] ?? null,
                    'price'       => (float) ($t->price ?? 0),
                ];
            })->values()->all();
        }
        return $row;
    }

    /** Who is at the desk: the team member signed in, else the operator. */
    private function who(Request $request, MarketplaceOrganizer $organizer): string
    {
        $token = (string) ($request->user()?->currentAccessToken()?->name ?? '');
        if (str_starts_with($token, 'team-member-') && class_exists(\App\Models\MarketplaceOrganizerTeamMember::class)) {
            $member = \App\Models\MarketplaceOrganizerTeamMember::find((int) substr($token, 12));
            if ($member) {
                return (string) ($member->name ?? $member->email ?? 'Membru echipă');
            }
        }
        return (string) ($organizer->name ?: 'Operator');
    }
}

