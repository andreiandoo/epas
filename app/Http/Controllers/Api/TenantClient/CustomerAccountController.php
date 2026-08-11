<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Api\Concerns\ResolvesCustomer;
use App\Http\Controllers\Api\Concerns\ResolvesTenant;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\TenantCustomerBeneficiary;
use App\Models\TenantCustomerFavorite;
use App\Models\TenantCustomerPaymentMethod;
use App\Models\TenantCustomerSubscription;
use App\Models\TenantGiftCard;
use App\Models\TenantReview;
use App\Models\Ticket;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Contul clientului tenant (bilete, comenzi, statistici, profil) — echivalentul
 * endpoint-urilor /customer/* din marketplace, adaptate la scop tenant +
 * autentificare prin CustomerToken (bearer).
 */
class CustomerAccountController extends Controller
{
    use ResolvesTenant;
    use ResolvesCustomer;

    private array $paidStatuses = ['paid', 'confirmed', 'completed'];

    /** Rezolvă (tenant, customer) sau întoarce un răspuns de eroare. */
    private function ctx(Request $request): array|JsonResponse
    {
        $resolved = $this->resolveRequestTenantWithDomain($request);
        if (! $resolved) {
            return response()->json(['success' => false, 'error' => 'Tenant not found'], 404);
        }
        $customer = $this->resolveCustomer($request);
        if (! $customer) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }
        return [$resolved['tenant'], $customer];
    }

    /**
     * Comenzile clientului: potrivim după customer_id SAU customer_email, ca să
     * prindem și comenzile plasate cu același email dar sub alt rând de customer
     * (ex. checkout ca invitat cu email-ul contului).
     */
    private function ordersQuery($tenant, $customer)
    {
        return Order::where('tenant_id', $tenant->id)->where(function ($q) use ($customer) {
            $q->where('customer_id', $customer->id);
            if (! empty($customer->email)) {
                $q->orWhere('customer_email', $customer->email);
            }
        });
    }

    /** Rezumat pentru panoul principal. */
    public function stats(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $paidOrders = $this->ordersQuery($tenant, $customer)
            ->whereIn('status', $this->paidStatuses)->get(['id', 'total_cents']);

        $ticketsCount = Ticket::whereIn('order_id', $paidOrders->pluck('id'))->where('status', 'valid')->count();
        $subs = TenantCustomerSubscription::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->where('status', 'active')->count();

        $points = 0;
        try {
            $gami = app(GamificationService::class);
            if ($gami->isEnabled($tenant->id)) {
                $points = (int) ($gami->getCustomerPoints($tenant->id, $customer->id)->current_balance ?? 0);
            }
        } catch (\Throwable $e) {
        }

        return response()->json(['success' => true, 'data' => [
            'upcoming_tickets' => $ticketsCount,
            'active_subscriptions' => $subs,
            'favorites' => 0,
            'points' => $points,
            'total_spent' => $paidOrders->sum('total_cents') / 100,
            'orders_count' => $this->ordersQuery($tenant, $customer)->count(),
        ]]);
    }

    /** Comenzile clientului. */
    public function orders(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $orders = $this->ordersQuery($tenant, $customer)
            ->withCount('tickets')->latest()->get();

        $eventIds = $orders->pluck('meta.event_id')->filter()->unique()->all();
        $events = Event::whereIn('id', $eventIds)->get()->keyBy('id');

        $data = $orders->map(fn ($o) => $this->formatOrder($o, $events));

        $paid = $orders->whereIn('status', $this->paidStatuses);

        return response()->json([
            'success' => true,
            'data' => $data->values(),
            'stats' => [
                'total_orders' => $orders->count(),
                'total_spent'  => $paid->sum('total_cents') / 100,
                'saved'        => 0,
            ],
        ]);
    }

    /** Detaliul unei comenzi. */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $order = $this->ordersQuery($tenant, $customer)
            ->withCount('tickets')->find($orderId);
        if (! $order) {
            return response()->json(['success' => false, 'error' => 'Order not found'], 404);
        }

        $events = collect();
        if (! empty($order->meta['event_id'])) {
            $ev = Event::find($order->meta['event_id']);
            if ($ev) { $events = collect([$ev->id => $ev]); }
        }

        $tickets = $order->tickets()->with('ticketType')->get()->map(fn ($t) => [
            'code' => $t->code,
            'type' => $t->ticketType?->name,
            'seat_label' => $t->meta['seat_label'] ?? null,
            'status' => $t->status,
        ])->values();

        return response()->json(['success' => true, 'data' => array_merge(
            $this->formatOrder($order, $events),
            ['tickets' => $tickets]
        )]);
    }

    /** Biletele clientului (din comenzi + abonamente). */
    public function tickets(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $orderIds = $this->ordersQuery($tenant, $customer)
            ->whereIn('status', $this->paidStatuses)->pluck('id', 'id');
        $orders = Order::whereIn('id', $orderIds)->get()->keyBy('id');

        $tickets = Ticket::with('ticketType')
            ->where(function ($q) use ($orderIds, $customer) {
                $q->whereIn('order_id', $orderIds)
                    ->orWhere('meta->customer_id', $customer->id);
            })
            ->where('status', 'valid')
            ->get();

        // Evenimente (din meta comandă sau meta bilet)
        $eventIds = collect();
        foreach ($tickets as $t) {
            $eid = $t->meta['event_id'] ?? ($orders[$t->order_id]->meta['event_id'] ?? null);
            if ($eid) { $eventIds->push($eid); }
        }
        $events = Event::with('venue')->whereIn('id', $eventIds->unique()->all())->get()->keyBy('id');

        $today = now()->toDateString();
        $out = $tickets->map(function ($t) use ($orders, $events, $today) {
            $eid = $t->meta['event_id'] ?? ($orders[$t->order_id]->meta['event_id'] ?? null);
            $ev = $eid ? ($events[$eid] ?? null) : null;
            $date = $ev?->event_date?->toDateString();
            return [
                'code'       => $t->code,
                'type'       => $t->ticketType?->name,
                'seat_label' => $t->meta['seat_label'] ?? null,
                'event_id'   => $eid,
                'event'      => $ev?->getTranslation('title', 'ro'),
                'venue'      => $ev?->venue?->getTranslation('name', 'ro'),
                'date'       => $ev?->event_date?->toIso8601String(),
                'time'       => $ev?->start_time,
                'is_upcoming' => $date ? ($date >= $today) : true,
                'order_id'   => $t->order_id,
                'is_subscription' => ($t->meta['source'] ?? null) === 'subscription',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming' => $out->where('is_upcoming', true)->values(),
                'past'     => $out->where('is_upcoming', false)->values(),
            ],
        ]);
    }

    /** Actualizare profil (nume, telefon). */
    public function updateProfile(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:190',
            'last_name'  => 'nullable|string|max:190',
            'phone'      => 'nullable|string|max:50',
        ]);
        $customer->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'data' => [
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'avatar' => $this->avatarUrl($customer),
        ]]);
    }

    /**
     * Poza de profil.
     *
     * Se primeste FISIERUL, nu un URL: aplicatia are imaginea din camera sau din
     * galerie, iar un flux prin URL ar cere intai un serviciu de gazduire.
     *
     * Cea veche se sterge la inlocuire — altfel fiecare schimbare de poza ar
     * lasa un fisier orfan pe disc, la nesfarsit.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $request->validate([
            /* Limita si tipurile sunt impuse aici, nu doar in aplicatie: un
               client isi poate trimite orice, iar un fisier de 40 MB ar umple
               discul. `image` respinge si fisierele care doar se prefac ca sunt
               imagini (verifica antetul, nu extensia). */
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $old = $customer->avatar_path;

        $path = $request->file('avatar')->store('customers/avatars', 'public');

        $customer->update(['avatar_path' => $path]);

        if ($old && $old !== $path) {
            try {
                Storage::disk('public')->delete($old);
            } catch (\Throwable) {
                /* fisierul vechi nu se poate sterge (permisiuni, deja lipsa):
                   nu e un motiv sa esueze incarcarea celei noi */
            }
        }

        return response()->json(['success' => true, 'data' => ['avatar' => $this->avatarUrl($customer)]]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        if ($customer->avatar_path) {
            try {
                Storage::disk('public')->delete($customer->avatar_path);
            } catch (\Throwable) {
                /* vezi mai sus */
            }
            $customer->update(['avatar_path' => null]);
        }

        return response()->json(['success' => true, 'data' => ['avatar' => null]]);
    }

    private function avatarUrl($customer): ?string
    {
        return $customer->avatar_path ? Storage::disk('public')->url($customer->avatar_path) : null;
    }

    /** Schimbare parolă. */
    public function changePassword(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);
        if (! $customer->password || ! Hash::check($validated['current_password'], $customer->password)) {
            return response()->json(['success' => false, 'error' => 'Parola curentă este incorectă.'], 422);
        }
        $customer->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['success' => true]);
    }

    /** Ștergere cont (dezactivare — marchează + anonimizează la nevoie). */
    public function deleteAccount(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $validated = $request->validate(['password' => 'required|string']);
        if (! $customer->password || ! Hash::check($validated['password'], $customer->password)) {
            return response()->json(['success' => false, 'error' => 'Parolă incorectă.'], 422);
        }
        // Nu ștergem definitiv (comenzi/obligații) — marcăm cererea în meta.
        $customer->update(['meta' => array_merge($customer->meta ?? [], [
            'deletion_requested_at' => now()->toIso8601String(),
        ])]);

        return response()->json(['success' => true]);
    }

    // ==================== FAVORITE ====================

    public function favorites(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $favs = TenantCustomerFavorite::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->latest()->get();

        $map = fn ($f) => array_merge(['id' => $f->id, 'item_type' => $f->item_type, 'item_id' => $f->item_id], $f->meta ?? []);

        return response()->json(['success' => true, 'data' => [
            'events'  => $favs->where('item_type', 'event')->map($map)->values(),
            'artists' => $favs->where('item_type', 'artist')->map($map)->values(),
        ]]);
    }

    public function toggleFavorite(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $v = $request->validate([
            'item_type' => 'required|in:event,artist',
            'item_id'   => 'required|integer',
            'meta'      => 'nullable|array',
        ]);

        $existing = TenantCustomerFavorite::where('customer_id', $customer->id)
            ->where('item_type', $v['item_type'])->where('item_id', $v['item_id'])->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'favorited' => false]);
        }

        TenantCustomerFavorite::create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id,
            'item_type' => $v['item_type'], 'item_id' => $v['item_id'], 'meta' => $v['meta'] ?? null,
        ]);
        return response()->json(['success' => true, 'favorited' => true]);
    }

    // ==================== RECENZII ====================

    public function reviews(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $reviews = TenantReview::with('event')->where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->latest()->get();

        $published = $reviews->where('status', 'published');

        return response()->json(['success' => true, 'data' => $reviews->map(fn ($r) => [
            'id' => $r->id,
            'event' => $r->event?->getTranslation('title', 'ro'),
            'rating' => $r->rating,
            'title' => $r->title,
            'body' => $r->body,
            'status' => $r->status,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->values(), 'stats' => [
            'total' => $reviews->count(),
            'published' => $published->count(),
            'pending' => $reviews->where('status', 'pending')->count(),
            'avg' => $published->count() ? round($published->avg('rating'), 1) : 0,
        ]]);
    }

    /** Evenimente la care a participat, fără recenzie încă. */
    public function reviewsEligible(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $orderIds = $this->ordersQuery($tenant, $customer)
            ->whereIn('status', $this->paidStatuses)->pluck('id');
        $eventIds = collect();
        foreach (Order::whereIn('id', $orderIds)->get() as $o) {
            if (! empty($o->meta['event_id'])) { $eventIds->push($o->meta['event_id']); }
        }
        $reviewed = TenantReview::where('customer_id', $customer->id)->pluck('event_id')->filter()->all();
        $today = now()->toDateString();

        $events = Event::whereIn('id', $eventIds->unique()->all())
            ->whereNotIn('id', $reviewed)
            ->get()
            ->filter(fn ($e) => ! $e->event_date || $e->event_date->toDateString() <= $today)
            ->map(fn ($e) => ['id' => $e->id, 'title' => $e->getTranslation('title', 'ro'), 'date' => $e->event_date?->toDateString()])
            ->values();

        return response()->json(['success' => true, 'data' => $events]);
    }

    public function submitReview(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $v = $request->validate([
            'event_id'     => 'nullable|integer',
            'rating'       => 'required|integer|min:1|max:5',
            'title'        => 'nullable|string|max:190',
            'body'         => 'required|string|max:4000',
            'is_anonymous' => 'nullable|boolean',
            'recommend'    => 'nullable|boolean',
            'aspects'      => 'nullable|array',
        ]);

        $review = TenantReview::create(array_merge($v, [
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]));

        return response()->json(['success' => true, 'data' => ['id' => $review->id, 'status' => $review->status]]);
    }

    // ==================== NOTIFICĂRI (derivate din activitate) ====================

    /**
     * Feed de notificări construit din activitatea reală a clientului:
     * comenzi confirmate, puncte primite, abonamente cumpărate. Fără tabel dedicat —
     * starea „citit” este ținută în localStorage pe client.
     */
    public function notifications(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $items = collect();

        // Comenzi confirmate → confirmări de comandă
        $orders = $this->ordersQuery($tenant, $customer)
            ->whereIn('status', $this->paidStatuses)->withCount('tickets')->latest()->take(15)->get();
        $eventIds = $orders->pluck('meta.event_id')->filter()->unique()->all();
        $events = Event::whereIn('id', $eventIds)->get()->keyBy('id');
        foreach ($orders as $o) {
            $ev = ! empty($o->meta['event_id']) ? ($events[$o->meta['event_id']] ?? null) : null;
            $items->push([
                'type'  => 'tickets',
                'icon'  => 'ticket',
                'title' => 'Comanda ' . ($o->order_number ?: ('TC-' . $o->id)) . ' a fost confirmată',
                'body'  => ($o->tickets_count ?? 0) . ' bilet' . (($o->tickets_count ?? 0) === 1 ? '' : 'e')
                    . ($ev ? ' pentru „' . $ev->getTranslation('title', 'ro') . '”' : '') . ' sunt disponibile în cont.',
                'link'  => '/cont/comanda?id=' . $o->id,
                'link_label' => 'Vezi biletele',
                'at'    => $o->created_at?->toIso8601String(),
                'ts'    => $o->created_at?->timestamp ?? 0,
            ]);
        }

        // Puncte primite → beneficii
        try {
            $gami = app(GamificationService::class);
            if ($gami->isEnabled($tenant->id)) {
                $history = $gami->getPointsHistory($tenant->id, $customer->id, 15);
                foreach (($history['transactions'] ?? []) as $h) {
                    $pts = (int) ($h['points'] ?? 0);
                    if ($pts <= 0) { continue; }
                    $when = ! empty($h['created_at']) ? \Illuminate\Support\Carbon::parse($h['created_at']) : null;
                    $items->push([
                        'type'  => 'benefits',
                        'icon'  => 'star',
                        'title' => 'Ai primit ' . $pts . ' puncte',
                        'body'  => ($h['description'] ?? '') ?: 'Puncte de fidelitate adăugate în cont.',
                        'link'  => '/cont/puncte',
                        'link_label' => 'Vezi punctele',
                        'at'    => $when?->toIso8601String(),
                        'ts'    => $when?->timestamp ?? 0,
                    ]);
                }
            }
        } catch (\Throwable $e) {
        }

        // Abonamente active → program
        $subs = TenantCustomerSubscription::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->latest()->take(5)->get();
        foreach ($subs as $s) {
            $items->push([
                'type'  => 'program',
                'icon'  => 'calendar',
                'title' => 'Abonament ' . ($s->status === 'active' ? 'activat' : 'actualizat'),
                'body'  => 'Abonamentul tău este ' . ($s->status === 'active' ? 'activ și poate fi folosit la rezervări.' : 'înregistrat în cont.'),
                'link'  => '/cont/abonamente',
                'link_label' => 'Vezi abonamentul',
                'at'    => $s->created_at?->toIso8601String(),
                'ts'    => $s->created_at?->timestamp ?? 0,
            ]);
        }

        $data = $items->sortByDesc('ts')->take(30)->values()->map(function ($n) {
            unset($n['ts']);
            return $n;
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ==================== CONT DE FAMILIE (beneficiari) ====================

    public function beneficiaries(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $list = TenantCustomerBeneficiary::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->orderBy('id')->get()->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'relation' => $b->relation,
                'email' => $b->email,
                'birthdate' => $b->birthdate?->toDateString(),
                'status' => $b->status,
            ])->values();

        return response()->json(['success' => true, 'data' => $list, 'owner' => [
            'name' => $customer->full_name,
            'email' => $customer->email,
        ]]);
    }

    public function addBeneficiary(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $v = $request->validate([
            'name'      => 'required|string|max:190',
            'relation'  => 'nullable|in:adult,child',
            'email'     => 'nullable|email|max:190',
            'birthdate' => 'nullable|date',
        ]);

        $b = TenantCustomerBeneficiary::create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'name'        => $v['name'],
            'relation'    => $v['relation'] ?? 'adult',
            'email'       => $v['email'] ?? null,
            'birthdate'   => $v['birthdate'] ?? null,
            'status'      => ! empty($v['email']) ? 'invited' : 'active',
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $b->id]]);
    }

    public function removeBeneficiary(Request $request, int $id): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        TenantCustomerBeneficiary::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    // ==================== METODE DE PLATĂ SALVATE ====================

    public function paymentMethods(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $list = TenantCustomerPaymentMethod::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->orderByDesc('is_default')->orderByDesc('id')->get()->map(fn ($p) => [
                'id' => $p->id,
                'brand' => $p->brand,
                'last4' => $p->last4,
                'exp' => $p->exp_month ? sprintf('%02d/%02d', $p->exp_month, $p->exp_year % 100) : null,
                'holder' => $p->holder,
                'is_default' => $p->is_default,
            ])->values();

        return response()->json(['success' => true, 'data' => $list, 'billing' => [
            'name' => $customer->full_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ]]);
    }

    public function addPaymentMethod(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        // NU stocăm numărul complet — doar ultimele 4 cifre + brand detectat (tokenizare demo).
        $v = $request->validate([
            'number' => 'required|string|max:32',
            'holder' => 'nullable|string|max:190',
            'exp'    => 'nullable|string|max:7',
        ]);

        $digits = preg_replace('/\D/', '', $v['number']);
        if (strlen($digits) < 12) {
            return response()->json(['success' => false, 'error' => 'Numărul cardului este invalid.'], 422);
        }
        $brand = match ($digits[0]) {
            '4' => 'visa',
            '5' => 'mastercard',
            default => 'card',
        };
        [$mm, $yy] = array_pad(explode('/', str_replace(' ', '', $v['exp'] ?? '')), 2, null);

        $isFirst = ! TenantCustomerPaymentMethod::where('customer_id', $customer->id)->exists();

        $p = TenantCustomerPaymentMethod::create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'brand'       => $brand,
            'last4'       => substr($digits, -4),
            'exp_month'   => $mm !== null && $mm !== '' ? (int) $mm : null,
            'exp_year'    => $yy !== null && $yy !== '' ? 2000 + (int) $yy : null,
            'holder'      => $v['holder'] ?? null,
            'is_default'  => $isFirst,
            'token'       => 'demo_' . bin2hex(random_bytes(8)),
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $p->id]]);
    }

    public function setDefaultPaymentMethod(Request $request, int $id): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $pm = TenantCustomerPaymentMethod::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->find($id);
        if (! $pm) { return response()->json(['success' => false, 'error' => 'Cardul nu a fost găsit.'], 404); }

        TenantCustomerPaymentMethod::where('customer_id', $customer->id)->update(['is_default' => false]);
        $pm->update(['is_default' => true]);

        return response()->json(['success' => true]);
    }

    public function removePaymentMethod(Request $request, int $id): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $pm = TenantCustomerPaymentMethod::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->find($id);
        if ($pm) {
            $wasDefault = $pm->is_default;
            $pm->delete();
            if ($wasDefault) {
                $next = TenantCustomerPaymentMethod::where('customer_id', $customer->id)->orderByDesc('id')->first();
                $next?->update(['is_default' => true]);
            }
        }

        return response()->json(['success' => true]);
    }

    // ==================== CARDURI CADOU ====================

    public function giftCards(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $cards = TenantGiftCard::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)
            ->latest()->get();

        $map = fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'initial' => $c->initial_cents / 100,
            'balance' => $c->balance_cents / 100,
            'status' => $c->status,
            'recipient_name' => $c->recipient_name,
            'message' => $c->message,
        ];

        return response()->json(['success' => true, 'data' => $cards->map($map)->values(), 'stats' => [
            'count' => $cards->count(),
            'balance' => $cards->sum('balance_cents') / 100,
        ]]);
    }

    /**
     * Activează un cod de card cadou. Reclamă un card existent nealocat; în modul
     * demo, un cod nou valid ca format generează un card de 100 lei pentru client.
     */
    public function redeemGiftCard(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $v = $request->validate(['code' => 'required|string|max:64']);
        $code = strtoupper(trim($v['code']));

        $card = TenantGiftCard::where('tenant_id', $tenant->id)->where('code', $code)->first();

        if ($card) {
            if ($card->customer_id && $card->customer_id !== $customer->id) {
                return response()->json(['success' => false, 'error' => 'Acest card a fost deja activat.'], 422);
            }
            if (! $card->customer_id) {
                $card->update(['customer_id' => $customer->id]);
            }
            return response()->json(['success' => true, 'data' => ['balance' => $card->balance_cents / 100]]);
        }

        // Mod demo: acceptă orice cod în formatul TC-GIFT-XXXX și emite un card nou.
        if (! preg_match('/^TC-GIFT-[A-Z0-9]{3,}$/', $code)) {
            return response()->json(['success' => false, 'error' => 'Cod card cadou invalid.'], 422);
        }
        $card = TenantGiftCard::create([
            'tenant_id'     => $tenant->id,
            'customer_id'   => $customer->id,
            'code'          => $code,
            'initial_cents' => 10000,
            'balance_cents' => 10000,
            'status'        => 'active',
            'meta'          => ['source' => 'demo_redeem'],
        ]);

        return response()->json(['success' => true, 'data' => ['balance' => $card->balance_cents / 100]]);
    }

    /** Cumpărare card cadou (demo — fără procesator; emite cardul direct). */
    public function purchaseGiftCard(Request $request): JsonResponse
    {
        $ctx = $this->ctx($request);
        if ($ctx instanceof JsonResponse) { return $ctx; }
        [$tenant, $customer] = $ctx;

        $v = $request->validate([
            'amount'         => 'required|numeric|min:25|max:5000',
            'recipient_name' => 'nullable|string|max:190',
            'message'        => 'nullable|string|max:500',
        ]);

        do {
            $code = 'TC-GIFT-' . strtoupper(Str::random(6));
        } while (TenantGiftCard::where('code', $code)->exists());

        $cents = (int) round(((float) $v['amount']) * 100);
        $card = TenantGiftCard::create([
            'tenant_id'      => $tenant->id,
            'customer_id'    => $customer->id,
            'code'           => $code,
            'initial_cents'  => $cents,
            'balance_cents'  => $cents,
            'status'         => 'active',
            'recipient_name' => $v['recipient_name'] ?? null,
            'message'        => $v['message'] ?? null,
            'meta'           => ['source' => 'demo_purchase'],
        ]);

        return response()->json(['success' => true, 'data' => ['code' => $card->code, 'balance' => $cents / 100]]);
    }

    private function formatOrder(Order $order, $events): array
    {
        $meta = $order->meta ?? [];
        $ev = ! empty($meta['event_id']) ? ($events[$meta['event_id']] ?? null) : null;

        return [
            'id'            => $order->id,
            'order_number'  => $order->order_number ?: ('TC-' . $order->id),
            'status'        => $order->status,
            'is_paid'       => in_array($order->status, $this->paidStatuses),
            'created_at'    => $order->created_at?->toIso8601String(),
            'total'         => ($order->total_cents ?? 0) / 100,
            'currency'      => $order->currency ?? 'RON',
            'tickets_count' => $order->tickets_count ?? 0,
            'payment_method' => ($meta['payment_method'] ?? 'card') === 'card_cultural' ? 'Card cultural' : 'Card',
            'kind'          => $meta['kind'] ?? 'tickets',
            'event'         => $ev?->getTranslation('title', 'ro'),
            'event_date'    => $ev?->event_date?->toIso8601String(),
        ];
    }
}
