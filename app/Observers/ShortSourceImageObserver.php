<?php

namespace App\Observers;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use App\Support\VerticalPoster;
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
    /**
     * Campurile urmarite, per tip de proprietar — DOAR cele verticale.
     *
     * Aceeasi regula ca la generare (App\Support\VerticalPoster): feed-ul e
     * 9:16, deci o imagine orizontala n-are ce cauta acolo. `meta` apare la sala
     * pentru ca portretul ei traieste in `meta.portrait`, nu intr-o coloana.
     */
    private const IMAGE_FIELDS = [
        Event::class => ['poster_url'],
        Artist::class => ['portrait_url'],
        Venue::class => ['meta'],
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

        $image = VerticalPoster::for($owner);

        if ($image === null) {
            /* Portretul a fost sters, sau inlocuit cu un fisier orizontal.
               Lasam posterul existent: un card gol in feed e mai rau decat unul
               cu imaginea anterioara, iar stergerea short-ului la o salvare
               gresita ar fi o pierdere tacuta de continut. */
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
