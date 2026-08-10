<?php

namespace App\Console\Commands\Shorts;

use App\Models\Short;
use App\Support\VerticalPoster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Scoate din feed short-urile generate peste o imagine ORIZONTALĂ.
 *
 * Generatorul avea, până acum, o listă de rezerve: dacă lipsea imaginea
 * verticală, cădea pe cea principală. Aşa au apărut sute de short-uri cu cadre
 * late, tăiate prin mijloc pe un ecran 9:16. Regula s-a schimbat (vezi
 * App\Support\VerticalPoster), dar rândurile deja scrise rămân până le ştergem.
 *
 * Se atinge DOAR ce a produs automatul:
 *   - `is_generated` — un short încărcat sau editat de om nu se şterge niciodată
 *     pe la spate;
 *   - fără `hls_url` — dacă există un asset randat, posterul e cadrul lui, nu
 *     imaginea din catalog, iar clipul e muncă reală.
 *
 * Implicit doar raportează. Ştergerea cere `--force`: e o operaţie
 * ireversibilă peste conţinut public.
 */
class PruneNonVerticalShortsCommand extends Command
{
    protected $signature = 'shorts:prune-non-vertical
        {--force : Chiar sterge (implicit doar raporteaza)}
        {--limit=0 : Opreste-te dupa atatea randuri (0 = fara limita)}';

    protected $description = 'Sterge short-urile generate automat peste imagini orizontale';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $checked = 0;
        $bad = [];
        $unmeasurable = 0;

        Short::query()
            ->where('is_generated', true)
            ->whereNull('hls_url')
            ->whereNotNull('poster_path')
            ->orderBy('id')
            ->chunkById(200, function ($shorts) use (&$checked, &$bad, &$unmeasurable, $limit) {
                foreach ($shorts as $short) {
                    $checked++;

                    $verdict = $this->measure($short->poster_path);

                    if ($verdict === null) {
                        $unmeasurable++;

                        continue;
                    }

                    if (! $verdict) {
                        $bad[] = $short;
                    }

                    if ($limit > 0 && count($bad) >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        $this->info(sprintf('Verificate: %d · orizontale: %d · nemasurabile: %d', $checked, count($bad), $unmeasurable));

        if ($unmeasurable > 0) {
            /* Nemasurabil = fisier lipsa de pe disc sau imagine gazduita in alta
               parte. Nu ghicim si nu stergem pe banuiala. */
            $this->line('  (nemasurabile: fisier absent local sau URL extern — lasate pe loc)');
        }

        if ($bad === []) {
            return self::SUCCESS;
        }

        foreach (array_slice($bad, 0, 15) as $short) {
            $this->line(sprintf('  #%d  %s  %s', $short->id, $short->owner_type, $short->poster_path));
        }

        if (count($bad) > 15) {
            $this->line(sprintf('  ... si inca %d', count($bad) - 15));
        }

        if (! $force) {
            $this->warn('Nimic sters. Ruleaza cu --force ca sa stergi.');

            return self::SUCCESS;
        }

        foreach ($bad as $short) {
            $short->delete();
        }

        $this->info(sprintf('Sterse: %d', count($bad)));

        return self::SUCCESS;
    }

    /**
     * true = verticala, false = orizontala/patrata, null = nu se poate masura.
     *
     * Patratul intra la „orizontal" deliberat: intr-un cadru 9:16 arata la fel
     * de gresit ca o imagine lata.
     */
    private function measure(string $path): ?bool
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $path = ltrim($path, '/');

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $size = @getimagesize(Storage::disk('public')->path($path));

        if ($size === false || empty($size[0]) || empty($size[1])) {
            return null;
        }

        return $size[1] > $size[0];
    }
}
