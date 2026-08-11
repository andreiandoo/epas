<?php

namespace App\Http\Controllers\Api\TixelloApp;

use App\Http\Controllers\Api\MarketplaceClient\OrdersController as MarketplaceOrdersController;
use App\Http\Controllers\Api\MarketplaceClient\PaymentController as MarketplacePaymentController;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\Order;
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
 * CINE VINDE:
 *  - eveniment de marketplace  -> marketplace-ul organizatorului, cu procesatorul
 *    şi comisionul lui;
 *  - eveniment de tenant       -> nu se poate vinde încă din aplicaţie (vezi mai
 *    jos), pentru că plata tenantului trece prin `TenantPaymentConfig`, iar
 *    fluxul de comandă de acolo e altul.
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

        $client = $this->sellerFor($event);

        if ($client instanceof JsonResponse) {
            return $client;
        }

        $this->impersonate($request, $client);

        return app(MarketplaceOrdersController::class)->create($request);
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

        /* Marketplace-ul se ia de pe COMANDĂ, nu din cerere: comanda ştie deja
           cine a vândut-o, iar dedus din nou ar putea diverge dacă evenimentul
           a fost mutat între timp. */
        $client = $model->marketplace_client_id ? MarketplaceClient::find($model->marketplace_client_id) : null;

        if (! $client) {
            return response()->json(['success' => false, 'message' => 'Comanda nu are un vânzător valid.'], 422);
        }

        $this->impersonate($request, $client);

        return app(MarketplacePaymentController::class)->initiate($request, $order);
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

    /**
     * Marketplace-ul care vinde evenimentul, sau un răspuns de eroare explicit.
     *
     * Evenimentele de tenant sunt refuzate ANUME, cu mesaj, nu lăsate să cadă
     * într-o eroare generică: fluxul lor de plată trece prin altă configuraţie
     * şi trebuie construit separat. Un „a apărut o eroare" ar fi ascuns faptul
     * că e o funcţie nefăcută, nu o defecţiune.
     */
    private function sellerFor(Event $event): MarketplaceClient|JsonResponse
    {
        if (! $event->marketplace_organizer_id) {
            return response()->json([
                'success' => false,
                'code' => 'tenant_checkout_unavailable',
                'message' => 'Biletele la acest eveniment nu se pot cumpăra încă din aplicație.',
            ], 422);
        }

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
