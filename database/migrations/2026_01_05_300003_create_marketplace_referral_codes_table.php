<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');

            $table->string('code', 20)->unique(); // e.g., REF-ABC123
            $table->integer('clicks')->default(0); // Total link clicks
            $table->integer('signups')->default(0); // Registrations from this code
            $table->integer('conversions')->default(0); // Successful purchases
            $table->decimal('total_value', 12, 2)->default(0); // Total order value from referrals
            $table->integer('points_earned')->default(0); // Total points earned
            $table->integer('pending_points')->default(0); // Points pending (awaiting qualification)

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['marketplace_client_id', 'code'], 'mrc_client_code_idx');
            $table->unique(['marketplace_client_id', 'marketplace_customer_id'], 'mrc_client_customer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_referral_codes');
    }
};
