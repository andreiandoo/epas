<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tax Audit Log - tracks all changes to tax configurations
        if (Schema::hasTable('tax_audit_logs')) {
            return;
        }

        Schema::create('tax_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('auditable_type'); // GeneralTax or LocalTax
            $table->unsignedBigInteger('auditable_id');
            $table->string('event'); // created, updated, deleted, restored
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('reason')->nullable(); // Optional reason for change
            $table->timestamps();

            $table->index(['tenant_id', 'auditable_type', 'auditable_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        // Tax Collection Records - for analytics
        if (Schema::hasTable('tax_collection_records')) {
            return;
        }

        Schema::create('tax_collection_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('taxable_type'); // Order, ShopOrder, etc.
            $table->unsignedBigInteger('taxable_id');
            $table->string('tax_type'); // general or local
            $table->unsignedBigInteger('tax_id');
            $table->string('tax_name');
            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('rate', 8, 4);
            $table->string('rate_type', 20); // percent or fixed
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_compound')->default(false);
            $table->boolean('exemption_applied')->default(false);
            $table->string('exemption_name')->nullable();
            $table->decimal('original_tax_amount', 12, 2)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedBigInteger('event_type_id')->nullable();
            $table->date('collection_date');
            $table->timestamps();

            $table->index(['tenant_id', 'collection_date']);
            $table->index(['tenant_id', 'tax_type', 'tax_id']);
            $table->index(['taxable_type', 'taxable_id']);
        });

        // Tax Analytics Cache - pre-aggregated data for faster queries
        if (Schema::hasTable('tax_analytics_cache')) {
            return;
        }

        Schema::create('tax_analytics_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('period_type'); // daily, weekly, monthly, yearly
            $table->date('period_start');
            $table->date('period_end');
            $table->string('tax_type')->nullable(); // null = all, general, local
            $table->unsignedBigInteger('tax_id')->nullable(); // null = all taxes of type
            $table->integer('transaction_count')->default(0);
            $table->decimal('total_taxable_amount', 14, 2)->default(0);
            $table->decimal('total_tax_collected', 14, 2)->default(0);
            $table->decimal('average_effective_rate', 8, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->json('breakdown')->nullable(); // Detailed breakdown by tax
            $table->timestamps();

            $table->unique(['tenant_id', 'period_type', 'period_start', 'tax_type', 'tax_id'], 'tax_analytics_unique');
            $table->index(['tenant_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_analytics_cache');
        Schema::dropIfExists('tax_collection_records');
        Schema::dropIfExists('tax_audit_logs');
    }
};
