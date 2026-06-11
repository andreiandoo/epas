<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;

class ArtistPublicController extends Controller
{
    public function index(Request $request)
    {
        $q      = (string) $request->input('q', '');
        $country= $request->input('country');
        $typeId = $request->input('type');
        $genreId= $request->input('genre');
        $letter = $request->input('letter');

        $artists = Artist::query()
            ->when($q, fn($qr) => $qr->where(function($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                  ->orWhere('city', 'ilike', "%{$q}%")
                  ->orWhere('country', 'ilike', "%{$q}%");
            }))
            ->when($country, fn($qr) => $qr->where('country', $country))
            ->when($typeId, fn($qr) => $qr->whereHas('artistTypes', fn($r) => $r->where('artist_types.id', $typeId)))
            ->when($genreId, fn($qr) => $qr->whereHas('artistGenres', fn($r) => $r->where('artist_genres.id', $genreId)))
            ->when($letter, fn($qr) => $qr->where('name', 'ilike', "{$letter}%"))
            ->with(['artistTypes:id,name','artistGenres:id,name'])
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return view('public.artists.index', [
            'artists' => $artists,
            'artistTypes' => \App\Models\ArtistType::orderBy('name')->get(),
            'artistGenres' => \App\Models\ArtistGenre::orderBy('name')->get(),
        ]);
    }

    public function show(string $slug)
    {
        // Find artist by slug (or numeric ID for backward compatibility)
        $artist = Artist::query()
            ->when(is_numeric($slug), fn($q) => $q->where('id', $slug))
            ->when(!is_numeric($slug), fn($q) => $q->where('slug', $slug))
            ->with([
                'artistTypes:id,name,slug',
                'artistGenres:id,name,slug',
                'events' => function($q) {
                    $q->where('starts_at', '>=', now())->orderBy('starts_at')->limit(10);
                }
            ])
            ->first();

        // If not found, show helpful error message
        if (!$artist) {
            $totalArtists = Artist::count();

            if ($totalArtists === 0) {
                abort(404, 'No artists found in database. Please add artists via admin panel first.');
            }

            // Show first 5 available slugs for debugging
            $samples = Artist::select('id', 'name', 'slug')->take(5)->get();
            $sampleList = $samples->map(fn($a) => "ID: {$a->id}, Name: {$a->name}, Slug: {$a->slug}")->implode(' | ');

            abort(404, "Artist with slug '{$slug}' not found. Total artists: {$totalArtists}. Examples: {$sampleList}");
        }

        return view('public.artists.show', ['artist' => $artist]);
    }
}
