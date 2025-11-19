<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\Artist;
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

        // Search Venues (by name)
        $venues = Venue::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'city'])
            ->toArray();

        if (!empty($venues)) {
            $results['venues'] = $venues;
        }

        // Search Artists (by name)
        $artists = Artist::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->toArray();

        if (!empty($artists)) {
            $results['artists'] = $artists;
        }

        // Search Tenants (by name, public_name, or email)
        $tenants = Tenant::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('public_name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'public_name', 'email'])
            ->toArray();

        if (!empty($tenants)) {
            $results['tenants'] = $tenants;
        }

        // Search Customers (by name or email)
        $customers = Customer::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->toArray();

        if (!empty($customers)) {
            $results['customers'] = $customers;
        }

        // Search Users (by name or email)
        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->toArray();

        if (!empty($users)) {
            $results['users'] = $users;
        }

        return response()->json($results);
    }
}
