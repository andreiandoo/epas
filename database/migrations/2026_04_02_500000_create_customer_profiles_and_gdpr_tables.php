<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Customer Profiles (behavioral profiling)
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();

            // Demographics
            $table->integer('age')->nullable();
            $table->string('age_group', 20)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 2)->nullable();

            // Spending behavior
            $table->integer('total_spent_cents')->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('avg_transaction_cents')->default(0);
            $table->integer('max_transaction_cents')->default(0);
            $table->integer('min_transaction_cents')->default(0);
            $table->integer('total_topped_up_cents')->default(0);
            $table->integer('total_cashed_out_cents')->default(0);
            $table->integer('net_spend_cents')->default(0);

            // Product preferences
            $table->json('top_categories')->nullable();
            $table->json('top_products')->nullable();
            $table->json('top_vendors')->nullable();
            $table->json('product_type_distribution')->nullable();

            // Temporal patterns
            $table->timestamp('first_transaction_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->integer('peak_hour')->nullable();
            $table->json('active_hours')->nullable();
            $table->json('active_days')->nullable();
            $table->integer('avg_time_between_purchases')->nullable();

            // Engagement scores
            $table->integer('spending_score')->default(0);
            $table->integer('frequency_score')->default(0);
            $table->integer('diversity_score')->default(0);
            $table->integer('overall_score')->default(0);

            // Segments & tags
            $table->string('segment', 50)->nullable();
            $table->json('tags')->nullable();

            // Flags
            $table->boolean('is_minor')->default(false);
            $table->boolean('has_age_restricted_attempts')->default(false);
            $table->boolean('flagged_for_review')->default(false);
            $table->string('flag_reason')->nullable();

            $table->json('meta')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['cashless_account_id'], 'customer_profile_account_unique');
            $table->index(['festival_edition_id', 'segment']);
            $table->index('overall_score');
        });

        // 2. Cashless Notification Preferences
        Schema::create('cashless_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashless_account_id')->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('notify_on_purchase')->default(true);
            $table->boolean('notify_on_topup')->default(true);
            $table->boolean('notify_on_cashout')->default(true);
            $table->boolean('notify_on_transfer')->default(true);
            $table->boolean('notify_low_balance')->default(true);
            $table->boolean('daily_summary')->default(false);
            $table->boolean('profiling_opt_out')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 3. GDPR Requests
        Schema::create('cashless_gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('request_type', 20); // export, deletion, rectification, objection
            $table->string('status', 20)->default('pending'); // pending, processing, completed, rejected
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('export_file_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashless_gdpr_requests');
        Schema::dropIfExists('cashless_notification_preferences');
        Schema::dropIfExists('customer_profiles');
    }
};
