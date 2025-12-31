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
        // Add locale support to contract templates
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('plan');
        });

        // Add contract status workflow and signature fields to tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('contract_status')->default('draft')->after('contract_template_id');
            $table->timestamp('contract_viewed_at')->nullable()->after('contract_sent_at');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_viewed_at');
            $table->string('contract_signature_ip')->nullable()->after('contract_signed_at');
            $table->text('contract_signature_data')->nullable()->after('contract_signature_ip');
            $table->date('contract_renewal_date')->nullable()->after('contract_signature_data');
            $table->boolean('contract_auto_renew')->default(false)->after('contract_renewal_date');
        });

        // Create contract versions table for versioning
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('contract_template_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('version_number');
            $table->string('contract_number');
            $table->string('file_path');
            $table->string('status')->default('generated');
            $table->timestamp('generated_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_ip')->nullable();
            $table->text('signature_data')->nullable();
            $table->json('tenant_data_snapshot')->nullable(); // Store tenant data at time of generation
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_versions');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'contract_status',
                'contract_viewed_at',
                'contract_signed_at',
                'contract_signature_ip',
                'contract_signature_data',
                'contract_renewal_date',
                'contract_auto_renew',
            ]);
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
