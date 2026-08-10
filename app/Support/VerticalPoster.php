<?php

namespace App\Support;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Imaginea VERTICALĂ a unui proprietar de short — sau nimic.
 *
 * DE CE EXISTĂ
 * Feed-ul e un ecran de telefon în picioare (9:16). O imagine orizontală pusă
 * acolo apare fie cu benzi negre imense, fie tăiată prin mijloc: în ambele
 * cazuri arată ca o eroare, nu ca o postare. Generatorul cădea până acum pe
 * imaginea principală („hero", „main") când lipsea cea verticală, ceea ce a
 * umplut feed-ul cu cadre orizontale.
 *
 * Regula, cerută explicit: se folosește DOAR câmpul vertical al modelului, iar
 * dacă acesta lipsește nu se generează nimic. Un short lipsă e un gol în feed;
 * unul cu imagine orizontală e un defect vizibil pe toate telefoanele.
 *
 * Câmpul vertical, per model (etichetele din panourile de admin):
 *   Event  → poster_url        „Poster (vertical)"
 *   Artist → portrait_url      „Portrait (vert.)", cerut 900×1200+
 *   Venue  → meta.portrait     „Portret (mobile hero)" (doar în /marketplace)
 *
 * Verificarea nu se oprește la numele câmpului: panoul de marketplace nu impune
 * dimensiuni, deci în „Portret" poate ajunge un fișier lat. Când imaginea se
 * poate măsura local, o măsurăm și o refuzăm dacă e mai lată decât înaltă.
 */
final class VerticalPoster
{
    /** Câmpul vertical al fiecărui model. Nicio rezervă — asta e ideea. */
    public static function for(Model $owner): ?string
    {
        $candidate = match (true) {
            $owner instanceof Event => $owner->poster_url,
            $owner instanceof Artist => $owner->portrait_url,
            $owner instanceof Venue => $owner->meta['portrait'] ?? null,
            default => null,
        };

        if (! is_string($candidate) || trim($candidate) === '') {
            return null;
        }

        $candidate = trim($candidate);

        return self::isVertical($candidate) ? $candidate : null;
    }

    /**
     * Verticală înseamnă mai înaltă decât lată.
     *
     * Se măsoară doar ce e pe discul local. O imagine găzduită în altă parte
     * (import vechi cu URL absolut) e ACCEPTATĂ fără măsurare: o citire de
     * rețea per rând ar transforma o măturătoare peste mii de artiști într-o
     * plimbare de zeci de minute, iar cazul e rar. Câmpul rămâne singurul
     * filtru acolo.
     */
    public static function isVertical(string $path): bool
    {
        $file = self::localPath($path);

        if ($file === null) {
            return true;
        }

        $size = @getimagesize($file);

        // Fișier ilizibil sau format neacceptat: nu inventăm un verdict.
        if ($size === false || empty($size[0]) || empty($size[1])) {
            return true;
        }

        return $size[1] > $size[0];
    }

    /** Calea pe disc, dacă imaginea e a noastră; null pentru orice URL extern. */
    private static function localPath(string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            // Poate fi tot fișierul nostru, servit prin APP_URL.
            $base = rtrim(Storage::disk('public')->url(''), '/');

            if ($base !== '' && str_starts_with($path, $base)) {
                $path = ltrim(substr($path, strlen($base)), '/');
            } else {
                return null;
            }
        }

        $path = ltrim($path, '/');

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->path($path)
            : null;
    }
}
