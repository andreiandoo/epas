<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Custom variables table
        Schema::create('contract_custom_variables', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'late_fee_percentage'
            $table->string('key'); // e.g., '{{late_fee_percentage}}'
            $table->string('label'); // e.g., 'Late Fee Percentage'
            $table->text('description')->nullable();
            $table->string('type')->default('text'); // text, number, date, select
            $table->json('options')->nullable(); // For select type
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Tenant custom variable values
        Schema::create('tenant_custom_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('contract_custom_variable_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_custom_variable_id'], 'tenant_variable_unique');
        });

        // Contract amendments table
        Schema::create('contract_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('contract_version_id')->nullable()->constrained()->onDelete('set null');
            $table->string('amendment_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content'); // The amendment content
            $table->string('file_path')->nullable(); // Generated PDF
            $table->string('status')->default('draft'); // draft, sent, signed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_by')->nullable();
            $table->text('signature_data')->nullable();
            $table->string('signature_ip')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_amendments');
        Schema::dropIfExists('tenant_custom_variables');
        Schema::dropIfExists('contract_custom_variables');
    }
};
