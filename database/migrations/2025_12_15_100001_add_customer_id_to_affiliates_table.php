<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            // Link affiliate to customer account (optional - for self-service affiliates)
            $table->foreignId('customer_id')->nullable()->after('tenant_id')
                ->constrained('customers')->nullOnDelete();

            // Additional fields for self-service affiliates
            $table->string('payment_method')->nullable()->after('meta'); // bank_transfer, paypal, etc.
            $table->json('payment_details')->nullable()->after('payment_method'); // Bank details, PayPal email, etc.
            $table->decimal('pending_balance', 12, 2)->default(0)->after('payment_details');
            $table->decimal('available_balance', 12, 2)->default(0)->after('pending_balance');
            $table->decimal('total_withdrawn', 12, 2)->default(0)->after('available_balance');
            $table->timestamp('last_withdrawal_at')->nullable()->after('total_withdrawn');

            // Index for customer lookup
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['tenant_id', 'customer_id']);
            $table->dropColumn([
                'customer_id',
                'payment_method',
                'payment_details',
                'pending_balance',
                'available_balance',
                'total_withdrawn',
                'last_withdrawal_at',
            ]);
        });
    }
};
