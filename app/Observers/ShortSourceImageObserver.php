<?php

namespace App\Observers;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Ține posterul unui short generat sincronizat cu imaginea sursei.
 *
 * DE CE UN OBSERVER, cand generarea se face printr-o maturatoare nocturna
 * Alegerea aceea e corecta pentru CREARE, si e explicata in routes/console.php:
 * un eveniment se creeaza cu mult inainte sa i se incarce posterul, deci un
 * observer ar porni pe un rand gol si n-ar mai reveni.
 * Pentru ACTUALIZARE, insa, situatia e pe dos: imaginea exista deja si tocmai
 * s-a schimbat. Maturatoarea nu ajuta cu nimic — ea sare peste orice proprietar
 * care are deja un short, deci posterul vechi ar ramane acolo pentru totdeauna.
 *
 * DOUA LIMITE, deliberate:
 *  - se ating DOAR short-urile generate automat (`is_generated`). Unul incarcat
 *    sau editat de om nu se rescrie niciodata pe la spate.
 *  - se ating doar cele FARA video. Daca exista un asset randat, posterul e
 *    cadrul lui, nu imaginea din catalog.
 */
class ShortSourceImageObserver
{
    /** Campul de imagine principal, per tip de proprietar. */
    private const IMAGE_FIELDS = [
        Event::class => ['poster_url', 'hero_image_url'],
        Artist::class => ['portrait_url', 'main_image_url', 'logo_url'],
        Venue::class => ['image_url'],
    ];

    public function updated(Model $owner): void
    {
        $fields = self::IMAGE_FIELDS[$owner::class] ?? null;
        if (! $fields) {
            return;
        }

        // Ne intereseaza doar cand chiar s-a schimbat o imagine
        if (! $owner->wasChanged($fields)) {
            return;
        }

        $image = null;
        foreach ($fields as $field) {
            $value = $owner->{$field};
            if (is_string($value) && trim($value) !== '') {
                $image = trim($value);
                break;
            }
        }

        if ($image === null) {
            // imaginile au fost sterse: lasam posterul existent, ca feed-ul sa
            // nu ramana cu un card gol
            return;
        }

        $updated = Short::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('is_generated', true)
            ->whereNull('hls_url')
            ->where(function ($q) use ($image) {
                $q->whereNull('poster_path')->orWhere('poster_path', '!=', $image);
            })
            ->get();

        foreach ($updated as $short) {
            /* `update()` si nu `forceFill`, ca ShortObserver sa vada schimbarea
               lui `poster_path` si sa recalculeze blurhash-ul. */
            $short->update(['poster_path' => $image, 'blurhash' => null]);
        }

        if ($updated->isNotEmpty()) {
            Log::info('ShortSourceImageObserver: poster resincronizat', [
                'owner' => $owner::class,
                'owner_id' => $owner->getKey(),
                'shorts' => $updated->pluck('id')->all(),
            ]);
        }
    }
}
