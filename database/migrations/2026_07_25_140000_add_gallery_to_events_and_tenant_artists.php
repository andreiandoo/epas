<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo galleries: per-event (shown on the public event page) and per
 * tenant artist (shown on the public artist page). Both nullable JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'gallery')) {
                $table->json('gallery')->nullable()->after('hero_image_url');
            }
        });

        Schema::table('tenant_artists', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_artists', 'gallery')) {
                $table->json('gallery')->nullable()->after('photo_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('gallery');
        });
        Schema::table('tenant_artists', function (Blueprint $table) {
            $table->dropColumn('gallery');
        });
    }
};
