<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuitul Sanius (Sf. Ana): sanii inchiriate pe tichetul de parcare, tractari
 * numarate offline, plata la iesire. Doar tabele noi; fara chei straine catre
 * tabelele existente (events, tickets, orders) ca migratia sa nu blocheze nimic
 * pe productie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sanius_settings')) {
            Schema::create('sanius_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->unique();
                $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->index();
                // off | test | live
                $table->string('mode', 8)->default('off');
                // [{member_id, role}] role: desk | tow | cashier | manager
                $table->json('members')->nullable();
                $table->unsignedSmallInteger('included_tows')->default(1);
                // {rent, tow, damage_small, damage_medium, damage_large, lost, parking_car, parking_bus} => ticket_type_id
                $table->json('products')->nullable();
                // {free_minutes, free_minutes_with_access, unit_minutes}
                $table->json('parking')->nullable();
                $table->unsignedSmallInteger('ping_timeout_seconds')->default(30);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sanius_sleds')) {
            Schema::create('sanius_sleds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedInteger('number');
                $table->string('label', 60)->nullable();
                $table->string('code', 32)->unique();
                // available | rented | repair | lost | retired
                $table->string('status', 12)->default('available');
                $table->boolean('is_test')->default(false);
                $table->string('notes', 500)->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'is_test', 'number']);
            });
        }

        if (! Schema::hasTable('sanius_tabs')) {
            Schema::create('sanius_tabs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->string('parking_code', 20);
                $table->timestamp('parking_entry_at')->nullable();
                $table->string('parking_number', 8)->nullable();
                $table->string('vehicle_plate', 16)->nullable()->index();
                // car | bus
                $table->string('vehicle_type', 8)->default('car');
                // open | settled | void
                $table->string('status', 10)->default('open');
                $table->boolean('is_test')->default(false);
                $table->unsignedBigInteger('opened_by_member_id')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->unsignedBigInteger('settled_by_member_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->boolean('settled_simulated')->default(false);
                $table->json('settlement')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'parking_code', 'status']);
            });
        }

        if (! Schema::hasTable('sanius_tab_tickets')) {
            Schema::create('sanius_tab_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tab_id')->constrained('sanius_tabs')->cascadeOnDelete();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('linked_by_member_id')->nullable();
                // desk | cashier | kiosk
                $table->string('linked_at_point', 10)->default('desk');
                $table->timestamps();

                $table->unique(['tab_id', 'ticket_id']);
            });
        }

        if (! Schema::hasTable('sanius_rentals')) {
            Schema::create('sanius_rentals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->foreignId('tab_id')->constrained('sanius_tabs')->cascadeOnDelete();
                $table->foreignId('sled_id')->constrained('sanius_sleds');
                // active | pending_sync | closed | lost
                $table->string('status', 14)->default('active');
                $table->timestamp('started_at');
                $table->unsignedBigInteger('started_by_member_id')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->unsignedBigInteger('returned_by_member_id')->nullable();
                $table->timestamp('closed_at')->nullable();
                // good | damaged
                $table->string('condition', 8)->nullable();
                // small | medium | large
                $table->string('damage_level', 8)->nullable();
                $table->string('damage_photo_path')->nullable();
                $table->unsignedSmallInteger('tows_count')->default(0);
                $table->unsignedSmallInteger('tows_included')->default(0);
                $table->unsignedSmallInteger('tows_paid')->default(0);
                $table->json('amounts')->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();

                $table->index(['sled_id', 'status']);
                $table->index(['event_id', 'status']);
            });
        }

        if (! Schema::hasTable('sanius_tow_shifts')) {
            Schema::create('sanius_tow_shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedBigInteger('member_id')->nullable()->index();
                $table->string('device_id', 64);
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('last_ping_at')->nullable();
                $table->unsignedInteger('pending_count')->default(0);
                $table->integer('clock_offset_ms')->default(0);
                $table->timestamp('force_unlocked_at')->nullable();
                $table->unsignedBigInteger('force_unlocked_by_member_id')->nullable();
                $table->string('force_unlock_reason', 500)->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();

                $table->index(['event_id', 'ended_at']);
            });
        }

        if (! Schema::hasTable('sanius_tows')) {
            Schema::create('sanius_tows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->foreignId('shift_id')->constrained('sanius_tow_shifts');
                $table->unsignedBigInteger('sled_id')->nullable()->index();
                $table->string('sled_code', 32);
                $table->unsignedBigInteger('rental_id')->nullable()->index();
                $table->string('client_uuid', 64)->unique();
                $table->timestamp('scanned_at_device');
                $table->timestamp('scanned_at');
                $table->timestamp('received_at');
                // counted | anomaly
                $table->string('status', 10)->default('counted');
                $table->string('anomaly_reason', 40)->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sanius_tows');
        Schema::dropIfExists('sanius_tow_shifts');
        Schema::dropIfExists('sanius_rentals');
        Schema::dropIfExists('sanius_tab_tickets');
        Schema::dropIfExists('sanius_tabs');
        Schema::dropIfExists('sanius_sleds');
        Schema::dropIfExists('sanius_settings');
    }
};
