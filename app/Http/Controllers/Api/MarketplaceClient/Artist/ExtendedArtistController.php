<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Artist;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceArtistAccount;
use App\Models\MarketplaceArtistAccountMicroservice;
use App\Models\ServiceOrder;
use App\Models\ServiceType;
use App\Services\ExtendedArtist\ExtendedArtistAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint-uri pentru artist privind statusul Extended Artist:
 *  - GET  /status                     -> status complet (incl. modules, can_start_trial)
 *  - GET  /pricing                    -> ServiceType cu pricing pentru self-purchase
 *  - POST /start-trial                -> porneste trial 30 zile (o data per cont)
 *  - POST /subscribe                  -> creaza ServiceOrder pending_payment + URL plata
 *  - POST /cancel                     -> cancelare la finalul perioadei platite
 *
 * Toate endpoint-urile presupun auth:sanctum cu MarketplaceArtistAccount.
 */
class ExtendedArtistController extends BaseController
{
    public function __construct(private readonly ExtendedArtistAccess $access)
    {
    }

    public function status(Request $request): JsonResponse
    {
        $account = $this->requireArtist($request);
        return $this->success($this->access->statusFor($account));
    }

    public function pricing(Request $request): JsonResponse
    {
        $account = $this->requireArtist($request);

        if (!$this->access->isAvailableForMarketplace($account->marketplace_client_id)) {
            return $this->error('Extended Artist is not available on this marketplace.', 404);
        }

        $serviceType = ServiceType::getOrCreateExtendedArtistService($account->marketplace_client_id);
        $pricing = $serviceType->pricing ?? [];

        return $this->success([
            'service_type_id' => $serviceType->id,
            'code' => $serviceType->code,
            'name' => $serviceType->name,
            'audience' => $serviceType->audience,
            'monthly_price' => (float) ($pricing['monthly'] ?? 0),
            'currency' => $pricing['currency'] ?? 'RON',
            'trial_days' => (int) ($pricing['trial_days'] ?? $this->access->trialDays()),
        ]);
    }

    public function startTrial(Request $request): JsonResponse
    {
        $account = $this->requireArtist($request);

        if (!$this->access->canStartTrial($account)) {
            return $this->error('Trial is not available for this account.', 422, [
                'reason' => 'trial_unavailable',
            ]);
        }

        $microservice = $this->access->microservice();
        $trialDays = $this->access->trialDays();
        $now = now();

        $pivot = new MarketplaceArtistAccountMicroservice([
            'marketplace_artist_account_id' => $account->id,
            'microservice_id' => $microservice->id,
            'status' => MarketplaceArtistAccountMicroservice::STATUS_TRIAL,
            'granted_by' => MarketplaceArtistAccountMicroservice::GRANTED_TRIAL,
            'activated_at' => $now,
            'trial_ends_at' => $now->copy()->addDays($trialDays),
        ]);
        $pivot->save();

        return $this->success(
            $this->access->statusFor($account->refresh()),
            "Trial-ul de {$trialDays} zile a inceput. Acces complet la cele 4 module."
        );
    }

    public function subscribe(Request $request): JsonResponse
    {
        $account = $this->requireArtist($request);

        if (!$this->access->isAvailableForMarketplace($account->marketplace_client_id)) {
            return $this->error('Extended Artist is not available on this marketplace.', 404);
        }

        $microservice = $this->access->microservice();
        if (!$microservice) {
            return $this->error('Extended Artist microservice is not configured.', 500);
        }

        $serviceType = ServiceType::getOrCreateExtendedArtistService($account->marketplace_client_id);
        $monthly = (float) ($serviceType->pricing['monthly'] ?? 0);
        $currency = $serviceType->pricing['currency'] ?? 'RON';

        if ($monthly <= 0) {
            return $this->error('Pricing not configured.', 500);
        }

        $order = DB::transaction(function () use ($account, $microservice, $serviceType, $monthly, $currency) {
            return ServiceOrder::create([
                'marketplace_client_id' => $account->marketplace_client_id,
                'marketplace_artist_account_id' => $account->id,
                'microservice_id' => $microservice->id,
                'service_type' => ServiceOrder::TYPE_EXTENDED_ARTIST,
                'config' => [
                    'service_type_id' => $serviceType->id,
                    'billing_cycle' => 'monthly',
                ],
                'subtotal' => $monthly,
                'tax' => 0,
                'total' => $monthly,
                'currency' => $currency,
                'payment_method' => 'card',
                'payment_status' => ServiceOrder::PAYMENT_PENDING,
                'status' => ServiceOrder::STATUS_PENDING_PAYMENT,
            ]);
        });

        // TODO Faza 1.5: integreaza efectiv generarea URL Netopia pentru artist (azi
        // doar organizator are flux complet). Pana atunci returnam UUID-ul comenzii
        // si frontend-ul afiseaza un placeholder "Reactiveaza in scurt timp".
        return $this->success([
            'order_uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'payment_url' => null, // populat in Faza 1.5
        ], 'Comanda creata. Astepti integrarea fluxului de plata.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $account = $this->requireArtist($request);
        $row = $account->extendedArtistActivation();

        if (!$row || $row->granted_by !== MarketplaceArtistAccountMicroservice::GRANTED_SELF_PURCHASE) {
            return $this->error('Nu exista un abonament platit de cancelat.', 422);
        }

        if ($row->status === MarketplaceArtistAccountMicroservice::STATUS_CANCELLED) {
            return $this->success(
                $this->access->statusFor($account),
                'Abonamentul este deja cancelat.'
            );
        }

        $row->update([
            'status' => MarketplaceArtistAccountMicroservice::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $this->success(
            $this->access->statusFor($account->refresh()),
            'Abonament cancelat. Pastrezi acces pana la ' . $row->expires_at?->translatedFormat('d M Y') . '.'
        );
    }

    protected function requireArtist(Request $request): MarketplaceArtistAccount
    {
        $account = $request->user();
        if (!$account instanceof MarketplaceArtistAccount) {
            abort(401, 'Artist account authentication required');
        }
        return $account;
    }
}
