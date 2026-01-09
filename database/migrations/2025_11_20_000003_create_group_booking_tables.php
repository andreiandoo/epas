<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Group bookings
        if (Schema::hasTable('group_bookings')) {
            return;
        }

        Schema::create('group_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('organizer_customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('group_name');
            $table->string('group_type')->default('corporate'); // corporate, school, club, family
            $table->integer('total_tickets');
            $table->decimal('total_amount', 12, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'confirmed', 'partially_paid', 'paid', 'cancelled'])->default('draft');
            $table->enum('payment_type', ['full', 'split', 'invoice'])->default('full');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['event_id', 'status']);
        });

        // Group members
        if (Schema::hasTable('group_booking_members')) {
            return;
        }

        Schema::create('group_booking_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'refunded'])->default('pending');
            $table->string('payment_link')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['group_booking_id', 'payment_status']);
        });

        // Group pricing tiers
        if (Schema::hasTable('group_pricing_tiers')) {
            return;
        }

        Schema::create('group_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->integer('min_tickets');
            $table->integer('max_tickets')->nullable();
            $table->decimal('discount_percentage', 5, 2);
            $table->boolean('is_default')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_booking_members');
        Schema::dropIfExists('group_bookings');
        Schema::dropIfExists('group_pricing_tiers');
    }
};
