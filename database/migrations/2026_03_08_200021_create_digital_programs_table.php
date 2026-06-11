<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Digital program / caiet de sală for theater, opera, philharmonic
        Schema::create('digital_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repertoire_id')->nullable()->constrained('repertoire')->nullOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();

            $table->jsonb('title')->comment('Translatable');
            $table->jsonb('director_notes')->nullable()->comment('Translatable: note de regie');
            $table->jsonb('dramaturg_notes')->nullable()->comment('Translatable: note de dramaturgie');
            $table->jsonb('synopsis')->nullable()->comment('Translatable: rezumat piesa');
            $table->jsonb('program_content')->nullable()->comment('Translatable: HTML content of the program booklet');

            // Cast & creative team (structured for rendering)
            $table->json('creative_team')->nullable()
                ->comment('[{role: "Regia", name: "Ion Popescu"}, {role: "Scenografia", name: "..."}]');
            $table->json('cast_list')->nullable()
                ->comment('[{character: "Hamlet", actor: "...", is_understudy: false}]');

            // For concerts/opera with multiple pieces
            $table->json('program_pieces')->nullable()
                ->comment('[{composer: "Beethoven", title: "Simfonia nr. 5", duration: "35min", notes: "..."}]');

            // Intermission info
            $table->integer('intermission_count')->default(0);
            $table->json('intermission_details')->nullable()
                ->comment('[{after_act: 1, duration_minutes: 20}]');

            // Sponsors block
            $table->json('sponsors')->nullable()
                ->comment('[{name: "...", logo_url: "...", tier: "gold|silver|bronze|partner"}]');

            // Surtitles info (opera/theater)
            $table->boolean('has_surtitles')->default(false);
            $table->json('surtitle_languages')->nullable()->comment('["ro","en"]');

            $table->string('cover_image_url')->nullable();
            $table->string('pdf_url')->nullable()->comment('Downloadable PDF version');

            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_programs');
    }
};
