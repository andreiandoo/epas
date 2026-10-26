<?php

namespace App\Services\BuyerAccounts;

use App\Http\Controllers\Api\TenantClient\DemoStorefrontController;
use App\Mail\BuyerAccount\BuyerAccountLinkMail;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TixelloBuyerAccount;
use App\Models\TixelloBuyerConsent;
use App\Services\Onboarding\SignupMailer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Contul de cumpărător Tixello și acordurile lui. Tixello e aici operator separat:
 * nimic nu se creează fără bifa cumpărătorului, iar fiecare acord dat sau retras
 * rămâne în registru, cu textul exact.
 */
class TixelloBuyerAccountService
{
    public const PAID = ['paid', 'confirmed', 'completed'];

    /** Bifele de la plată pe site-ul unui organizator Tixello. */
    public function fromCheckout(Tenant $tenant, Order $order, array $customer, bool $wantsAccount, bool $wantsMarketing, ?string $ip, ?string $userAgent): void
    {
        if (! $wantsAccount && ! $wantsMarketing) {
            return;
        }

        $email = strtolower(trim((string) ($customer['email'] ?? '')));
        if ($email === '') {
            return;
        }

        $context = ['source' => 'checkout', 'tenant_id' => $tenant->id, 'order_id' => $order->id, 'ip' => $ip, 'user_agent' => $userAgent];
        $newlyEnabled = false;

        $account = DB::transaction(function () use ($email, $customer, $wantsAccount, $wantsMarketing, $context, &$newlyEnabled) {
            $account = TixelloBuyerAccount::firstOrNew(['email' => $email]);
            $account->first_name = $account->first_name ?: ($customer['first_name'] ?? null);
            $account->last_name = $account->last_name ?: ($customer['last_name'] ?? null);

            if ($wantsAccount && ! $account->account_enabled) {
                $account->fill(['account_enabled' => true, 'account_enabled_at' => now()]);
                $newlyEnabled = true;
            }
            $marketingNow = $wantsMarketing && ! $account->marketing_opt_in;
            if ($marketingNow) {
                $account->fill(['marketing_opt_in' => true, 'marketing_opt_in_at' => now(), 'marketing_opt_out_at' => null]);
            }
            $account->save();

            if ($newlyEnabled) {
                $this->record($account, 'account', 'granted', $context);
            }
            if ($marketingNow) {
                $this->record($account, 'marketing', 'granted', $context);
            }

            return $account;
        });

        // Emailul spune ce s-a bifat și dă linkul de intrare; „nu am cerut asta” oprește tot dintr-un click
        if ($newlyEnabled || $wantsMarketing) {
            $this->sendLink($account, $newlyEnabled ? 'welcome' : 'marketing');
        }
    }

    /** Cerere de link din /contul-meu. Răspunsul către vizitator e mereu același, ca să nu aflăm cine are cont. */
    public function requestLink(string $email): void
    {
        $email = strtolower(trim($email));
        $account = TixelloBuyerAccount::where('email', $email)->first();
        if ($account && $account->account_enabled) {
            $this->sendLink($account, 'login');

            return;
        }

        // Nu are încă cont: linkul îl creează, cu acordul dat pe pagina /contul-meu
        $this->sendLink($account ?? new TixelloBuyerAccount(['email' => $email]), 'create');
    }

    public function createFromPortal(string $email, ?string $ip, ?string $userAgent): TixelloBuyerAccount
    {
        return DB::transaction(function () use ($email, $ip, $userAgent) {
            $account = TixelloBuyerAccount::firstOrNew(['email' => strtolower($email)]);
            if (! $account->account_enabled) {
                $account->fill(['account_enabled' => true, 'account_enabled_at' => now()]);
                $account->save();
                $this->record($account, 'account', 'granted', ['source' => 'portal', 'ip' => $ip, 'user_agent' => $userAgent]);
            }
            $account->forceFill(['email_verified_at' => $account->email_verified_at ?? now(), 'last_login_at' => now()])->save();

            return $account;
        });
    }

