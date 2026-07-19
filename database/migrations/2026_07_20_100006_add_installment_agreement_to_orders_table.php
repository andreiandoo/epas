<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link an order to its flexible-payment agreement (if any). Null for
 * normal full-payment orders → fully backward compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'installment_agreement_id')) {
                $table->foreignId('installment_agreement_id')->nullable()->after('payment_status');
                $table->index('installment_agreement_id');
            }
            if (! Schema::hasColumn('orders', 'payment_method_kind')) {
                // full | installments | bnpl | delegated_pay — for reporting/admin display.
                $table->string('payment_method_kind')->default('full')->after('installment_agreement_id');
            }
            if (! Schema::hasColumn('orders', 'outstanding_cents')) {
                // Remaining balance to be collected (0 for full-payment orders).
                $table->unsignedBigInteger('outstanding_cents')->default(0)->after('payment_method_kind');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['installment_agreement_id', 'payment_method_kind', 'outstanding_cents'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
