<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Per-event FOMO toggle. Off by default; activated manually
            // per event from the Filament admin (Setări Featured section).
            // No FOMO logic runs anywhere unless this is true, so the
            // feature is invisible on the public site until enabled.
            $table->boolean('generate_fomo')->default(false)->after('is_category_featured');

            // Persisted "displayed remaining tickets" counter for the
            // scarcity progress bar. Stored on the row so it survives
            // cache flushes — the FOMO service ratchets this down over
            // time and never lets it rise, which is what prevents the
            // "today 18, tomorrow 23, day after 5" yo-yo pattern that
            // would otherwise show whenever real inventory fluctuates
            // (refunds, holds expiring, etc.).
            $table->integer('fomo_displayed_remaining')->nullable()->after('generate_fomo');
            $table->timestamp('fomo_displayed_remaining_updated_at')->nullable()->after('fomo_displayed_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'generate_fomo',
                'fomo_displayed_remaining',
                'fomo_displayed_remaining_updated_at',
            ]);
        });
    }
};
