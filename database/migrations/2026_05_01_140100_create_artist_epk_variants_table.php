<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('artist_epk_variants')) {
            return;
        }

        Schema::create('artist_epk_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_epk_id')
                ->constrained('artist_epks')
                ->cascadeOnDelete();

            // Display name pentru artist ("Default", "EPK Festival", "EPK Club")
            $table->string('name', 100);

            // Audience target opțional ("Universal", "Festival-uri", "Cluburi & bars")
            $table->string('target', 100)->nullable();

            // URL-safe slug — apare in URL: /epk/{artist_slug}/{variant_slug}
            // Auto-slugified din name la prima salvare.
            $table->string('slug', 100);

            // Branding
            $table->string('accent_color', 9)->default('#A51C30');
            $table->string('template', 50)->default('modern');

            // Configul celor 12 secțiuni — { id, enabled, data{...} } pentru fiecare.
            // Schema in JSON (nu coloane separate) pentru flexibilitate la adăugare/eliminare câmpuri.
            $table->json('sections')->nullable();

            // Denormalizate pentru afișare in editor (Versions tab) — actualizate de Faza B.
            // In Faza A rămân la 0.
            $table->unsignedInteger('views_count')->default(0);
            $table->decimal('conversion_pct', 5, 2)->default(0);

            $table->timestamps();

            $table->unique(['artist_epk_id', 'slug'], 'aev_epk_slug_unique');
        });

        // Adaugă FK pe artist_epks.active_variant_id acum că tabela există.
        if (Schema::hasTable('artist_epks') && !$this->fkExists('artist_epks', 'artist_epks_active_variant_id_foreign')) {
            Schema::table('artist_epks', function (Blueprint $table) {
                $table->foreign('active_variant_id')
                    ->references('id')->on('artist_epk_variants')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('artist_epks')) {
            Schema::table('artist_epks', function (Blueprint $table) {
                try {
                    $table->dropForeign(['active_variant_id']);
                } catch (\Throwable $e) {
                    // OK pe SQLite — FK-urile inline pot fi gestionate diferit.
                }
            });
        }
        Schema::dropIfExists('artist_epk_variants');
    }

    private function fkExists(string $table, string $name): bool
    {
        try {
            $fks = \Illuminate\Support\Facades\DB::select(
                "SELECT conname FROM pg_constraint WHERE conname = ?", [$name]
            );
            return !empty($fks);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
