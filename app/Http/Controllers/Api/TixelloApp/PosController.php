<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Api\TixelloApp\Concerns\ResolvesLinkedOrganizer;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Support\OrderSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vânzarea la ușă din aplicația Tixello.
 *
 * Comanda se scrie in lumea PARTENERULUI, cu `source = 'pos_app'`, ca sa intre
 * exact ca orice alta vanzare fizica in rapoartele si decontarile lui. Daca ar
 * scrie altceva, ar aparea drept vanzare online si comisionul ar iesi gresit —
 * vezi App\Support\OrderSource, unde e explicata inconsecventa gasita.
 *
 * TREI REGULI care fac diferenta intre un POS corect si unul care strica bani:
 *
 * 1. PRETUL SE CALCULEAZA PE SERVER. Aplicatia trimite doar ce tip de bilet si
 *    cate bucati. Un pret venit de la client poate fi modificat de oricine
 *    intercepteaza cererea.
 * 2. STOCUL SE VERIFICA SUB LOCK. Doua case care vand simultan ultimul bilet
 *    trebuie sa se excluda reciproc.
 * 3. IDEMPOTENTA. Casierul apasa din nou cand reteaua intarzie; fara un id de
 *    vanzare generat pe dispozitiv, omul plateste o data si primeste doua
 *    comenzi.
 */
class PosController extends Controller
{
    use ResolvesLinkedOrganizer;

    /** Cod de bilet, in formatul folosit de restul sistemului. */
    private function ticketCode(): string
    {
        return 'TIX-'.strtoupper(Str::random(10));
    }

    public function sale(Request $request): JsonResponse
    {
        $org = $this->organizerFor($request);
        if (! $org) {
            return $this->noOrganizer();
        }

        $v = $request->validate([
            // id generat pe dispozitiv: acelasi id = aceeasi vanzare
            'sale_id' => 'required|string|max:64',
            'event_id' => 'required|integer',
            'items' => 'required|array|min:1|max:50',
            'items.*.ticket_type_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1|max:50',
            'payment_method' => 'required|in:cash,card,nfc',
            'customer.email' => 'nullable|email',
            'customer.name' => 'nullable|string|max:120',
            'customer.phone' => 'nullable|string|max:40',
        ]);

        // scoping din legatura, nu din ce trimite aplicatia
        $event = Event::where('id', $v['event_id'])
            ->where('marketplace_organizer_id', $org->id)
            ->first();

        if (! $event) {
            return response()->json(['success' => false, 'error' => 'Eveniment inexistent.'], 404);
        }

        // idempotenta: aceeasi vanzare retrimisa nu creeaza a doua comanda
        $existing = Order::where('meta->pos_sale_id', $v['sale_id'])->first();
        if ($existing) {
            return $this->saleResponse($existing, $v['payment_method'], false);
        }

        try {
            $order = DB::transaction(function () use ($v, $org, $event) {
                $total = 0.0;
                $rows = [];

                foreach ($v['items'] as $item) {
                    /** Lock pe tipul de bilet: doua case nu pot vinde acelasi ultim bilet. */
                    $tt = TicketType::where('id', $item['ticket_type_id'])
                        ->where('event_id', $event->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $tt) {
                        throw new \RuntimeException('Tip de bilet inexistent pentru acest eveniment.');
                    }

                    $qty = (int) $item['qty'];

                    /* Stocul e `capacity` (+ `quota_sold` vandute). Cand nu e
                       setat, tipul se vinde liber — asa il trateaza si restul
                       sistemului. */
                    if (! is_null($tt->capacity)) {
                        $remaining = (int) $tt->capacity - (int) ($tt->quota_sold ?? 0);
                        if ($qty > $remaining) {
                            throw new \RuntimeException("Stoc insuficient pentru „{$tt->name}” ({$remaining} rămase).");
                        }
                    }
                    $tt->increment('quota_sold', $qty);

                    // PRETUL VINE DIN BAZA, niciodata de la aplicatie
                    $unit = (float) $tt->price;

                    for ($i = 0; $i < $qty; $i++) {
                        $total += $unit;
                        $rows[] = ['tt' => $tt, 'price' => $unit];
                    }
                }

                /**
                 * Clientul se potriveste dupa email in lumea partenerului si se
                 * REFOLOSESTE daca exista deja — exact ca la checkout-ul lor
                 * (`firstOrCreate` pe marketplace_client_id + email). Asa nu apar
                 * clienti dubli in CRM, chiar daca omul cumparase inainte direct
                 * de pe site-ul partenerului.
                 */
                $email = $v['customer']['email'] ?? null;
                $customer = null;
                if ($email) {
                    $customer = MarketplaceCustomer::firstOrCreate(
                        ['marketplace_client_id' => $org->marketplace_client_id, 'email' => mb_strtolower(trim($email))],
                        [
                            'first_name' => $v['customer']['name'] ?? null,
                            'phone' => $v['customer']['phone'] ?? null,
                            'status' => 'active',
                        ]
                    );
                }

                $order = Order::create([
                    'order_number' => 'POS-'.strtoupper(Str::random(8)),
                    'marketplace_client_id' => $org->marketplace_client_id,
                    'marketplace_customer_id' => $customer?->id,
                    'event_id' => $event->id,
                    'total' => $total,
                    'currency' => 'RON',
                    'status' => 'paid',                         // banii au fost incasati la usa
                    'source' => OrderSource::TIXELLO_APP_POS,   // 'pos_app'
                    'paid_at' => now(),
                    'meta' => [
                        'event_id' => $event->id,
                        'pos_sale_id' => $v['sale_id'],
                        'payment' => 'pos',
                        'payment_method' => $v['payment_method'],
                        'sold_via' => 'tixello_app',
                        'marketplace_organizer_id' => $org->id,
                        'customer_name' => $v['customer']['name'] ?? null,
                        'customer_phone' => $v['customer']['phone'] ?? null,
                    ],
                ]);

                foreach ($rows as $row) {
                    Ticket::create([
                        'order_id' => $order->id,
                        'ticket_type_id' => $row['tt']->id,
                        'event_id' => $event->id,
                        'marketplace_client_id' => $org->marketplace_client_id,
                        'marketplace_customer_id' => $customer?->id,
                        'code' => $this->ticketCode(),
                        'status' => 'valid',   // platit la usa, valabil imediat
                        'price' => $row['price'],
                        'meta' => ['event_id' => $event->id, 'pos' => true, 'sold_via' => 'tixello_app'],
                    ]);
                }

                return $order;
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return $this->saleResponse($order->fresh(['tickets']), $v['payment_method'], true);
    }

    private function saleResponse(Order $order, string $paymentMethod, bool $created): JsonResponse
    {
        $order->loadMissing('tickets');

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (float) $order->total,
                'currency' => $order->currency ?? 'RON',
                'payment_method' => $paymentMethod,
                'tickets' => $order->tickets->map(fn ($t) => [
                    'code' => $t->code,
                    'price' => (float) $t->price,
                ])->values(),
            ],
        ], $created ? 201 : 200);
    }
}
