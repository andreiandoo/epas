<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captions (B6) + the organiser analytics rollup (B5) + the auto-generation
 * bookkeeping (B3).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('short_captions')) {
            Schema::create('short_captions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->string('language', 8);
                $table->string('vtt_path');
                $table->boolean('auto_generated')->default(true);
                $table->timestamps();

                $table->unique(['short_id', 'language'], 'short_captions_unique');
            });
        }

        if (! Schema::hasTable('short_analytics_daily')) {
            Schema::create('short_analytics_daily', function (Blueprint $table) {
                $table->id();
                $table->foreignId('short_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedBigInteger('completions')->default(0);
                $table->unsignedInteger('unique_viewers')->default(0);
                $table->decimal('avg_watch_ratio', 4, 3)->default(0);
                $table->unsignedBigInteger('likes')->default(0);
                $table->unsignedBigInteger('saves')->default(0);
                $table->unsignedBigInteger('shares')->default(0);
                $table->unsignedBigInteger('cta_clicks')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->unsignedBigInteger('revenue_cents')->default(0);

                $table->unique(['short_id', 'date'], 'short_analytics_daily_unique');
                $table->index('date');
            });
        }

        if (Schema::hasTable('shorts')) {
            Schema::table('shorts', function (Blueprint $table) {
                // Set while a render is in flight, so a re-run does not queue a
                // second render for the same short (B3).
                if (! Schema::hasColumn('shorts', 'render_job_id')) {
                    $table->string('render_job_id')->nullable()->index();
                }

                // "poster short": a still image played as a card in the feed —
                // the MVP that fills the feed before a real renderer exists.
                if (! Schema::hasColumn('shorts', 'is_generated')) {
                    $table->boolean('is_generated')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('short_captions');
        Schema::dropIfExists('short_analytics_daily');

        if (Schema::hasTable('shorts')) {
            Schema::table('shorts', function (Blueprint $table) {
                $table->dropColumn(['render_job_id', 'is_generated']);
            });
        }
    }
};
