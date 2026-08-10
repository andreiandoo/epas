<?php

namespace App\Support;

/**
 * Text simplu dintr-un câmp posibil traductibil.
 *
 * DE CE EXISTA
 * `Event.title`, `Venue.name` si altele sunt declarate `translatable`, deci
 * atributul intoarce un ARRAY de traduceri, nu un sir. Consecintele difera dupa
 * unde ajunge valoarea, si niciuna nu e buna:
 *   - intr-un `create()` -> PDO crapa cu „Array to string conversion";
 *   - intr-un payload JSON -> aplicatia primeste un obiect si afiseaza gol;
 *   - intr-o cerere catre un serviciu extern -> trimitem un obiect in loc de titlu.
 *
 * Acceptam si sirurile: `venues.name` e TEXT pe productie desi modelul il
 * declara traductibil (drift de schema cunoscut), deci valoarea poate veni in
 * ambele forme si nu ne putem baza pe una singura.
 *
 * Ordinea de preferinta e cea deja folosita in `Event::getNameAttribute()`:
 * ro, en, apoi prima traducere existenta.
 */
final class PlainText
{
    public static function of(mixed $value, ?string $fallback = null): ?string
    {
        if (is_array($value)) {
            /* Traducerile GOALE se sar, nu doar cele lipsa: `??` cade doar pe
               null, deci un titlu cu `ro` gol si `en` completat ar fi ramas
               fara text. (Aceeasi scapare exista si in
               Event::getNameAttribute() — merita aliniat cand se atinge.) */
            $usable = array_filter(
                $value,
                fn ($v) => is_string($v) && trim($v) !== '',
            );

            if ($usable === []) {
                return $fallback;
            }

            return $usable['ro'] ?? $usable['en'] ?? reset($usable);
        }

        if (is_string($value)) {
            return $value !== '' ? $value : $fallback;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $fallback;
    }
}
