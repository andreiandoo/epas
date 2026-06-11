<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Vendor employees — staff that operate POS devices ──
        if (!Schema::hasTable('vendor_employees')) {
            Schema::create('vendor_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('pin', 10);                         // 4-6 digit PIN for quick POS auth
                $table->string('role')->default('operator');       // admin|operator|viewer
                $table->string('status')->default('active');       // active|inactive|suspended
                $table->json('permissions')->nullable();           // granular: ["sell","refund","view_reports","manage_products"]
                $table->string('avatar_url')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'pin']);              // PIN unique per vendor
                $table->index(['vendor_id', 'status']);
            });
        }

        // ── Vendor shifts — track who works when on which device ──
        if (!Schema::hasTable('vendor_shifts')) {
            Schema::create('vendor_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_pos_device_id')->nullable()->constrained()->nullOnDelete();
                $table->dateTime('started_at');
                $table->dateTime('ended_at')->nullable();
                $table->string('status')->default('active');       // active|completed|abandoned
                $table->unsignedInteger('sales_count')->default(0);
                $table->unsignedInteger('sales_total_cents')->default(0);
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'festival_edition_id', 'started_at'], 'vs_vendor_edition_start');
                $table->index(['vendor_employee_id', 'status']);
            });
        }

        // ── Add parent_id to product categories for subcategories ──
        if (!Schema::hasColumn('vendor_product_categories', 'parent_id')) {
            Schema::table('vendor_product_categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('festival_edition_id')
                    ->constrained('vendor_product_categories')->nullOnDelete();
                $table->string('icon')->nullable()->after('slug');
                $table->string('color')->nullable()->after('icon');
            });
        }

        // ── Add employee tracking to sale items ──
        if (!Schema::hasColumn('vendor_sale_items', 'vendor_employee_id')) {
            Schema::table('vendor_sale_items', function (Blueprint $table) {
                $table->foreignId('vendor_employee_id')->nullable()->after('vendor_pos_device_id')
                    ->constrained()->nullOnDelete();
                $table->foreignId('vendor_shift_id')->nullable()->after('vendor_employee_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_shift_id');
            $table->dropConstrainedForeignId('vendor_employee_id');
        });

        Schema::table('vendor_product_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['icon', 'color']);
        });

        Schema::dropIfExists('vendor_shifts');
        Schema::dropIfExists('vendor_employees');
    }
};
