<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $q = Venue::query()
            ->with(['tenant'])
            ->when($request->filled('country'), fn($x) => $x->where('country', $request->string('country')))
            ->when($request->filled('state'), fn($x)    => $x->where('state',   $request->string('state')))
            ->when($request->filled('city'), fn($x)     => $x->where('city',    $request->string('city')))
            ->when($request->filled('q'), fn($x)        => $x->where('name', 'ilike', '%'.$request->string('q').'%'))
            ->orderBy('name')
            ->paginate(36)
            ->withQueryString();

        return view('public.venues.index', ['venues' => $q]);
    }

    public function show(string $venue)
    {
        // Find venue by slug (or numeric ID for backward compatibility)
        $venueModel = Venue::query()
            ->when(is_numeric($venue), fn($q) => $q->where('id', $venue))
            ->when(!is_numeric($venue), fn($q) => $q->where('slug', $venue))
            ->with('tenant')
            ->firstOrFail();

        return view('public.venues.show', ['venue' => $venueModel]);
    }
}
