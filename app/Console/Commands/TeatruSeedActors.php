<?php

namespace App\Console\Commands;

use App\Models\TenantArtist;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seed demo de actori/regizori/scenografi pentru un tenant de teatru.
 *
 *   php artisan teatru:seed-actors {tenant=17}
 *
 * Idempotent: refolosește actorii după (tenant_id, slug).
 * Fotografiile sunt free stock (Unsplash), doar pentru demo.
 */
class TeatruSeedActors extends Command
{
    protected $signature = 'teatru:seed-actors {tenant=17 : ID tenant}';
    protected $description = 'Seed demo: ansamblu de actori/regizori/scenografi pentru un teatru';

    // [nume, rol, an naștere, foto, galerie[], bio scurtă]
    private array $actors = [
        ['Victor Rebengiuc', 'Actor', 1933, '1507003211169-0a1dd7228f2d', ['1516450360452-9312f5e86fc7', '1503095396549-807759245b35'], 'Unul dintre cei mai importanți actori ai scenei românești, cu o carieră de peste șase decenii.'],
        ['Maia Morgenstern', 'Actriță', 1962, '1494790108377-be9c29b29330', ['1460881680858-30d872d5b530', '1507924538820-ede94a04019d'], 'Actriță de teatru și film, prezență scenică remarcabilă în roluri clasice și contemporane.'],
        ['Marcel Iureș', 'Actor', 1951, '1472099645785-5658abf4ff4e', ['1519677100203-a0e668c92439'], 'Actor cu o vastă experiență în teatru și cinema, apreciat pentru rigoarea interpretării.'],
        ['Oana Pellea', 'Actriță', 1962, '1438761681033-6461ffad8d80', ['1470229722913-7c0e2dbbafd3'], 'Actriță de forță, cu roluri memorabile pe cele mai importante scene din țară.'],
        ['Ana Ularu', 'Actriță', 1985, '1544005313-94ddf0286df2', ['1516450360452-9312f5e86fc7'], 'Actriță din noua generație, activă în producții de teatru și film internaționale.'],
        ['Marius Manole', 'Actor', 1978, '1506794778202-cad84cf45f1d', ['1503095396549-807759245b35'], 'Unul dintre cei mai versatili actori ai generației sale, distins cu numeroase premii.'],
        ['Medeea Marinescu', 'Actriță', 1973, '1534528741775-53994a69daeb', ['1507924538820-ede94a04019d'], 'Actriță îndrăgită de public, cu o carieră bogată în teatru, film și televiziune.'],
        ['Alexandru Dabija', 'Regizor', 1955, '1560250097-0b93528c311a', [], 'Regizor de referință al teatrului românesc, semnatar al unor spectacole care au definit stagiuni întregi.'],
        ['Silviu Purcărete', 'Regizor', 1950, '1504257432389-52343af06ae3', [], 'Regizor cu recunoaștere internațională, cunoscut pentru viziunea sa vizuală amplă.'],
        ['Dragoș Buhagiar', 'Scenograf', 1966, '1507591064344-4c6ce005b128', [], 'Scenograf premiat, autor al unor decoruri și costume de excepție.'],
    ];

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant');
        $this->info("Seed actori pentru tenant #{$tenantId}");

        $img = fn (string $id, int $w = 600) => "https://images.unsplash.com/photo-{$id}?w={$w}&q=80&auto=format&fit=crop";

        $count = 0;
        foreach ($this->actors as [$name, $role, $year, $photo, $galleryIds, $bio]) {
            $slug = Str::slug($name);

            $gallery = array_map(fn ($id) => $img($id, 1000), $galleryIds);

            $artist = TenantArtist::updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                [
                    'name'        => $name,
                    'role'        => $role,
                    'birth_date'  => sprintf('%04d-01-01', $year),
                    'bio'         => ['ro' => '<p>' . $bio . '</p>', 'en' => '<p>' . $bio . '</p>'],
                    'photo_url'   => $img($photo, 800),
                    'gallery'     => $gallery,
                    'is_resident' => true,
                    'status'      => 'active',
                ]
            );

            $this->line("  actor: {$name} ({$role}) — #{$artist->id}");
            $count++;
        }

        $this->newLine();
        $this->info("Gata — {$count} actori. Verifică: https://teatru.tixello.ro/trupa");

        return self::SUCCESS;
    }
}
