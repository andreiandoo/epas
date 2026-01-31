<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // EVENTS
        if (Schema::hasTable('events')) {
            return;
        }

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title', 190);
            $table->string('slug', 190)->unique();
            $table->text('description')->nullable();
            $table->string('venue_name', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('status', 32)->default('published'); // draft|published|archived
            $table->string('seo_title', 190)->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // PERFORMANCES (occurrences)
        if (Schema::hasTable('performances')) {
            return;
        }

        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 32)->default('active'); // active|cancelled|finished
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
        });

        // TICKET TYPES (early-bird, standard, etc.)
        if (Schema::hasTable('ticket_types')) {
            return;
        }

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 120);
            $table->bigInteger('price_cents')->default(0);
            $table->string('currency', 8)->default('RON');
            $table->integer('quota_total')->default(0);
            $table->integer('quota_sold')->default(0);
            $table->string('status', 32)->default('active'); // active|hidden|disabled
            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        // TICKETS
        if (Schema::hasTable('tickets')) {
            return;
        }

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('ticket_type_id')->constrained('ticket_types')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('performance_id')->nullable()->constrained('performances')->cascadeOnUpdate()->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('status', 32)->default('valid'); // valid|used|void
            $table->string('seat_label', 64)->nullable();  // ex: "A-12"
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['ticket_type_id', 'status']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('performances');
        Schema::dropIfExists('events');
    }
};
