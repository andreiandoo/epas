<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rewards')) {
            return;
        }

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->cascadeOnDelete();

            // Basic info
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->string('slug')->unique();
            $table->string('image_url')->nullable();

            // Reward type and value
            $table->enum('type', ['fixed_discount', 'percentage_discount', 'free_item', 'voucher_code'])->default('fixed_discount');
            $table->integer('points_cost'); // How many points to redeem
            $table->decimal('value', 10, 2); // Discount amount or percentage
            $table->string('currency', 3)->default('RON');
            $table->string('voucher_prefix', 10)->nullable(); // For voucher_code type

            // Restrictions
            $table->decimal('min_order_value', 10, 2)->nullable(); // Minimum order to use reward
            $table->integer('max_redemptions_total')->nullable(); // Total redemption limit
            $table->integer('max_redemptions_per_customer')->nullable(); // Per-customer limit

            // Validity
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            // Requirements
            $table->json('required_tiers')->nullable(); // Array of tier names required
            $table->integer('min_level_required')->nullable(); // Minimum XP level required

            // Display settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['marketplace_client_id', 'is_active']);
            $table->index(['is_active', 'is_featured', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
