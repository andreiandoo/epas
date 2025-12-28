<?php

namespace App\Providers;

use App\Models\Artist;
use App\Models\Venue;
use App\Models\User;
use App\Policies\ArtistPolicy;
use App\Policies\VenuePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Artist::class => ArtistPolicy::class,
        Venue::class => VenuePolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
