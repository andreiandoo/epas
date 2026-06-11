<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            // Imagine landscape principala (existentul poster_url ramane portrait)
            $table->string('cover_url')->nullable()->after('poster_url');

            // Descriere scurta translatable (in plus fata de description care e long-form)
            $table->json('short_description')->nullable()->after('description')
                ->comment('Translatable short description (textarea)');

            // Setlist: [{title, sort_order}, ...] + durata totala generala
            $table->json('setlist')->nullable()->after('short_description');
            $table->unsignedSmallInteger('setlist_duration_minutes')->nullable()->after('setlist');

            // FAQ: [{question, answer}, ...]
            $table->json('faq')->nullable()->after('setlist_duration_minutes');

            // Alte detalii
            $table->string('age_min', 20)->nullable()->after('faq')
                ->comment('Varsta minima (ex: 18+, 16+)');
            $table->foreignId('marketplace_organizer_id')->nullable()->after('age_min')
                ->constrained('marketplace_organizers')->nullOnDelete();

            $table->index(['marketplace_organizer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropForeign(['marketplace_organizer_id']);
            $table->dropIndex(['marketplace_organizer_id']);
            $table->dropColumn([
                'cover_url',
                'short_description',
                'setlist',
                'setlist_duration_minutes',
                'faq',
                'age_min',
                'marketplace_organizer_id',
            ]);
        });
    }
};
