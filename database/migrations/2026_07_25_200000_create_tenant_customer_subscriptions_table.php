<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonamentele CUMPĂRATE de clienți (instanțe), cu locurile alese (fixe pe toate
 * spectacolele incluse) și consumul (câte spectacole au fost folosite).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenant_customer_subscriptions')) {
            return;
        }

        Schema::create('tenant_customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained('tenant_subscription_plans')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('status', 32)->default('pending')->comment('pending|active|expired|cancelled');

            $table->integer('shows_included')->nullable();
            $table->integer('shows_used')->default(0);
            $table->integer('tickets_included')->default(1);

            $table->jsonb('seat_uids')->nullable();
            $table->jsonb('seat_labels')->nullable();
            $table->unsignedBigInteger('event_seating_id')->nullable();

            $table->integer('price_cents')->default(0);
            $table->string('currency', 3)->default('RON');

            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('priority_access')->default(false);

            $table->jsonb('benefits')->nullable();
            $table->jsonb('redeemed_events')->nullable()->comment('[{event_id,title,date,redeemed_at}]');
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_customer_subscriptions');
    }
};
