<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortShare;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Share-out with a branded card and the sharer's referral code baked into the
 * link (D1) — the growth loop.
 *
 * The landing URL is the only web surface this mobile-only feature needs: it
 * carries OG tags for the preview, hands the viewer into the app, and records
 * the click against the share that produced it.
 */
class ShortShareService
{
    public function __construct(private readonly ShortDeepLink $deepLink) {}

    /**
     * Record a share and return everything the client needs to hand to the OS
     * share sheet.
     *
     * @return array{share_id: int, url: string, card_url: string|null, deep_link: string, token: string}
     */
    public function share(Short $short, ?MarketplaceCustomer $sharer, ?string $channel = null): array
    {
        $share = ShortShare::create([
            'short_id' => $short->id,
            'sharer_customer_id' => $sharer?->id,
            'channel' => $channel,
            'referral_code' => $sharer ? $this->referralCodeFor($sharer) : null,
        ]);

        return [
            'share_id' => $share->id,
            'token' => $share->token,
            'url' => $this->landingUrl($short, $share),
            'card_url' => $this->cardUrl($short),
            'deep_link' => $this->deepLink->forShort($short->id),
        ];
    }

    /**
     * Public landing URL: `/s/{short}?s={token}&ref={code}`.
     */
    public function landingUrl(Short $short, ?ShortShare $share = null): string
    {
        $base = rtrim((string) config('shorts.deep_link.share_base_url', config('app.url')), '/');

        $query = array_filter([
            's' => $share?->token,
            'ref' => $share?->referral_code,
        ]);

        return $base.'/s/'.$short->id.($query ? '?'.http_build_query($query) : '');
    }

    public function cardUrl(Short $short): ?string
    {
        if ($short->share_card_path) {
            return Storage::disk('public')->url($short->share_card_path);
        }

        // No branded card yet — the poster is a usable OG image on its own.
        return $short->poster_url;
    }

    /**
     * Count a landing click against the share that produced it.
     */
    public function recordClick(?string $token): ?ShortShare
    {
        if (! $token) {
            return null;
        }

        $share = ShortShare::query()->where('token', $token)->first();

        if (! $share) {
            return null;
        }

        ShortShare::query()->whereKey($share->id)->increment('clicks');

        return $share;
    }

    /**
     * The sharer's marketplace referral code, so a signup through the link is
     * attributed by the existing referral flow rather than a parallel one.
     */
    protected function referralCodeFor(MarketplaceCustomer $customer): ?string
    {
        try {
            return DB::table('marketplace_referral_codes')
                ->where('marketplace_customer_id', $customer->id)
                ->where('is_active', true)
                ->value('code');
        } catch (\Throwable) {
            // Referral tables absent (scoped dev schema) — sharing still works,
            // it just carries no attribution.
            return null;
        }
    }
}
