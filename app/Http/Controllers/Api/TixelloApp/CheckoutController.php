<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Api\MarketplaceClient\OrdersController as MarketplaceOrdersController;
use App\Http\Controllers\Api\MarketplaceClient\PaymentController as MarketplacePaymentController;
use App\Http\Controllers\Api\TenantClient\OrderController as TenantOrderController;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cumpărarea de bilete din aplicaţia Tixello.
 *
 * NU rescrie fluxul de vânzare. Crearea unei comenzi înseamnă rezervare de
 * stoc, blocare de locuri, comision, expirare, e-mailuri — câteva sute de linii
 * care rulează de mult în producţie pentru ambilet.ro şi bilete.online. O a doua
 * implementare ar fi ajuns inevitabil să difere de prima, iar diferenţele la
 * vânzarea de bilete se numesc locuri vândute de două ori.
 *
 * Aşadar controlerul ăsta face un singur lucru: află CINE vinde evenimentul şi
 * predă cererea, neschimbată, controllerelor care fac deja treaba.
 *
 * CINE VINDE, şi pe ce drum:
 *  - eveniment de MARKETPLACE -> `MarketplaceClient\OrdersController::create`
 *    + `PaymentController::initiate`, cu procesatorul configurat de marketplace;
 *  - eveniment de TENANT      -> `TenantClient\OrderController::store`
 *    + procesatorul din `TenantPaymentConfig`.
 *
 * Sunt două fluxuri diferite fiindcă aşa sunt în sistem, nu din alegerea
 * noastră: comanda de marketplace ştie de `marketplace_client_id`, cea de tenant
 * se rezolvă după domeniu. Ce le uneşte e ultimul pas — orice procesator
 * (Netopia, Stripe, EuPlatesc, PayU) răspunde la acelaşi `createPayment()` şi
 * întoarce o adresă de plată. De aceea aplicaţia nu ştie şi nu trebuie să ştie
 * cine procesează: ambilet.ro merge pe Netopia, alt vânzător pe Stripe, iar
 * ecranul e acelaşi.
 *
 * Autentificarea marketplace-ului (X-API-Key) se ocoleşte deliberat: aplicaţia
 * nu e un client de marketplace şi n-are cum să poarte cheia altcuiva. În loc de
 * cheie, marketplace-ul se DEDUCE din eveniment — adică din date, nu din ce
 * spune clientul — şi se pune pe cerere exact acolo de unde îl citeşte
 * `BaseController::getClient()`.
 */
class CheckoutController extends Controller
{
    /**
     * POST /api/app/checkout/order
     *
     * Aceleaşi câmpuri ca la marketplace: event_id, tickets[], customer{}.
     */
    public function createOrder(Request $request): JsonResponse
    {
        $event = Event::find($request->input('event_id'));

        if (! $event) {
            return response()->json(['success' => false, 'message' => 'Evenimentul nu există.'], 404);
        }

        if ($event->marketplace_organizer_id) {
            $client = $this->marketplaceFor($event);

            if ($client instanceof JsonResponse) {
                return $client;
            }

            $this->impersonate($request, $client);

            return app(MarketplaceOrdersController::class)->create($request);
        }

        return $this->createTenantOrder($request, $event);
    }

