<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Microservicii care nu apar deloc la /inregistrare (interne, de marketplace sau
 * pornite de noi pentru tipuri anume de tenant). Implicit false.
 */
return new class extends Migration
{
    private const HIDDEN_SLUGS = [
        'discovery-module',
        'activities-module',
        'ops-board',
        'media-partners',
        'cashless',
        'leisure-rentals',
        'leisure-pos',
        'leisure-core',
        'leisure-embed',
        'extended-artist',
    ];

    public function up(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            if (! Schema::hasColumn('microservices', 'signup_hidden')) {
                $table->boolean('signup_hidden')->default(false);
            }
        });

        DB::table('microservices')->whereIn('slug', self::HIDDEN_SLUGS)->update(['signup_hidden' => true]);
    }

    public function down(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            if (Schema::hasColumn('microservices', 'signup_hidden')) {
                $table->dropColumn('signup_hidden');
            }
        });
    }
};
