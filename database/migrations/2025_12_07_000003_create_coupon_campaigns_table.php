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
        // Coupon campaigns
        if (Schema::hasTable('coupon_campaigns')) {
            return;
        }

        Schema::create('coupon_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('name'); // Translatable
            $table->json('description')->nullable(); // Translatable
            $table->enum('status', ['draft', 'active', 'paused', 'expired', 'archived'])->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->decimal('budget_limit', 12, 2)->nullable(); // Max total discount amount
            $table->decimal('budget_used', 12, 2)->default(0);
            $table->integer('redemption_limit')->nullable(); // Max total redemptions
            $table->integer('redemption_count')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        // Coupon codes (enhanced)
        if (Schema::hasTable('coupon_codes')) {
            return;
        }

        Schema::create('coupon_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->uuid('campaign_id')->nullable();
            $table->string('code', 50);
            $table->enum('code_type', ['single_use', 'multi_use', 'unique_per_user'])->default('multi_use');

            // Discount Configuration
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'free_shipping', 'free_trial', 'bogo'])->default('percentage');
            $table->decimal('discount_value', 10, 2); // 20 for 20% or $20
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // Cap for percentage discounts

            // Usage Rules
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->integer('min_quantity')->nullable(); // Min items in cart
            $table->integer('max_uses_total')->nullable(); // null = unlimited
            $table->integer('max_uses_per_user')->nullable();
            $table->integer('current_uses')->default(0);

            // Restrictions
            $table->json('applicable_products')->nullable(); // Product IDs
            $table->json('excluded_products')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->json('applicable_events')->nullable(); // For event tickets
            $table->json('allowed_user_segments')->nullable(); // "new_users", "premium", etc.
            $table->boolean('first_purchase_only')->default(false);

            // Time Constraints
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('valid_days_of_week')->nullable(); // 0-6
            $table->json('valid_hours')->nullable(); // { start: "09:00", end: "17:00" }
            $table->string('timezone', 50)->nullable();

            // Status
            $table->enum('status', ['active', 'disabled', 'exhausted', 'expired'])->default('active');
            $table->boolean('is_public')->default(true);

            // Stacking
            $table->boolean('combinable')->default(false);
            $table->json('exclude_combinations')->nullable(); // IDs of codes that can't be combined

            // Tracking
            $table->string('source')->nullable(); // "influencer", "email_campaign", etc.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('campaign_id')->references('id')->on('coupon_campaigns')->onDelete('set null');
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['campaign_id', 'status']);
            $table->index(['code', 'status']);
            $table->index(['expires_at', 'status']);
        });

        // Coupon redemptions
        if (Schema::hasTable('coupon_redemptions')) {
            return;
        }

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->uuid('coupon_id');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('order_id'); // External order reference

            // Redemption Details
            $table->decimal('discount_applied', 10, 2);
            $table->decimal('original_amount', 10, 2);
            $table->decimal('final_amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_fingerprint', 64)->nullable();

            // Status
            $table->enum('status', ['applied', 'completed', 'refunded', 'cancelled'])->default('applied');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->foreign('coupon_id')->references('id')->on('coupon_codes')->onDelete('cascade');
            $table->unique(['coupon_id', 'order_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'coupon_id']);
            $table->index(['order_id']);
        });

        // Bulk code generation jobs
        if (Schema::hasTable('coupon_generation_jobs')) {
            return;
        }

        Schema::create('coupon_generation_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->uuid('campaign_id')->nullable();
            $table->integer('quantity');
            $table->string('pattern')->nullable(); // "SUMMER-####-@@"
            $table->string('prefix')->nullable();
            $table->string('suffix')->nullable();
            $table->json('template_data')->nullable(); // Discount settings to apply
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('codes_generated')->default(0);
            $table->string('download_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('coupon_campaigns')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
        });

        // Validation attempts (for fraud detection)
        if (Schema::hasTable('coupon_validation_attempts')) {
            return;
        }

        Schema::create('coupon_validation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('coupon_code', 50);
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('cart_amount', 10, 2)->nullable();
            $table->boolean('is_valid');
            $table->string('rejection_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'ip_address', 'created_at']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index(['coupon_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_validation_attempts');
        Schema::dropIfExists('coupon_generation_jobs');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_codes');
        Schema::dropIfExists('coupon_campaigns');
    }
};
