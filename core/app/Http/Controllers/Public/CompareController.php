<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    /**
     * Slug pattern: "epas-iabilet", "epas-ambilet", etc.
     * URL: /compare/{country}/{slug}
     */
    public function show(string $country, string $slug)
    {
        // Basic parse (left side is always us)
        $parts = explode('-', $slug);
        $competitor = $parts[1] ?? null;

        // Map friendly competitor names to display/meta
        $map = [
            'iabilet'   => ['name' => 'iaBilet',     'url' => 'https://www.iabilet.ro/'],
            'ambilet'   => ['name' => 'amBilet',     'url' => 'https://www.ambilet.ro/'],
            'livetickets'=> ['name' => 'LiveTickets', 'url' => 'https://www.livetickets.ro/'],
            'bilete'    => ['name' => 'bilete.ro',   'url' => 'https://www.bilete.ro/'],
            'entertix'  => ['name' => 'Entertix',    'url' => 'https://www.entertix.ro/'],
            'iticket'   => ['name' => 'iTicket',     'url' => 'https://iticket.ro/'],
            'biletin'   => ['name' => 'Biletin',     'url' => 'https://biletin.ro/'],
            'myticket'  => ['name' => 'MyTicket',    'url' => 'https://www.myticket.ro/'],
            'eventim'   => ['name' => 'Eventim',     'url' => 'https://www.eventim.ro/ro/'],
            'evticket'  => ['name' => 'EVTicket',    'url' => 'https://www.evticket.ro/'],
            'oveit'     => ['name' => 'Oveit',       'url' => 'https://oveit.com/'],
            'blt'       => ['name' => 'BLT',         'url' => 'https://www.blt.ro/'],
            'eventbook' => ['name' => 'Eventbook',   'url' => 'https://eventbook.ro/'],
            'get-in'    => ['name' => 'Get-in',      'url' => 'https://get-in.ro/'],
        ];

        // If competitor not found, show helpful message
        if (!$competitor || !isset($map[$competitor])) {
            $availableCompetitors = implode(', ', array_keys($map));
            abort(404, "Invalid comparison slug '{$slug}'. Expected format: 'epas-{competitor}'. Available competitors: {$availableCompetitors}");
        }

        $data = [
            'country'    => strtolower($country),
            'ours'       => ['name' => 'ePas/EventPilot', 'url' => url('/')],
            'competitor' => $map[$competitor],
            // You can enrich with dynamic, measured KPIs later.
            'sections'   => [
                'Fees & Payouts'  => [
                    'We keep payouts transparent and fast.',
                    'No hidden per-ticket service fees for tenants.',
                ],
                'Feature set'     => [
                    'Tenant-aware multi-venue tours, riders, backline.',
                    'Embeddable storefront + single JS include.',
                    'SSO “Login as client” from Core.',
                ],
                'Support & Onboarding' => [
                    'Concierge onboarding for first 3 events.',
                    'Romanian & English support.',
                ],
            ],
        ];

        return view('public.compare.show', $data);
    }
}
