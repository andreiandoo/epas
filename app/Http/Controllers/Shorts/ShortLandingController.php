<?php

namespace App\Http\Controllers\Shorts;

use App\Http\Controllers\Controller;
use App\Models\Short;
use App\Services\Shorts\ShortDeepLink;
use App\Services\Shorts\ShortShareService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one web page a mobile-only feature needs (D1).
 *
 * A shared short lands here: OG tags so the preview looks like something in
 * WhatsApp/Instagram, a button into the app, store fallbacks, and click
 * attribution back to the share that produced it.
 */
class ShortLandingController extends Controller
{
    public function __construct(
        private readonly ShortShareService $shares,
        private readonly ShortDeepLink $deepLink,
    ) {}

    public function show(Request $request, int $short): View|Response
    {
        $model = Short::query()->published()->with(['owner', 'event'])->find($short);

        if (! $model) {
            return response()->view('shorts.landing-missing', [], 404);
        }

        $share = $this->shares->recordClick($request->query('s'));

        $referral = $request->query('ref') ?? $share?->referral_code;

        $response = response()->view('shorts.landing', [
            'short' => $model,
            'cardUrl' => $this->shares->cardUrl($model),
            'deepLink' => $this->deepLink->forShort($model->id),
            'referral' => $referral,
            'iosStoreUrl' => config('shorts.deep_link.ios_store_url'),
            'androidStoreUrl' => config('shorts.deep_link.android_store_url'),
        ]);

        // The referral cookie is what carries attribution through a signup that
        // happens minutes later, in the app, on a different surface.
        if ($referral !== null) {
            $response->cookie('tixello_ref', (string) $referral, 60 * 24 * 30);
        }

        return $response;
    }
}
