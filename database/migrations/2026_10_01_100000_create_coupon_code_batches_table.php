<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loturi de coduri de reducere generate în masă (/coupon-codes-list/bulk).
 *
 * Un lot = N coduri unice, de unică folosință, pe un singur tip de bilet,
 * predate organizatorului ca CSV. Lotul ține setările generării și cine a
 * generat; codurile poartă batch_id ca să poată fi descărcate / dezactivate
 * împreună și ascunse din contul organizatorului.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('coupon_code_batches')) {
            Schema::create('coupon_code_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
                $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->index();
                $table->unsignedBigInteger('event_id')->nullable()->index();
                $table->unsignedBigInteger('ticket_type_id')->nullable();
                $table->uuid('campaign_id')->nullable();
                $table->unsignedInteger('quantity');
                $table->unsignedSmallInteger('code_length');
                $table->string('prefix', 20)->nullable();
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['marketplace_client_id', 'created_at']);
            });
        }

        if (!Schema::hasColumn('coupon_codes', 'batch_id')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->foreignId('batch_id')->nullable()->constrained('coupon_code_batches')->nullOnDelete();
                $table->index('batch_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('coupon_codes', 'batch_id')) {
            Schema::table('coupon_codes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('batch_id');
            });
        }

        Schema::dropIfExists('coupon_code_batches');
    }
};
