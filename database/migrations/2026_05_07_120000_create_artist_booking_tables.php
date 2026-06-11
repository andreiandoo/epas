<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Listing-ul public al artistului — cachet, tipuri eveniment, condiții standard
        Schema::create('artist_booking_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->unique()->constrained('artists')->cascadeOnDelete();
            $table->integer('min_fee_ron')->nullable();
            $table->integer('max_fee_ron')->nullable();
            $table->boolean('show_fee_publicly')->default(true);
            $table->json('event_types')->nullable();
            $table->json('accepted_genres')->nullable();
            $table->integer('standard_set_length_min')->default(60);
            $table->integer('standard_min_audience')->nullable();
            $table->integer('standard_max_audience')->nullable();
            $table->boolean('requires_soundcheck')->default(true);
            $table->integer('soundcheck_min_minutes')->default(60);
            $table->boolean('requires_backline')->default(false);
            $table->boolean('requires_catering')->default(true);
            $table->boolean('requires_accommodation')->default(false);
            $table->boolean('requires_transport')->default(false);
            $table->json('description')->nullable(); // translatable
            $table->integer('max_distance_km')->nullable();
            $table->integer('response_target_hours')->default(24);
            $table->string('status', 20)->default('paused'); // active | paused
            $table->timestamps();
            $table->index('status');
        });

        // Cererile primite de artist
        Schema::create('artist_booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->string('guest_name', 120);
            $table->string('guest_email', 180);
            $table->string('guest_phone', 40)->nullable();
            $table->string('guest_company', 180)->nullable();
            $table->string('guest_company_type', 30)->nullable(); // organizator | agentie | venue | persoana

            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->string('event_venue_name', 180)->nullable();
            $table->string('event_city', 120);
            $table->string('event_country', 60)->default('RO');
            $table->string('event_type', 40); // concert | festival | private | corporate | wedding | club
            $table->integer('audience_size')->nullable();

            $table->integer('proposed_fee_ron');
            $table->integer('proposed_set_length_min')->default(60);
            $table->json('conditions')->nullable(); // ['soundcheck', 'backline', 'catering', 'accommodation', 'transport']
            $table->text('initial_message');

            $table->string('status', 20)->default('new'); // new | viewed | negotiating | accepted | rejected | expired
            $table->string('guest_token', 64)->unique();

            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_artist_response_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->json('final_terms')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
            $table->index(['artist_id', 'status']);
            $table->index('event_date');
        });

        // Mesaje thread negociere
        Schema::create('artist_booking_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->constrained('artist_booking_requests')->cascadeOnDelete();
            $table->string('sender_type', 10); // artist | guest
            $table->string('type', 20)->default('message'); // message | counter | accept | reject
            $table->text('body')->nullable();
            $table->json('counter_terms')->nullable(); // { fee, set_length_min, date, conditions[] }
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('booking_request_id');
        });

        // Calendar disponibilitate — zile blocate
        Schema::create('artist_booking_unavailable_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $table->date('date_start');
            $table->date('date_end');
            $table->string('reason', 120)->nullable();
            $table->string('color', 9)->default('#94A3B8');
            $table->timestamps();
            $table->index(['artist_id', 'date_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_booking_messages');
        Schema::dropIfExists('artist_booking_requests');
        Schema::dropIfExists('artist_booking_unavailable_dates');
        Schema::dropIfExists('artist_booking_listings');
    }
};
