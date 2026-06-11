<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add compound tax support to general_taxes
        Schema::table('general_taxes', function (Blueprint $table) {
            $table->boolean('is_compound')->default(false)->after('priority');
            $table->integer('compound_order')->default(0)->after('is_compound');
        });

        // Add compound tax support to local_taxes
        Schema::table('local_taxes', function (Blueprint $table) {
            $table->boolean('is_compound')->default(false)->after('priority');
            $table->integer('compound_order')->default(0)->after('is_compound');
        });

        // Tax Exemptions table
        if (Schema::hasTable('tax_exemptions')) {
            return;
        }

        Schema::create('tax_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 190);
            $table->enum('exemption_type', ['customer', 'ticket_type', 'event', 'product', 'category']);
            $table->unsignedBigInteger('exemptable_id')->nullable(); // ID of customer, ticket_type, event, etc.
            $table->string('exemptable_type')->nullable(); // Model class
            $table->enum('scope', ['all', 'general', 'local'])->default('all'); // Which taxes to exempt
            $table->decimal('exemption_percent', 5, 2)->default(100); // 100 = full exemption
            $table->text('reason')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['exemptable_type', 'exemptable_id']);
        });

        // Tax Import Logs table
        if (Schema::hasTable('tax_import_logs')) {
            return;
        }

        Schema::create('tax_import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('filename', 255);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('imported_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_import_logs');
        Schema::dropIfExists('tax_exemptions');

        Schema::table('local_taxes', function (Blueprint $table) {
            $table->dropColumn(['is_compound', 'compound_order']);
        });

        Schema::table('general_taxes', function (Blueprint $table) {
            $table->dropColumn(['is_compound', 'compound_order']);
        });
    }
};
