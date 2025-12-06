<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Invoice type: proforma or fiscal
            $table->enum('type', ['proforma', 'fiscal'])->default('proforma')->after('number');

            // Stripe payment fields
            $table->string('stripe_payment_link_id')->nullable()->after('status');
            $table->string('stripe_payment_link_url', 512)->nullable()->after('stripe_payment_link_id');
            $table->string('stripe_checkout_session_id')->nullable()->after('stripe_payment_link_url');

            // Payment tracking
            $table->timestamp('paid_at')->nullable()->after('stripe_checkout_session_id');
            $table->string('payment_method')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'stripe_payment_link_id',
                'stripe_payment_link_url',
                'stripe_checkout_session_id',
                'paid_at',
                'payment_method',
            ]);
        });
    }
};
