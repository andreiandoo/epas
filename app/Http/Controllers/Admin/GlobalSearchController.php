<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $results = [];
        $locale = app()->getLocale();

        // Search Venues (by name - translatable)
        $venues = Venue::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($venues->isNotEmpty()) {
            $results['venues'] = $venues->map(function ($venue) use ($locale) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->getTranslation('name', $locale) ?? $venue->getTranslation('name', 'en') ?? 'Unnamed',
                    'subtitle' => $venue->city ?? '',
                    'url' => route('filament.admin.resources.venues.edit', ['record' => $venue]),
                ];
            })->toArray();
        }

        // Search Artists (by name - translatable)
        $artists = Artist::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($artists->isNotEmpty()) {
            $results['artists'] = $artists->map(function ($artist) use ($locale) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->getTranslation('name', $locale) ?? $artist->getTranslation('name', 'en') ?? 'Unnamed',
                    'subtitle' => '',
                    'url' => route('filament.admin.resources.artists.edit', ['record' => $artist]),
                ];
            })->toArray();
        }

        // Search Events (by title - translatable)
        $events = Event::query()
            ->where('title', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($events->isNotEmpty()) {
            $results['events'] = $events->map(function ($event) use ($locale) {
                return [
                    'id' => $event->id,
                    'name' => $event->getTranslation('title', $locale) ?? $event->getTranslation('title', 'en') ?? 'Unnamed',
                    'subtitle' => $event->start_date ? $event->start_date->format('Y-m-d') : '',
                    'url' => route('filament.admin.resources.events.edit', ['record' => $event]),
                ];
            })->toArray();
        }

        // Search Tenants (by name, public_name, or email)
        $tenants = Tenant::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('public_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($tenants->isNotEmpty()) {
            $results['tenants'] = $tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->public_name ?? $tenant->name ?? 'Unnamed',
                    'subtitle' => $tenant->email ?? '',
                    'url' => route('filament.admin.resources.tenants.edit', ['record' => $tenant]),
                ];
            })->toArray();
        }

        // Search Customers (by name or email)
        $customers = Customer::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($customers->isNotEmpty()) {
            $results['customers'] = $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name ?? $customer->email ?? 'Unnamed',
                    'subtitle' => $customer->email ?? '',
                    'url' => route('filament.admin.resources.customers.edit', ['record' => $customer]),
                ];
            })->toArray();
        }

        // Search Users (by name or email)
        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        if ($users->isNotEmpty()) {
            $results['users'] = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'subtitle' => $user->email,
                    'url' => route('filament.admin.resources.users.edit', ['record' => $user]),
                ];
            })->toArray();
        }

        return response()->json($results);
    }
}
