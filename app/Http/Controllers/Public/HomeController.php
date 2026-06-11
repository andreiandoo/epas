<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Artist;
use App\Models\Venue;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\Ticket; // if you track sold tickets here
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        // Cache KPIs for 5 minutes
        $stats = Cache::remember('public.kpis', 300, function () {
            return [
                'tickets'  => class_exists(Ticket::class)   ? (int) Ticket::query()->count()   : 0,
                'customers'=> class_exists(Customer::class) ? (int) Customer::query()->count() : 0,
                'tenants'  => class_exists(Tenant::class)   ? (int) Tenant::query()->count()   : 0,
                'venues'   => class_exists(Venue::class)    ? (int) Venue::query()->count()    : 0,
                'events'   => class_exists(Event::class)    ? (int) Event::query()->count()    : 0,
                'artists'  => class_exists(Artist::class)   ? (int) Artist::query()->count()   : 0,
            ];
        });

        // Latest public events across tenants (customize scope if needed)
        $latestEvents = Event::query()
            ->with(['tenant', 'venue'])
            ->orderByDesc('starts_at')
            ->limit(12)
            ->get();

        return view('public.home', compact('stats', 'latestEvents'));
    }
}
