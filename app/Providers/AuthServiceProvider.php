<?php

namespace App\Providers;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Marketplace\MarketplacePayout;
use App\Models\Order;
use App\Models\Venue;
use App\Models\User;
use App\Policies\ArtistPolicy;
use App\Policies\Marketplace\OrganizerEventPolicy;
use App\Policies\Marketplace\OrganizerOrderPolicy;
use App\Policies\Marketplace\OrganizerPayoutPolicy;
use App\Policies\VenuePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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

        // Register organizer-specific policies for the organizer guard
        Gate::define('organizer-view-event', [OrganizerEventPolicy::class, 'view']);
        Gate::define('organizer-create-event', [OrganizerEventPolicy::class, 'create']);
        Gate::define('organizer-update-event', [OrganizerEventPolicy::class, 'update']);
        Gate::define('organizer-delete-event', [OrganizerEventPolicy::class, 'delete']);

        Gate::define('organizer-view-order', [OrganizerOrderPolicy::class, 'view']);

        Gate::define('organizer-view-payout', [OrganizerPayoutPolicy::class, 'view']);
    }
}
