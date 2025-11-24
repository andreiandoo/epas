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
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('content'); // WYSIWYG HTML content
            $table->string('work_method')->nullable(); // exclusive, mixed, reseller - or null for default
            $table->string('plan')->nullable(); // 1percent, 2percent, 3percent - or null for default
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('available_variables')->nullable(); // List of variables available in this template
            $table->timestamps();
        });

        // Add contract-related fields to tenants table
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('contract_generated_at')->nullable()->after('contract_file');
            $table->timestamp('contract_sent_at')->nullable()->after('contract_generated_at');
            $table->foreignId('contract_template_id')->nullable()->after('contract_sent_at')->constrained('contract_templates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['contract_template_id']);
            $table->dropColumn(['contract_generated_at', 'contract_sent_at', 'contract_template_id']);
        });

        Schema::dropIfExists('contract_templates');
    }
};
