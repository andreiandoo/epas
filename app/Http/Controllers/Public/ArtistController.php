<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    public function index(Request $request)
    {
        $q = Artist::query()
            ->withCount('events') // if relation exists
            ->when($request->filled('genre'), fn($x) =>
                $x->whereHas('taxonomies', fn($t) => $t->where('type','music-genre')->where('slug', $request->string('genre')))
            )
            ->when($request->filled('q'), fn($x) =>
                $x->where('name', 'ilike', '%'.$request->string('q').'%')
            )
            ->orderBy('name')
            ->paginate(36)
            ->withQueryString();

        return view('public.artists.index', ['artists' => $q]);
    }
}
