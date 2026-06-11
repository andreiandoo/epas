<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('category')->nullable()->after('code');
            $table->json('tags')->nullable()->after('category');
            $table->unsignedBigInteger('customer_id')->nullable()->after('tenant_id'); // For customer-specific codes
            $table->string('referral_source')->nullable()->after('customer_id');
            $table->string('campaign_id')->nullable()->after('referral_source');
            $table->boolean('combinable')->default(false)->after('is_public'); // Can be combined with other codes
            $table->json('exclude_combinations')->nullable()->after('combinable'); // IDs of codes it can't combine with
            $table->string('variant')->nullable()->after('code'); // For A/B testing (A, B, C, etc.)
            $table->decimal('conversion_rate', 5, 2)->nullable()->after('usage_count'); // For A/B testing tracking

            $table->index('category');
            $table->index('customer_id');
            $table->index('campaign_id');
            $table->index('variant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'tags',
                'customer_id',
                'referral_source',
                'campaign_id',
                'combinable',
                'exclude_combinations',
                'variant',
                'conversion_rate',
            ]);
        });
    }
};
