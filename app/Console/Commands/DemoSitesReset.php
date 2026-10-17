<?php

namespace App\Console\Commands;

use App\Models\Coupon\CouponCode;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantBookingRequest;
use App\Models\TenantCustomerSubscription;
use App\Models\TenantNewsletter;
use App\Models\TenantNewsletterSubscriber;
use App\Models\TenantSiteDemo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Curățenia de noapte a demo-urilor de template (demo-*.tics.ro): șterge ce au
 * lăsat vizitatorii pe tenanții demo, ca fiecare prospect să găsească demo-ul
 * curat a doua zi:
 *   - comenzile plătite simulat (meta.payment = demo), prin model: locurile,
 *     stocul (quota_sold) și biletele se eliberează exact ca la o anulare;
 *   - abonamentele cumpărate și clienții creați de vizitatori (contul demo
 *     „demo@…” rămâne);
 *   - utilizările codurilor de reducere.
 *
 * Se atinge doar un tenant care e în tenant_site_demos ȘI are
 * settings.demo_checkout = true ȘI nu e în config tenant-sites.demo_reset_exclude
 * (implicit tenantul 60, WUKF). Comenzile mai noi de 30 de minute rămân
 * (cineva poate fi chiar atunci în plată).
 *
 *   php artisan demo:reset --dry-run
 */
class DemoSitesReset extends Command
{
    protected $signature = 'demo:reset
        {--dry-run : Doar arată ce s-ar șterge}
        {--tenant=* : Doar acești tenanți (tot cu garanțiile de mai sus)}';

    protected $description = 'Șterge comenzile, clienții și utilizările de cod lăsate de vizitatori pe tenanții demo';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $exclude = array_map('intval', (array) config('tenant-sites.demo_reset_exclude', [60]));
        $ids = TenantSiteDemo::query()->distinct()->pluck('tenant_id')->map(fn ($id) => (int) $id)->all();
        if ($only = array_map('intval', (array) $this->option('tenant'))) {
            $ids = array_values(array_intersect($ids, $only));
        }

        $tenants = Tenant::whereIn('id', $ids)->get()->filter(function (Tenant $t) use ($exclude) {
            if (in_array((int) $t->id, $exclude, true)) {
                $this->line("  #{$t->id} exclus (config tenant-sites.demo_reset_exclude)");
                return false;
            }
            if (empty(($t->settings ?? [])['demo_checkout'])) {
                $this->warn("  #{$t->id} nu are demo_checkout: sar peste (nu e tenant demo)");
                return false;
            }
            return true;
        });

        if ($tenants->isEmpty()) {
            $this->info('Niciun tenant demo de curățat.');
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes(30);
        foreach ($tenants as $tenant) {
            $this->info("Tenant #{$tenant->id} " . ($tenant->public_name ?: $tenant->name) . ($dry ? ' (simulare)' : ''));

            // 1. Comenzile plătite simulat, prin model (deleting eliberează locuri + stoc + bilete)
            $orders = Order::where('tenant_id', $tenant->id)
                ->where('meta->payment', 'demo')
                ->where('created_at', '<', $cutoff)
                ->get();
            $deleted = 0;
            foreach ($orders as $order) {
                if ($dry) {
                    $deleted++;
                    continue;
                }
                try {
                    $order->delete();
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->warn("  comanda #{$order->id}: " . $e->getMessage());
                }
            }
            $this->line("  comenzi demo: {$deleted}");

            // 2. Clienții creați de vizitatori (contul demo@ rămâne)
            $customers = Customer::where('tenant_id', $tenant->id)
                ->where('email', 'not like', 'demo@%')
                ->where('created_at', '<', $cutoff)
                ->get();
            $removed = 0;
            foreach ($customers as $customer) {
                // Un client care are încă o comandă reală (nu demo) nu se atinge
                if (Order::where('customer_id', $customer->id)->where(fn ($q) => $q->whereNull('meta->payment')->orWhere('meta->payment', '!=', 'demo'))->exists()) {
                    continue;
                }
                if ($dry) {
                    $removed++;
                    continue;
                }
                try {
                    if (Schema::hasTable('tenant_customer_subscriptions')) {
                        TenantCustomerSubscription::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->delete();
                    }
                    // Definitiv: un client șters soft ar bloca o comandă nouă cu același email (unic pe tenant)
                    $customer->forceDelete();
                    $removed++;
                } catch (\Throwable $e) {
                    $this->warn("  client #{$customer->id}: " . $e->getMessage());
                }
            }
            $this->line("  clienți creați de vizitatori: {$removed}");

            // 3. Abonații și campaniile de newsletter lăsate de vizitatori pe demo (adrese reale, nu le păstrăm)
            if (Schema::hasTable('tenant_newsletter_subscribers')) {
                $subs = TenantNewsletterSubscriber::where('tenant_id', $tenant->id)->where('created_at', '<', $cutoff);
                $n = $subs->count();
                if (! $dry) {
                    $subs->delete();
                    TenantNewsletter::where('tenant_id', $tenant->id)->where('created_at', '<', $cutoff)->delete();
                }
                $this->line("  abonați newsletter: {$n}");
            }

            // 4. Cererile de booking trimise de vizitatori (nume, email, telefon reale)
            if (Schema::hasTable('tenant_booking_requests')) {
                $br = TenantBookingRequest::where('tenant_id', $tenant->id)->where('created_at', '<', $cutoff);
                $n = $br->count();
                if (! $dry) {
                    $br->delete();
                }
                $this->line("  cereri de booking: {$n}");
            }

            // 5. Codurile de reducere: utilizările revin la zero
            if (Schema::hasTable('coupon_codes')) {
                $coupons = CouponCode::where('tenant_id', $tenant->id)->where('current_uses', '>', 0)->get();
                if (! $dry) {
                    foreach ($coupons as $coupon) {
                        $coupon->update(['current_uses' => 0] + ($coupon->status === 'exhausted' ? ['status' => 'active'] : []));
                    }
                }
                $this->line('  coduri resetate: ' . $coupons->count());
            }
        }

        return self::SUCCESS;
    }
}
