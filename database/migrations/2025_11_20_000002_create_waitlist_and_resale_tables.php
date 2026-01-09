<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Waitlist entries
        if (Schema::hasTable('event_waitlist')) {
            return;
        }

        Schema::create('event_waitlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_type_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->string('email')->index();
            $table->string('name')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('priority')->default(0);
            $table->enum('status', ['waiting', 'notified', 'purchased', 'expired', 'cancelled'])->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status', 'priority']);
            $table->index(['tenant_id', 'status']);
        });

        // Resale listings
        if (Schema::hasTable('resale_listings')) {
            return;
        }

        Schema::create('resale_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('seller_customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('original_price', 10, 2);
            $table->decimal('asking_price', 10, 2);
            $table->decimal('max_allowed_price', 10, 2);
            $table->enum('status', ['active', 'sold', 'cancelled', 'expired'])->default('active');
            $table->timestamp('listed_at');
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('buyer_customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('seller_payout', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['ticket_id', 'status']);
        });

        // Resale transactions
        if (Schema::hasTable('resale_transactions')) {
            return;
        }

        Schema::create('resale_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('resale_listings')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('buyer_customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('seller_customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('sale_price', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('seller_payout', 10, 2);
            $table->enum('payout_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['payout_status']);
        });

        // Resale policies
        if (Schema::hasTable('resale_policies')) {
            return;
        }

        Schema::create('resale_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('max_markup_percentage', 5, 2)->default(120); // 120% of original
            $table->decimal('platform_fee_percentage', 5, 2)->default(3);
            $table->integer('min_hours_before_resale')->default(24);
            $table->integer('min_hours_before_event')->default(2);
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resale_transactions');
        Schema::dropIfExists('resale_listings');
        Schema::dropIfExists('resale_policies');
        Schema::dropIfExists('event_waitlist');
    }
};
