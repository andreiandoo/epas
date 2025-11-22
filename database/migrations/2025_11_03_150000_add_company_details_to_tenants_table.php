<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Company details
            $table->string('company_name')->nullable()->after('name');
            $table->string('cui', 50)->nullable()->after('company_name');
            $table->string('reg_com')->nullable()->after('cui');

            // Banking details
            $table->string('bank_account')->nullable()->after('reg_com');
            $table->string('bank_name')->nullable()->after('bank_account');

            // Address details
            $table->text('address')->nullable()->after('bank_name');
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('country', 2)->nullable()->after('state');

            // Billing details
            $table->timestamp('billing_starts_at')->nullable()->after('due_at');
            $table->integer('billing_cycle_days')->default(30)->after('billing_starts_at');

            // Indexes
            $table->index('cui');
            $table->index('country');
            $table->index('billing_starts_at');
        });

        // Set billing_starts_at to created_at for existing tenants
        DB::statement('UPDATE tenants SET billing_starts_at = created_at WHERE billing_starts_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['cui']);
            $table->dropIndex(['country']);
            $table->dropIndex(['billing_starts_at']);

            $table->dropColumn([
                'company_name',
                'cui',
                'reg_com',
                'bank_account',
                'bank_name',
                'address',
                'city',
                'state',
                'country',
                'billing_starts_at',
                'billing_cycle_days',
            ]);
        });
    }
};
