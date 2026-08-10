<?php

namespace App\Console\Commands\Shorts;

use App\Jobs\Shorts\PullChannelShortsJob;
use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use App\Services\Shorts\ShortIngestService;
use Illuminate\Console\Command;

/**
 * Aduce rândurile deja scrise la regulile de acum.
 *
 * Trei reparaţii, toate pe conţinut produs de automat:
 *
 *  1. ETICHETELE butonului principal. Erau scrise la generare şi au rămas
 *     îngheţate greşit: o locaţie spunea „Vezi evenimentele", un artist „Ia
 *     bilet". Regula nouă: locaţie → „Vezi detalii", artist → „Vezi profil".
 *     Evenimentele nu apar aici — eticheta lor se compune la fiecare cerere,
 *     din cel mai mic preţ activ (ShortPayload::ctaLabel).
 *
 *  2. ADOPTAREA short-urilor luate din YouTube înainte ca preluarea să le
 *     marcheze `is_generated`. Fără marcaj, plafonul de mai jos nu ştie ce are
 *     voie să şteargă şi le-ar lăsa să se adune la nesfârşit.
 *
 *  3. PLAFONUL de {@see PullChannelShortsJob::MAX_PER_OWNER} short-uri aduse
 *     automat per artist, aplicat şi retroactiv.
 *
 * Implicit doar raportează; scrie doar cu `--force`.
 */
class RepairGeneratedShortsCommand extends Command
{
    protected $signature = 'shorts:repair-generated
        {--force : Chiar scrie (implicit doar raporteaza)}';

    protected $description = 'Aduce short-urile generate la regulile curente: etichete CTA, marcaj si plafon per artist';

    /** Ce ar trebui sa scrie pe buton, per tip de proprietar. */
    private const CTA = [
        Venue::class => ['open_venue', 'Vezi detalii'],
        Artist::class => ['open_artist', 'Vezi profil'],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->repairCtaLabels($force);
        $this->adoptPulledShorts($force);
        $this->applyPerOwnerCap($force);

        if (! $force) {
            $this->warn('Nimic scris. Ruleaza cu --force ca sa aplici.');
        }

        return self::SUCCESS;
    }

    private function repairCtaLabels(bool $force): void
    {
        foreach (self::CTA as $ownerType => [$type, $label]) {
            $query = Short::query()
                ->where('is_generated', true)
                ->where('owner_type', $ownerType)
                ->where(function ($q) use ($type, $label) {
                    $q->where('cta_type', '!=', $type)->orWhere('cta_label', '!=', $label);
                });

            $count = (clone $query)->count();
            $this->line(sprintf('CTA %s → "%s": %d de corectat', class_basename($ownerType), $label, $count));

            if ($force && $count > 0) {
                $query->update(['cta_type' => $type, 'cta_label' => $label]);
            }
        }

        /* Evenimentele isi pastreaza `buy_tickets`, dar eticheta inghetata
           („Ia bilet") nu mai e folosita decat ca rezerva cand nu se stie niciun
           pret — deci o aducem la un text care are sens si atunci. */
        $events = Short::query()
            ->where('is_generated', true)
            ->where('owner_type', Event::class)
            ->where('cta_label', 'Ia bilet');

        $eventCount = (clone $events)->count();
        $this->line(sprintf('CTA Event → rezerva "Vezi biletele": %d de corectat', $eventCount));

        if ($force && $eventCount > 0) {
            $events->update(['cta_label' => 'Vezi biletele']);
        }
    }

    private function adoptPulledShorts(bool $force): void
    {
        $query = Short::query()
            ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
            ->whereNotNull('source_video_id')
            ->where('owner_type', Artist::class)
            ->where(fn ($q) => $q->where('is_generated', false)->orWhereNull('is_generated'));

        $count = (clone $query)->count();
        $this->line(sprintf('Short-uri YouTube de marcat ca aduse automat: %d', $count));

        if ($force && $count > 0) {
            $query->update(['is_generated' => true]);
        }

        /* Cele aduse inainte ca preluarea sa seteze butonul au ramas cu
           `cta_type = 'none'`, deci apareau in feed fara nicio actiune. */
        $noCta = Short::query()
            ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
            ->where('owner_type', Artist::class)
            ->where(fn ($q) => $q->where('cta_type', 'none')->orWhereNull('cta_type'));

        $noCtaCount = (clone $noCta)->count();
        $this->line(sprintf('Short-uri YouTube fara buton de actiune: %d', $noCtaCount));

        if ($force && $noCtaCount > 0) {
            $noCta->update(['cta_type' => 'open_artist', 'cta_label' => 'Vezi profil']);
        }
    }

    private function applyPerOwnerCap(bool $force): void
    {
        $cap = PullChannelShortsJob::MAX_PER_OWNER;

        $owners = Short::query()
            ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
            ->where('owner_type', Artist::class)
            ->where('is_generated', true)
            ->selectRaw('owner_id, count(*) as total')
            ->groupBy('owner_id')
            ->havingRaw('count(*) > ?', [$cap])
            ->get();

        $total = 0;

        foreach ($owners as $row) {
            $keep = Short::query()
                ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
                ->where('owner_type', Artist::class)
                ->where('owner_id', $row->owner_id)
                ->where('is_generated', true)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit($cap)
                ->pluck('id');

            $stale = Short::query()
                ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
                ->where('owner_type', Artist::class)
                ->where('owner_id', $row->owner_id)
                ->where('is_generated', true)
                ->whereNotIn('id', $keep)
                ->get();

            $total += $stale->count();

            if ($force) {
                foreach ($stale as $short) {
                    $short->delete();
                }
            }
        }

        $this->line(sprintf(
            'Plafon %d/artist: %d artisti peste plafon, %d short-uri de sters',
            $cap,
            $owners->count(),
            $total,
        ));
    }
}
