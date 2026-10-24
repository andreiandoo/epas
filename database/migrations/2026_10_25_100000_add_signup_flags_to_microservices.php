<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Microserviciile alese la /inregistrare: unele ridică comisionul cu 1 punct în loc
 * să se plătească separat, iar o parte apar în lista scurtă. Implicit false, deci
 * nimic nu se schimbă pentru microserviciile și tenanții existenți.
 */
return new class extends Migration
{
    private const DEFAULT_SLUGS = [
        'door-sales',
        'facebook-capi-integration',
        'accounting-connectors',
        'coupon-codes',
        'shop',
        'live-chat',
        'leisure-multi-society',
    ];

    public function up(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            if (! Schema::hasColumn('microservices', 'raises_commission')) {
                $table->boolean('raises_commission')->default(false);
            }
            if (! Schema::hasColumn('microservices', 'signup_featured')) {
                $table->boolean('signup_featured')->default(false);
            }
        });

        DB::table('microservices')->whereIn('slug', self::DEFAULT_SLUGS)
            ->update(['raises_commission' => true, 'signup_featured' => true]);
    }

    public function down(): void
    {
        Schema::table('microservices', function (Blueprint $table) {
            foreach (['raises_commission', 'signup_featured'] as $column) {
                if (Schema::hasColumn('microservices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
