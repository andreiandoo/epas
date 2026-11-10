<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ambilet: articolul "ghid-creare-eveniment" din Centrul de ajutor cerea
 * cover de minim 1200x630. Formularul din contul organizatorului cere acum
 * macheta orizontala pe formatul coverului de eveniment Facebook
 * (config/marketplace_media.php). Se inlocuieste doar paragraful respectiv,
 * restul continutului (eventual editat din admin) ramane neatins.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clientId = DB::table('marketplace_clients')->where('domain', 'like', '%ambilet.ro%')->value('id') ?? 1;

        $row = DB::table('kb_articles')
            ->where('marketplace_client_id', $clientId)
            ->where('slug', 'ghid-creare-eveniment')
            ->first(['id', 'content']);
        if (!$row) {
            return;
        }

        $content = json_decode($row->content ?? '', true) ?: [];
        $ro = $content['ro'] ?? '';

        $new = '<p>Încarcă macheta orizontală în formatul coverului de eveniment Facebook: 1920×1005 px (raport 1,91:1), minim 1200×628 px. Dacă imaginea are alt raport, o poți decupa direct la încărcare. Macheta apare pe pagina evenimentului și în promovări. Afișul vertical e opțional, recomandat 800×1200 px. Formate acceptate: JPG, PNG sau WebP, maxim 10 MB.</p>';

        $updated = preg_replace('~<p>[^<]*1200\s*x\s*630[^<]*</p>~u', $new, $ro, 1, $count);
        if (!$count) {
            return;
        }

        $content['ro'] = $updated;
        DB::table('kb_articles')->where('id', $row->id)->update([
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Continut editorial, nu se revine
    }
};
