<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceClient;
use Illuminate\Http\Request;

class GateController extends Controller
{
    public function show(Request $request)
    {
        $client = MarketplaceClient::where('domain', $request->getHost())->first()
            ?? MarketplaceClient::first();

        $apiBaseUrl = '/api/marketplace-client';

        return view('public.gate-scanner', [
            'apiKey' => $client?->api_key ?? '',
            'apiBaseUrl' => $apiBaseUrl,
            'marketplaceName' => $client?->name ?? 'Event Gate',
        ]);
    }
}
