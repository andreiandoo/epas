<?php

namespace App\Observers;

use App\Models\MarketplaceHeroBanner;
use App\Services\Cache\AmbiletCacheBuster;
use Illuminate\Support\Facades\DB;

/**
 * Banner saved/removed → ambilet drops its cached hero list, so the change
 * shows on the homepage right away instead of after the 30 min TTL.
 */
class HeroBannerBustObserver
{
    public function __construct(protected AmbiletCacheBuster $buster) {}

    public function saved(MarketplaceHeroBanner $banner): void
    {
        DB::afterCommit(fn () => $this->buster->bustHomepageHero());
    }

    public function deleted(MarketplaceHeroBanner $banner): void
    {
        DB::afterCommit(fn () => $this->buster->bustHomepageHero());
    }
}
