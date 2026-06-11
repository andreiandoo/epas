<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Booking requests for management agencies: promoter → agency workflow
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agency_artist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            // Requester info (promoter / organizer)
            $table->string('requester_name');
            $table->string('requester_email');
            $table->string('requester_phone')->nullable();
            $table->string('requester_organization')->nullable();
            $table->string('requester_country', 2)->nullable();

            // Event details
            $table->string('event_name');
            $table->date('event_date')->nullable();
            $table->date('event_date_alt')->nullable()->comment('Alternative date');
            $table->string('event_venue')->nullable();
            $table->string('event_city')->nullable();
            $table->string('event_country', 2)->nullable();
            $table->integer('expected_capacity')->nullable();
            $table->string('event_type', 64)->nullable()->comment('concert|festival|private|corporate|gala|other');

            // Financial
            $table->integer('offered_fee_cents')->nullable();
            $table->string('fee_currency', 3)->default('EUR');
            $table->boolean('fee_includes_travel')->default(false);
            $table->boolean('fee_includes_accommodation')->default(false);
            $table->text('financial_notes')->nullable();

            // Technical
            $table->integer('set_duration_minutes')->nullable();
            $table->boolean('backline_provided')->default(false);
            $table->boolean('sound_engineer_provided')->default(false);
            $table->text('technical_notes')->nullable();

            // Workflow
            $table->string('status', 32)->default('new')
                ->comment('new|reviewing|offer_sent|negotiating|confirmed|contract_sent|contracted|declined|cancelled');
            $table->string('priority', 16)->default('normal')
                ->comment('low|normal|high|urgent');
            $table->string('decline_reason')->nullable();

            // Offer from agency
            $table->integer('counter_fee_cents')->nullable();
            $table->text('offer_notes')->nullable();
            $table->timestamp('offer_sent_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            $table->string('source', 32)->default('manual')
                ->comment('manual|website_form|email|phone');

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['artist_id']);
            $table->index(['event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