    /**
     * Comandă pentru un eveniment de tenant.
     *
     * Controllerul de tenant aşteaptă altă formă (`cart[]`, `customer_email`) şi
     * îşi rezolvă tenantul după DOMENIU, nu după id. Aplicaţia n-are un domeniu
     * al ei, aşa că i-l punem pe cel al tenantului evenimentului — traducere de
     * formă, nu logică de vânzare: stocul, preţurile şi biletele rămân treaba
     * controllerului.
     */
    private function createTenantOrder(Request $request, Event $event): JsonResponse
    {
        $tenant = $event->tenant;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'code' => 'seller_unavailable',
                'message' => 'Vânzătorul acestui eveniment nu este disponibil momentan.',
            ], 422);
        }

        $hostname = $this->tenantHostname($tenant);

        if (! $hostname) {
            /* Fără domeniu activ n-avem cum să trecem prin controllerul de
               tenant, iar o comandă „aproape creată" e mai rea decât una
               refuzată: ar bloca stoc fără să poată fi plătită. */
            return response()->json([
                'success' => false,
                'code' => 'seller_unavailable',
                'message' => 'Vânzătorul acestui eveniment nu este disponibil momentan.',
            ], 422);
        }

        $customer = (array) $request->input('customer', []);

        $payload = [
            'customer_email' => $customer['email'] ?? null,
            'customer_first_name' => $customer['first_name'] ?? null,
            'customer_last_name' => $customer['last_name'] ?? null,
            'customer_phone' => $customer['phone'] ?? null,
            'cart' => collect((array) $request->input('tickets', []))
                ->map(fn ($t) => [
                    'eventId' => $event->id,
                    'ticketTypeId' => (int) ($t['ticket_type_id'] ?? 0),
                    'quantity' => (int) ($t['quantity'] ?? 0),
                ])
                ->values()
                ->all(),
            /* Beneficiarii merg mai departe neschimbaţi: controllerul de tenant
               îi ştie deja, iar din ei se nasc invitaţiile de prietenie. */
            'beneficiaries' => $request->input('beneficiaries', []),
        ];

        $sub = Request::create(
            '/api/tenant-client/orders?hostname='.urlencode($hostname),
            'POST',
            $payload,
        );

        return app(TenantOrderController::class)->store($sub);
    }

    /**
     * POST /api/app/checkout/order/{order}/pay
     *
     * Întoarce `payment_url` — pagina procesatorului (Stripe Checkout, Netopia).
     * Aplicaţia o deschide în browserul sistemului; confirmarea vine pe webhook,
     * exact ca pe site.
     */
    public function pay(Request $request, int $order): JsonResponse
    {
        $model = Order::find($order);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Comanda nu există.'], 404);
        }

        /* Vânzătorul se ia de pe COMANDĂ, nu din cerere: comanda ştie deja cine
           a vândut-o, iar dedus din nou ar putea diverge dacă evenimentul a fost
           mutat între timp. */
        if ($model->marketplace_client_id) {
            $client = MarketplaceClient::find($model->marketplace_client_id);

            if (! $client) {
                return response()->json(['success' => false, 'message' => 'Comanda nu are un vânzător valid.'], 422);
            }

            $this->impersonate($request, $client);

            return app(MarketplacePaymentController::class)->initiate($request, $order);
        }

        return $this->payTenantOrder($model);
    }

    /**
     * Plata unei comenzi de tenant, cu procesatorul lui.
     *
     * Se cheamă acelaşi `createPayment()` ca la marketplace — e metoda comună a
     * tuturor procesatoarelor — deci Netopia, Stripe, EuPlătesc sau PayU merg pe
     * acelaşi drum şi întorc o adresă de plată.
     */
    private function payTenantOrder(Order $order): JsonResponse
    {
        $tenant = $order->event?->tenant;

        if (! $tenant) {
            return response()->json(['success' => false, 'message' => 'Comanda nu are un vânzător valid.'], 422);
        }

        $config = $tenant->activePaymentConfig();

        if (! $config) {
            return response()->json([
                'success' => false,
                'code' => 'processor_missing',
                'message' => 'Organizatorul nu are încă o metodă de plată configurată.',
            ], 422);
        }

        try {
            $processor = PaymentProcessorFactory::makeFromConfig($config);

            if (! $processor->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'code' => 'processor_missing',
                    'message' => 'Metoda de plată a organizatorului nu este completă.',
                ], 422);
            }

            $home = $this->tenantHostname($tenant);
            $base = $home ? 'https://'.$home : url('/');

            $payment = $processor->createPayment([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'currency' => $order->currency ?: 'RON',
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'description' => 'Bilete Tixello',
                'success_url' => $base.'/comanda-finalizata',
                'return_url' => $base.'/comanda-finalizata',
                'cancel_url' => $base.'/comanda-anulata',
                'metadata' => ['source' => 'tixello_app', 'tenant_id' => $tenant->id],
            ]);

            $order->update([
                'payment_reference' => $payment['reference'] ?? $payment['payment_id'] ?? null,
                'payment_processor' => $config->processor,
            ]);

            return response()->json(['success' => true, 'data' => [
                'payment_url' => $payment['redirect_url'] ?? $payment['payment_url'] ?? null,
                'processor' => $config->processor,
            ]]);
        } catch (\Throwable $e) {
            Log::error('Tixello app: plata comenzii de tenant a esuat', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Plata nu a putut fi pornită. Încearcă din nou.',
            ], 500);
        }
    }

    /** Domeniul activ al tenantului — cel principal, dacă există. */
    private function tenantHostname($tenant): ?string
    {
        $domains = $tenant->domains()->where('is_active', true)->get();

        return $domains->firstWhere('is_primary', true)?->domain ?? $domains->first()?->domain;
    }

    /**
     * GET /api/app/checkout/order/{order}
     *
     * Starea comenzii, pentru ecranul care aşteaptă întoarcerea de la plată.
     * Se întoarce puţin şi deliberat: aplicaţia are nevoie doar să ştie dacă
     * plata a trecut.
     */
    public function status(Request $request, int $order): JsonResponse
    {
        $model = Order::find($order);

        if (! $model) {
            return response()->json(['success' => false, 'message' => 'Comanda nu există.'], 404);
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $model->id,
            'reference' => $model->reference ?? null,
            'status' => $model->status,
            'total' => (float) ($model->total ?? 0),
            'paid' => in_array($model->status, ['paid', 'confirmed', 'completed'], true),
            'expires_at' => $model->expires_at?->toIso8601String(),
        ]]);
    }

    /* ================= ajutoare ================= */

    /** Marketplace-ul care vinde evenimentul, sau un răspuns de eroare explicit. */
    private function marketplaceFor(Event $event): MarketplaceClient|JsonResponse
    {
        $clientId = $event->marketplace_client_id
            ?? $event->marketplaceOrganizer?->marketplace_client_id;

        $client = $clientId ? MarketplaceClient::find($clientId) : null;

        if (! $client || ! $client->isActive()) {
            return response()->json([
                'success' => false,
                'code' => 'seller_unavailable',
                'message' => 'Vânzătorul acestui eveniment nu este disponibil momentan.',
            ], 422);
        }

        return $client;
    }

    /**
     * Pune marketplace-ul acolo de unde îl citesc controllerele de marketplace.
     *
     * `BaseController::getClient()` citeşte `$request->attributes`, aşa că e de
     * ajuns să-l aşezăm acolo — fără să atingem middleware-ul de autentificare
     * şi fără să inventăm o a doua cale de acces la acele controllere.
     */
    private function impersonate(Request $request, MarketplaceClient $client): void
    {
        $request->attributes->set('marketplace_client', $client);
    }
}