    public function setMarketing(TixelloBuyerAccount $account, bool $on, string $source, ?string $ip, ?string $userAgent): void
    {
        if ($account->marketing_opt_in === $on) {
            return;
        }
        $account->fill($on
            ? ['marketing_opt_in' => true, 'marketing_opt_in_at' => now(), 'marketing_opt_out_at' => null]
            : ['marketing_opt_in' => false, 'marketing_opt_out_at' => now()])->save();
        $this->record($account, 'marketing', $on ? 'granted' : 'withdrawn', ['source' => $source, 'ip' => $ip, 'user_agent' => $userAgent]);
    }

    /** Ștergerea contului: registrul păstrează doar dovada retragerii acordurilor. */
    public function delete(TixelloBuyerAccount $account, ?string $ip, ?string $userAgent): void
    {
        DB::transaction(function () use ($account, $ip, $userAgent) {
            $context = ['source' => 'deletion', 'ip' => $ip, 'user_agent' => $userAgent];
            if ($account->account_enabled) {
                $this->record($account, 'account', 'withdrawn', $context);
            }
            if ($account->marketing_opt_in) {
                $this->record($account, 'marketing', 'withdrawn', $context);
            }
            $account->delete();
        });
    }

    /**
     * Comenzile plătite de pe site-urile organizatorilor Tixello, pe adresa contului.
     * Marketplace-urile (Ambilet, bilete.online) au conturile lor și nu intră aici.
     */
    public function orders(TixelloBuyerAccount $account): Collection
    {
        $orders = Order::query()
            ->whereRaw('lower(customer_email) = ?', [$account->email])
            ->whereNotNull('tenant_id')
            ->whereNull('marketplace_client_id')
            ->whereIn('status', self::PAID)
            ->with(['tenant:id,public_name,name', 'event'])
            ->withCount('tickets')
            ->latest('id')
            ->limit(200)
            ->get();

        $domains = Domain::whereIn('tenant_id', $orders->pluck('tenant_id')->unique())
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->get(['tenant_id', 'domain'])
            ->unique('tenant_id')
            ->pluck('domain', 'tenant_id');

        return $orders->map(function (Order $order) use ($domains) {
            $event = $order->event;
            $host = $domains[$order->tenant_id] ?? null;

            return [
                'id' => $order->id,
                'organizer' => $order->tenant?->public_name ?: $order->tenant?->name,
                'event' => $event ? ($event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en')) : null,
                'date' => $event?->event_date ?? $event?->range_start_date,
                'tickets' => (int) $order->tickets_count,
                'total' => $order->total_cents ? $order->total_cents / 100 : (float) $order->total,
                'currency' => $order->currency ?: 'RON',
                'bought_at' => $order->created_at,
                'pdf' => $host ? url('/api/tenant-client/storefront/tickets.pdf') . '?' . http_build_query([
                    'hostname' => $host, 'order' => $order->id, 'token' => DemoStorefrontController::orderToken($order),
                ]) : null,
                'site' => $host ? 'https://' . $host : null,
            ];
        });
    }

    private function record(TixelloBuyerAccount $account, string $type, string $action, array $context): void
    {
        $texts = config('legal.buyer_consents');
        TixelloBuyerConsent::create([
            'account_id' => $account->id,
            'email' => $account->email,
            'type' => $type,
            'action' => $action,
            'text_version' => $texts['version'] ?? null,
            'text' => $action === 'granted' ? ($texts[$type] ?? null) : null,
            'source' => $context['source'],
            'tenant_id' => $context['tenant_id'] ?? null,
            'order_id' => $context['order_id'] ?? null,
            'ip' => $context['ip'] ?? null,
            'user_agent' => isset($context['user_agent']) ? mb_substr((string) $context['user_agent'], 0, 255) : null,
        ]);
    }

    private function sendLink(TixelloBuyerAccount $account, string $kind): void
    {
        try {
            app(SignupMailer::class)->send($account->email, new BuyerAccountLinkMail($account, $kind), 'buyer_account_' . $kind);
        } catch (\Throwable $e) {
            Log::error('Cont cumpărător: emailul nu a plecat', ['email' => $account->email, 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }
}
