<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema drift fix: `marketplace_newsletters.body_html` was created
     * NOT NULL by 2025_12_29_000003 back when the editor wrote raw HTML
     * directly to that column. The 2026_03_07 migration introduced
     * `body_sections` (JSON) as the new content model, and the Filament
     * form stopped writing body_html for new newsletters — but the column
     * is still NOT NULL, so every "Create newsletter" now blows up with
     * SQLSTATE[23502].
     *
     * We don't drop the column: legacy rows may still carry HTML there,
     * and a few code paths still read it as a fallback.
     */
    public function up(): void
    {
        Schema::table('marketplace_newsletters', function (Blueprint $table) {
            $table->text('body_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Re-tightening would refuse any newsletter created after this
        // fix (they all have body_html=null), so the down() stays a no-op.
    }
};
