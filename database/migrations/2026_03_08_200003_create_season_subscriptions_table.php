<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('season_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name')->comment('Subscription label, e.g. "Abonament Integral Stagiunea 2025-2026"');
            $table->string('subscription_type', 32)->default('full')->comment('full|partial|custom');

            // Seat reservation for the entire season
            $table->string('seat_label')->nullable()->comment('Reserved seat label, e.g. "Row 5, Seat 12"');
            $table->string('seat_uid')->nullable()->comment('Seat UID from seating layout');
            $table->foreignId('section_id')->nullable()->comment('Section from seating layout');

            // Pricing
            $table->integer('price_cents');
            $table->string('currency', 3)->default('RON');

            // Status & validity
            $table->string('status', 32)->default('pending')->comment('pending|active|expired|cancelled');
            $table->json('events_included')->nullable()->comment('Array of event IDs included in subscription');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('auto_renew')->default(false);

            // Subscriber details (for non-registered customers)
            $table->string('subscriber_name')->nullable();
            $table->string('subscriber_email')->nullable();
            $table->string('subscriber_phone')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['season_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['seat_uid', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('season_subscriptions');
    }
};
