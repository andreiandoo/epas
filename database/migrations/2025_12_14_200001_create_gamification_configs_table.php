<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Point value configuration
            $table->integer('point_value_cents')->default(1); // How many cents is 1 point worth
            $table->string('currency', 3)->default('RON');

            // Earning configuration
            $table->decimal('earn_percentage', 5, 2)->default(5.00); // % of order value converted to points
            $table->boolean('earn_on_subtotal')->default(true); // Earn on subtotal vs total (with fees)
            $table->integer('min_order_cents_for_earning')->default(0); // Minimum order to earn points

            // Redemption configuration
            $table->integer('min_redeem_points')->default(100); // Minimum points to redeem
            $table->decimal('max_redeem_percentage', 5, 2)->default(50.00); // Max % of order that can be paid with points
            $table->integer('max_redeem_points_per_order')->nullable(); // Cap on points per order

            // Special bonuses
            $table->integer('birthday_bonus_points')->default(100);
            $table->integer('signup_bonus_points')->default(50);
            $table->integer('referral_bonus_points')->default(200); // Points for referrer
            $table->integer('referred_bonus_points')->default(100); // Points for referred customer

            // Expiration settings
            $table->integer('points_expire_days')->nullable(); // Null = never expire
            $table->boolean('expire_on_inactivity')->default(false);
            $table->integer('inactivity_days')->default(365);

            // Display settings
            $table->string('points_name')->default('puncte'); // "points", "coins", "stars", etc.
            $table->string('points_name_singular')->default('punct');
            $table->string('icon')->default('star'); // Icon name for UI

            // Tiers/Levels (JSON array of tier definitions)
            $table->json('tiers')->nullable();
            // Structure: [{ name: "Bronze", min_points: 0, benefits: [...] }, { name: "Silver", min_points: 500 }, ...]

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_configs');
    }
};
