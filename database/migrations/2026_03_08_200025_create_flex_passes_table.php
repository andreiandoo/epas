<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flex_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('total_entries')->comment('Number of entries included in the pass');
            $table->integer('price_cents');
            $table->string('currency', 3)->default('RON');
            $table->string('status')->default('active')->comment('draft|active|paused|expired');
            $table->json('eligible_event_ids')->nullable()->comment('Null = all events; array of event IDs if restricted');
            $table->json('eligible_ticket_type_ids')->nullable()->comment('Null = all ticket types; restrict to specific types');
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->integer('max_sales')->nullable()->comment('Null = unlimited');
            $table->integer('total_sold')->default(0);
            $table->integer('max_entries_per_event')->default(1)->comment('How many entries can be used for the same event');
            $table->boolean('is_transferable')->default(false);
            $table->boolean('is_refundable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('flex_pass_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flex_pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique()->comment('Unique pass code for scanning');
            $table->integer('entries_remaining');
            $table->integer('entries_used')->default(0);
            $table->string('status')->default('active')->comment('active|fully_used|expired|cancelled|refunded');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['status']);
        });

        Schema::create('flex_pass_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flex_pass_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('confirmed')->comment('confirmed|cancelled|no_show');
            $table->dateTime('redeemed_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['flex_pass_purchase_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flex_pass_redemptions');
        Schema::dropIfExists('flex_pass_purchases');
        Schema::dropIfExists('flex_passes');
    }
};
