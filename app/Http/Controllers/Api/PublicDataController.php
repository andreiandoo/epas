<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicDataController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'events' => Event::count(),
            'venues' => Venue::count(),
            'artists' => Artist::count(),
            'tenants' => Tenant::where('is_active', true)->count(),
        ]);
    }

    public function venues(Request $request): JsonResponse
    {
        $query = Venue::query();

        if ($request->has('active')) {
            $query->where('is_active', true);
        }

        $venues = $query->select([
            'id', 'name', 'slug', 'city', 'country', 'capacity',
            'address', 'latitude', 'longitude', 'created_at'
        ])->get();

        return response()->json($venues);
    }

    public function venue(string $slug): JsonResponse
    {
        $venue = Venue::where('slug', $slug)->firstOrFail();

        return response()->json($venue);
    }

    public function artists(Request $request): JsonResponse
    {
        $query = Artist::query();

        if ($request->has('active')) {
            $query->where('is_active', true);
        }

        $artists = $query->select([
            'id', 'name', 'slug', 'country', 'bio', 'created_at'
        ])->get();

        return response()->json($artists);
    }

    public function artist(string $slug): JsonResponse
    {
        $artist = Artist::where('slug', $slug)->firstOrFail();

        return response()->json($artist);
    }

    public function tenants(Request $request): JsonResponse
    {
        $query = Tenant::where('is_active', true);

        $tenants = $query->select([
            'id', 'name', 'public_name', 'slug', 'city', 'country', 'created_at'
        ])->get();

        return response()->json($tenants);
    }

    public function tenant(string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($tenant->only([
            'id', 'name', 'public_name', 'slug', 'city', 'country', 'created_at'
        ]));
    }

    public function events(Request $request): JsonResponse
    {
        $query = Event::query();

        if ($request->has('upcoming')) {
            $query->where('start_date', '>=', now());
        }

        $events = $query->select([
            'id', 'title', 'slug', 'start_date', 'end_date',
            'venue_id', 'tenant_id', 'created_at'
        ])->with(['venue:id,name,slug', 'tenant:id,name,public_name'])
          ->limit(100)
          ->get();

        return response()->json($events);
    }

    public function event(string $slug): JsonResponse
    {
        $event = Event::where('slug', $slug)
            ->with(['venue', 'artists', 'tenant:id,name,public_name'])
            ->firstOrFail();

        return response()->json($event);
    }
}
