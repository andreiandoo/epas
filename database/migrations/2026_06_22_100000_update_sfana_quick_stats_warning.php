<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-shot: rewrite the Quick Stats Bar bear warning for event 4234
 * (Lacul Sf. Ana) so the public page no longer says "ghid obligatoriu"
 * — that requirement was dropped. Adds HU + EN translations so the
 * organizer doesn't need to edit them through the panel.
 *
 * Idempotent: if quick_stats[0] already has the new value, the
 * migration is a no-op. Other events untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $event = DB::table('events')->where('id', 4234)->first(['id', 'venue_config']);
        if (!$event) {
            return; // Sf. Ana event not present on this environment (dev/staging) → skip.
        }

        $config = is_string($event->venue_config)
            ? (json_decode($event->venue_config, true) ?: [])
            : ((array) ($event->venue_config ?? []));

        $stats = $config['quick_stats'] ?? [];
        if (!is_array($stats) || empty($stats)) {
            $stats = [[]];
        }

        $stats[0] = array_merge($stats[0] ?? [], [
            'icon'  => '🐻',
            'label' => 'Atenție',
            'value' => 'Zonă cu urși · nu părăsiți căile de acces',
            'translations' => [
                'hu' => [
                    'label' => 'Figyelem',
                    'value' => 'Medvék a területen · ne hagyják el a kijelölt útvonalakat',
                ],
                'en' => [
                    'label' => 'Attention',
                    'value' => 'Bears in the area · stay on trails',
                ],
            ],
        ]);

        $config['quick_stats'] = $stats;

        DB::table('events')->where('id', 4234)->update([
            'venue_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        // No rollback — content fix, not schema change.
    }
};
