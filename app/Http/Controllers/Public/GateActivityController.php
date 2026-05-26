<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use Illuminate\Http\Request;

/**
 * Activity-flavoured gate scanner — sibling of GateController but talks
 * to the activity check-in endpoints. Mounted at /gate-activitati so
 * staff at the venue door for an activity (escape room / tour) gets a
 * focused UI without the event-selector noise.
 */
class GateActivityController extends Controller
{
    public function show(Request $request)
    {
        $client = MarketplaceClient::where('domain', $request->getHost())->first()
            ?? MarketplaceClient::first();

        return view('public.gate-scanner-activity', [
            'apiKey' => $client?->api_key ?? '',
            'apiBaseUrl' => '/api/marketplace-client',
            'marketplaceName' => $client?->name ?? 'Activities Gate',
        ]);
    }
}
