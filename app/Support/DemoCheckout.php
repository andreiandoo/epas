<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Unde e permis checkout-ul demo (plată simulată, comanda devine plătită fără
 * bani): doar pe site-urile de prezentare. Fără regula asta, oricine putea
 * cere /tenant-client/demo-checkout?tenant=ID și primea bilete valide gratuit
 * la evenimentele oricărui tenant.
 *
 * Permis când:
 *  - tenantul are settings.demo_checkout = true (comutatorul din Tenants → Site), sau
 *  - nu are setarea, dar are un domeniu activ pe un domeniu de demo
 *    (config tenant-sites.demo_checkout_domains, implicit tixello.ro:
 *    teatru.tixello.ro, competitie.tixello.ro, nordvale.tixello.ro).
 * settings.demo_checkout = false îl oprește oricum.
 */
class DemoCheckout
{
    public static function allowed(?Tenant $tenant): bool
    {
        if (! $tenant) {
            return false;
        }

        $flag = ($tenant->settings ?? [])['demo_checkout'] ?? null;
        if ($flag !== null) {
            return (bool) $flag;
        }

        $suffixes = (array) config('tenant-sites.demo_checkout_domains', []);
        foreach ($tenant->domains()->where('is_active', true)->pluck('domain') as $domain) {
            foreach ($suffixes as $suffix) {
                if ($domain === $suffix || str_ends_with($domain, '.' . $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function refusal(): array
    {
        return [
            'success' => false,
            'error' => 'Plata online nu este încă activă pe acest site.',
            'code' => 'demo_checkout_disabled',
        ];
    }
}
