<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Action identification
            $table->string('action_type', 50); // order, birthday, referral, signup, review, social_share, etc.
            $table->json('name'); // Translatable name
            $table->json('description')->nullable(); // Translatable description

            // Points configuration
            $table->enum('points_type', ['fixed', 'percentage', 'multiplier'])->default('fixed');
            $table->integer('points_amount')->default(0); // Fixed points or percentage value
            $table->decimal('multiplier', 5, 2)->default(1.00); // Multiplier for order-based points

            // Conditions
            $table->integer('min_order_cents')->nullable(); // Minimum order value for action
            $table->integer('max_points_per_action')->nullable(); // Cap on points earned
            $table->integer('max_times_per_day')->nullable(); // Limit actions per day
            $table->integer('max_times_per_customer')->nullable(); // Lifetime limit per customer
            $table->integer('cooldown_hours')->nullable(); // Hours between earning same action

            // Validity
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('valid_days')->nullable(); // [0,1,2,3,4,5,6] for specific days of week

            // Targeting
            $table->json('customer_tiers')->nullable(); // Only for specific tiers
            $table->boolean('new_customers_only')->default(false);
            $table->integer('min_orders_required')->default(0);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'action_type']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_actions');
    }
};
