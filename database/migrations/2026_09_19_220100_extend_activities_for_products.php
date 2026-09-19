<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module (bilete.online): an activity becomes a PRODUCT of a
 * location, one of three types:
 *
 *   access      entry tickets (adult / child / group, parking, camping…),
 *               usually valid all day (booking_mode = day)
 *   experience  services such as a 30-min boat ride or a guided tour, on time
 *               slots or all day
 *   package     a fixed-price bundle of access tickets and experiences
 *               (activity_package_items)
 *
 * Only activity_* tables change; they are used by the activities module alone.
 * Every new column is nullable or has a default that keeps today's rows
 * behaving exactly as before (experience, slot mode, capacity per slot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('venue_id')
                    ->constrained('activity_locations')->nullOnDelete();
            }
            if (!Schema::hasColumn('activities', 'product_type')) {
                $table->string('product_type', 16)->default('experience');    // access|experience|package
                $table->string('access_kind', 16)->nullable();                // access: person|vehicle|camping|other
                $table->string('service_type', 16)->nullable();               // experience: rental|guided|workshop|other
                $table->string('booking_mode', 16)->default('slot');          // slot|day
                $table->string('capacity_mode', 16)->default('per_slot');     // slot mode: per_slot|concurrent
                $table->unsignedInteger('daily_capacity')->nullable();         // day mode (and optional cap in slot mode)
                $table->boolean('use_location_schedule')->default(false);      // opening hours from the location's seasons
                $table->string('access_requirement', 16)->default('none');    // none|any|adult: needs an access ticket in the cart
                $table->boolean('requires_vehicle_info')->default(false);      // parking: plate number
                $table->boolean('pos_only')->default(false);                   // sold only at the venue
                $table->string('issuing_company', 16)->default('primary');    // primary|secondary
                $table->string('display_category', 64)->nullable();           // id from location.display_categories
                $table->jsonb('unit_label')->nullable();                      // e.g. {"ro": "persoană / zi"}
                $table->jsonb('usage_terms')->nullable();
                $table->string('icon', 16)->nullable();
                // Operator-created products need the admin's approval before the
                // first publication. Null = created by the admin (no review).
                $table->string('review_status', 16)->nullable();              // draft|pending|approved|rejected
                $table->dateTime('submitted_at')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('rejection_reason')->nullable();

                $table->index(['location_id'], 'activities_location_idx');
                $table->index(['marketplace_client_id', 'product_type', 'is_published'], 'activities_mp_type_pub_idx');
            }
        });

        Schema::table('activity_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_variants', 'duration_minutes')) {
                $table->unsignedSmallInteger('duration_minutes')->nullable();  // overrides activities.duration_minutes (30 / 60 min boat)
                $table->unsignedSmallInteger('validity_days')->default(1);     // access: consecutive days covered (camping nights, multi-day pass)
                $table->string('price_type', 16)->default('per_person');      // per_person|per_unit (a boat, a car, a family)
                $table->unsignedSmallInteger('persons_min')->nullable();       // people covered by one unit / group size
                $table->unsignedSmallInteger('persons_max')->nullable();
                $table->boolean('is_child')->default(false);                   // for "adult required" rules
                $table->integer('pos_price_cents')->nullable();                // on-site price
                $table->boolean('pos_only')->default(false);
                $table->unsignedSmallInteger('step_qty')->nullable();          // quantity steps (groups)
                $table->string('companion_label', 80)->nullable();             // free companion ticket (guide, teacher) when qty >= min_per_order
            }
        });

        Schema::table('activity_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_schedules', 'season_start')) {
                // Row applies only between these month-days (every year; may
                // wrap the new year). Null = all year.
                $table->char('season_start', 5)->nullable();
                $table->char('season_end', 5)->nullable();
            }
        });

        Schema::table('activity_bookings', function (Blueprint $table) {
            // Day tickets have no time.
            $table->time('slot_start_time')->nullable()->change();
            $table->time('slot_end_time')->nullable()->change();
        });

        Schema::table('activity_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_bookings', 'variant_id')) {
                $table->foreignId('variant_id')->nullable()->after('activity_id')
                    ->constrained('activity_variants')->nullOnDelete();
                $table->foreignId('location_id')->nullable()
                    ->constrained('activity_locations')->nullOnDelete();
                // Per-booking operator: one order can hold several operators' products.
                $table->foreignId('marketplace_organizer_id')->nullable()
                    ->constrained('marketplace_organizers')->nullOnDelete();
                // Component bookings point at their package booking.
                $table->unsignedBigInteger('package_booking_id')->nullable();
                $table->foreign('package_booking_id', 'act_bookings_package_fk')
                    ->references('id')->on('activity_bookings')->nullOnDelete();
                $table->date('end_date')->nullable();                          // last day covered (multi-day access)
                $table->unsignedSmallInteger('quantity')->default(1);          // units / persons bought
                $table->integer('unit_price_cents')->nullable();
                $table->jsonb('addons')->nullable();
                $table->jsonb('meta')->nullable();

                $table->index(['location_id', 'booking_date'], 'act_bookings_location_date_idx');
                $table->index(['marketplace_organizer_id', 'booking_date'], 'act_bookings_organizer_date_idx');
                $table->index(['package_booking_id'], 'act_bookings_package_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('activity_bookings', 'variant_id')) {
                $table->dropIndex('act_bookings_location_date_idx');
                $table->dropIndex('act_bookings_organizer_date_idx');
                $table->dropIndex('act_bookings_package_idx');
                $table->dropForeign('act_bookings_package_fk');
                $table->dropConstrainedForeignId('variant_id');
                $table->dropConstrainedForeignId('location_id');
                $table->dropConstrainedForeignId('marketplace_organizer_id');
                $table->dropColumn(['package_booking_id', 'end_date', 'quantity', 'unit_price_cents', 'addons', 'meta']);
            }
        });

        Schema::table('activity_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('activity_schedules', 'season_start')) {
                $table->dropColumn(['season_start', 'season_end']);
            }
        });

        Schema::table('activity_variants', function (Blueprint $table) {
            if (Schema::hasColumn('activity_variants', 'duration_minutes')) {
                $table->dropColumn([
                    'duration_minutes', 'validity_days', 'price_type', 'persons_min', 'persons_max',
                    'is_child', 'pos_price_cents', 'pos_only', 'step_qty', 'companion_label',
                ]);
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'product_type')) {
                $table->dropIndex('activities_location_idx');
                $table->dropIndex('activities_mp_type_pub_idx');
                $table->dropConstrainedForeignId('reviewed_by');
                $table->dropColumn([
                    'product_type', 'access_kind', 'service_type', 'booking_mode', 'capacity_mode',
                    'daily_capacity', 'use_location_schedule', 'access_requirement', 'requires_vehicle_info',
                    'pos_only', 'issuing_company', 'display_category', 'unit_label', 'usage_terms', 'icon',
                    'review_status', 'submitted_at', 'reviewed_at', 'rejection_reason',
                ]);
            }
            if (Schema::hasColumn('activities', 'location_id')) {
                $table->dropConstrainedForeignId('location_id');
            }
        });

        // slot_start_time / slot_end_time stay nullable: day-ticket rows may
        // exist by then and would block NOT NULL.
    }
};
