<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Wristband transactions (cashless payment log) ───
        Schema::create('wristband_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wristband_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type')->comment('topup|payment|refund|correction|transfer_in|transfer_out');
            $table->integer('amount_cents');
            $table->integer('balance_before_cents');
            $table->integer('balance_after_cents');
            $table->string('currency', 3)->default('RON');
            $table->string('description')->nullable();
            $table->string('vendor_name')->nullable()->comment('Name of food/drink/merch vendor');
            $table->string('vendor_location')->nullable()->comment('POI or zone reference');
            $table->string('payment_method')->nullable()->comment('cash|card|online (for top-ups)');
            $table->string('reference')->nullable()->comment('External transaction reference');
            $table->foreignId('related_wristband_id')->nullable()->comment('For transfers between wristbands');
            $table->string('operator')->nullable()->comment('Staff member who processed');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['wristband_id', 'created_at']);
            $table->index(['tenant_id', 'transaction_type']);
            $table->index(['vendor_name', 'created_at']);
        });

        // ── Festival volunteers ─────────────────────────────
        Schema::create('festival_volunteers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete()->comment('If volunteer is also a customer/attendee');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('id_number')->nullable()->comment('CNP or ID card number');
            $table->string('role')->comment('general|security|medical|logistics|hospitality|tech|info|bar|cleanup|parking|camping');
            $table->string('department')->nullable()->comment('Organizational department');
            $table->string('team_leader')->nullable();
            $table->text('skills')->nullable();
            $table->string('tshirt_size')->nullable();
            $table->string('dietary_restrictions')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('status')->default('applied')->comment('applied|approved|confirmed|checked_in|active|completed|cancelled|no_show');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->json('assigned_zone_ids')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['event_id', 'role']);
            $table->unique(['tenant_id', 'email']);
        });

        // ── Volunteer shifts ────────────────────────────────
        Schema::create('volunteer_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('festival_volunteer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_day_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->comment('Role for this specific shift');
            $table->string('zone')->nullable()->comment('Area/stage/gate assignment');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->string('status')->default('scheduled')->comment('scheduled|checked_in|completed|missed|cancelled');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['festival_volunteer_id', 'status']);
        });

        // ── Festival transport shuttles ─────────────────────
        Schema::create('festival_transport_shuttles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->comment('e.g. City Center → Festival, Airport Express');
            $table->string('route_code')->nullable()->comment('Short code: S1, S2, AE');
            $table->string('departure_location');
            $table->string('arrival_location');
            $table->decimal('departure_lat', 10, 7)->nullable();
            $table->decimal('departure_lng', 10, 7)->nullable();
            $table->decimal('arrival_lat', 10, 7)->nullable();
            $table->decimal('arrival_lng', 10, 7)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->integer('capacity')->nullable();
            $table->integer('price_cents')->default(0)->comment('0 = free shuttle');
            $table->string('currency', 3)->default('RON');
            $table->json('schedule')->nullable()->comment('JSON: [{day_id, departures: ["09:00","09:30",...]}]');
            $table->json('operating_days')->nullable()->comment('JSON: day IDs when this shuttle runs');
            $table->string('status')->default('active')->comment('draft|active|suspended|cancelled');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // ── Lost & Found ────────────────────────────────────
        Schema::create('lost_and_found', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete()->comment('Reporter if known');
            $table->string('type')->comment('lost|found');
            $table->string('category')->comment('phone|wallet|keys|clothing|bag|jewelry|documents|electronics|medication|other');
            $table->string('item_description');
            $table->text('detailed_description')->nullable();
            $table->string('color')->nullable();
            $table->string('brand')->nullable();
            $table->string('location_found_or_lost')->nullable();
            $table->dateTime('date_lost_or_found')->nullable();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->string('reporter_phone')->nullable();
            $table->string('storage_location')->nullable()->comment('Where item is stored at info point');
            $table->string('status')->default('open')->comment('open|matched|claimed|returned|unclaimed|donated');
            $table->foreignId('matched_id')->nullable()->comment('Linked lost↔found entry');
            $table->dateTime('claimed_at')->nullable();
            $table->string('claimed_by_name')->nullable();
            $table->string('claimed_by_id_number')->nullable()->comment('ID verification for claim');
            $table->string('photo_url')->nullable();
            $table->text('staff_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['category', 'status']);
        });

        // ── Festival reviews ────────────────────────────────
        Schema::create('festival_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reviewable_type')->comment('Event, Artist, Stage, FestivalPointOfInterest');
            $table->unsignedBigInteger('reviewable_id');
            $table->tinyInteger('overall_rating')->comment('1-5');
            $table->tinyInteger('sound_rating')->nullable()->comment('1-5');
            $table->tinyInteger('organization_rating')->nullable()->comment('1-5');
            $table->tinyInteger('value_rating')->nullable()->comment('1-5');
            $table->tinyInteger('food_rating')->nullable()->comment('1-5');
            $table->tinyInteger('safety_rating')->nullable()->comment('1-5');
            $table->text('comment')->nullable();
            $table->string('status')->default('pending')->comment('pending|approved|rejected|flagged');
            $table->boolean('is_anonymous')->default(false);
            $table->string('moderation_note')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['reviewable_type', 'reviewable_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['customer_id', 'reviewable_type', 'reviewable_id'], 'review_unique_per_customer');
        });

        // ── Medical incidents ───────────────────────────────
        Schema::create('medical_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('festival_day_id')->nullable()->constrained()->nullOnDelete();
            $table->string('incident_number')->unique();
            $table->string('severity')->comment('minor|moderate|serious|critical|fatal');
            $table->string('category')->comment('dehydration|heat_stroke|injury|allergy|intoxication|cardiac|respiratory|wound|fracture|seizure|mental_health|other');
            $table->text('description');
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->dateTime('reported_at');
            $table->dateTime('response_at')->nullable();
            $table->integer('response_time_minutes')->nullable();
            $table->string('patient_name')->nullable();
            $table->string('patient_age_group')->nullable()->comment('minor|18-25|26-35|36-50|50+|unknown');
            $table->string('patient_gender')->nullable();
            $table->string('patient_wristband_uid')->nullable();
            $table->text('treatment_given')->nullable();
            $table->string('outcome')->comment('treated_on_site|first_aid|ambulance_called|hospital_transport|refused_treatment|deceased');
            $table->string('hospital_name')->nullable();
            $table->string('ambulance_unit')->nullable();
            $table->string('attending_medic')->nullable();
            $table->string('status')->default('open')->comment('open|in_progress|resolved|transferred|follow_up');
            $table->dateTime('resolved_at')->nullable();
            $table->text('staff_notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'severity']);
            $table->index(['reported_at']);
            $table->index(['event_id', 'festival_day_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_incidents');
        Schema::dropIfExists('festival_reviews');
        Schema::dropIfExists('lost_and_found');
        Schema::dropIfExists('festival_transport_shuttles');
        Schema::dropIfExists('volunteer_shifts');
        Schema::dropIfExists('festival_volunteers');
        Schema::dropIfExists('wristband_transactions');
    }
};
