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
        Schema::table('tenants', function (Blueprint $table) {
            // Onboarding fields
            $table->string('locale', 5)->default('ro')->after('settings');
            $table->boolean('vat_payer')->default(true)->after('cui');
            $table->integer('estimated_monthly_tickets')->nullable()->after('billing_cycle_days');
            $table->enum('work_method', ['exclusive', 'mixed', 'reseller'])->default('mixed')->after('estimated_monthly_tickets');

            // Onboarding completion tracking
            $table->boolean('onboarding_completed')->default(false)->after('work_method');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_completed');
            $table->integer('onboarding_step')->default(1)->after('onboarding_completed_at');

            // Contact person (from onboarding Step 1)
            $table->string('contact_first_name')->nullable()->after('company_name');
            $table->string('contact_last_name')->nullable()->after('contact_first_name');
            $table->string('contact_email')->nullable()->after('contact_last_name');
            $table->string('contact_phone')->nullable()->after('contact_email');

            // Indexes
            $table->index('locale');
            $table->index('work_method');
            $table->index('onboarding_completed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropIndex(['work_method']);
            $table->dropIndex(['onboarding_completed']);

            $table->dropColumn([
                'locale',
                'vat_payer',
                'estimated_monthly_tickets',
                'work_method',
                'onboarding_completed',
                'onboarding_completed_at',
                'onboarding_step',
                'contact_first_name',
                'contact_last_name',
                'contact_email',
                'contact_phone',
            ]);
        });
    }
};
