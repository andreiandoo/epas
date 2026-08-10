<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave-1 columns on `shorts`:
 *   - share_card_path : branded OG/story card generated after the asset is ready (D1)
 *   - blurhash        : LQIP placeholder shown before the poster loads (D9)
 *   - content_flags   : ['flashing', 'alcohol', ...] — drives content warnings (D10)
 *
 * Additive and nullable throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shorts')) {
            return;
        }

        Schema::table('shorts', function (Blueprint $table) {
            if (! Schema::hasColumn('shorts', 'share_card_path')) {
                $table->string('share_card_path')->nullable();
            }

            if (! Schema::hasColumn('shorts', 'blurhash')) {
                $table->string('blurhash', 64)->nullable();
            }

            if (! Schema::hasColumn('shorts', 'content_flags')) {
                $table->json('content_flags')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shorts')) {
            return;
        }

        Schema::table('shorts', function (Blueprint $table) {
            $table->dropColumn(['share_card_path', 'blurhash', 'content_flags']);
        });
    }
};
