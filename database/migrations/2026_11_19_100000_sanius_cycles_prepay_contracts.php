<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanius v2: chirie pe cicluri (pret L-V / weekend, tractari incluse pe ciclu),
 * plata in avans la casierie si contractul semnat pe tableta. Doar coloane si
 * tabele noi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanius_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('sanius_settings', 'rental')) {
                // {cycle_minutes, tows_per_cycle, grace_minutes, weekend_extra_dates[]}
                $table->json('rental')->nullable();
            }
            if (! Schema::hasColumn('sanius_settings', 'contract')) {
                // {required, title, body, version, stations[]}
                $table->json('contract')->nullable();
            }
        });

        Schema::table('sanius_rentals', function (Blueprint $table) {
            if (! Schema::hasColumn('sanius_rentals', 'day_type')) {
                // weekday | weekend
                $table->string('day_type', 8)->nullable();
            }
            if (! Schema::hasColumn('sanius_rentals', 'duration_minutes')) {
                $table->unsignedInteger('duration_minutes')->nullable();
            }
            if (! Schema::hasColumn('sanius_rentals', 'cycles_used')) {
                $table->unsignedSmallInteger('cycles_used')->nullable();
            }
        });

        if (! Schema::hasTable('sanius_prepaids')) {
            Schema::create('sanius_prepaids', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->foreignId('tab_id')->constrained('sanius_tabs')->cascadeOnDelete();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedSmallInteger('sleds');
                $table->unsignedSmallInteger('cycles_per_sled');
                $table->unsignedSmallInteger('sled_cycles');
                // weekday | weekend
                $table->string('day_type', 8);
                $table->unsignedBigInteger('ticket_type_id')->nullable();
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('payment_method', 10)->nullable();
                $table->boolean('simulated')->default(false);
                // paid | void
                $table->string('status', 8)->default('paid');
                $table->unsignedBigInteger('member_id')->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sanius_contracts')) {
            Schema::create('sanius_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->index();
                $table->foreignId('tab_id')->constrained('sanius_tabs')->cascadeOnDelete();
                // awaiting_signature | awaiting_paper | signed | signed_paper | void
                $table->string('status', 20)->default('awaiting_signature');
                $table->string('station', 40)->nullable();
                $table->string('signer_name', 120);
                // ci | passport | other
                $table->string('doc_type', 10)->default('ci');
                $table->string('doc_series', 12)->nullable();
                $table->string('doc_number', 20);
                $table->string('phone', 30)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('title', 160);
                // Textul exact aratat clientului (cu datele completate), ca PDF-ul sa fie identic.
                $table->text('body');
                $table->unsignedInteger('template_version')->default(1);
                $table->string('body_hash', 64);
                $table->string('signature_path')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('signed_ip', 45)->nullable();
                $table->string('signed_device', 160)->nullable();
                $table->unsignedBigInteger('created_by_member_id')->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();

                $table->index(['event_id', 'status', 'station']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sanius_contracts');
        Schema::dropIfExists('sanius_prepaids');
        Schema::table('sanius_rentals', function (Blueprint $table) {
            foreach (['day_type', 'duration_minutes', 'cycles_used'] as $col) {
                if (Schema::hasColumn('sanius_rentals', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('sanius_settings', function (Blueprint $table) {
            foreach (['rental', 'contract'] as $col) {
                if (Schema::hasColumn('sanius_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
