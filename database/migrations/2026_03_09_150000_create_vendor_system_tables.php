<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each table wrapped in hasTable check for safe re-run after partial failure.

        // Festival editions — groups all data per year/edition
        if (!Schema::hasTable('festival_editions')) {
            Schema::create('festival_editions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug')->index();
                $table->year('year');
                $table->unsignedTinyInteger('edition_number')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('status')->default('draft');
                $table->string('currency', 3)->default('RON');
                $table->json('settings')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->index(['tenant_id', 'year']);
            });
        }

        // Vendors — food/drink/merch operators at festivals
        if (!Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug')->index();
                $table->string('email')->index();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('company_name')->nullable();
                $table->string('cui')->nullable();
                $table->string('contact_person')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('status')->default('active');
                $table->string('api_token', 80)->nullable()->unique();
                $table->json('meta')->nullable();
                $table->rememberToken();
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
                $table->unique(['tenant_id', 'email']);
            });
        }

        // Pivot: vendor ↔ edition with commission and location
        if (!Schema::hasTable('vendor_edition')) {
            Schema::create('vendor_edition', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->string('location')->nullable();
                $table->string('location_coordinates')->nullable();
                $table->string('vendor_type')->default('food');
                $table->decimal('commission_rate', 5, 2)->default(0);
                $table->string('commission_mode')->default('percentage');
                $table->unsignedInteger('fixed_commission_cents')->nullable();
                $table->string('status')->default('confirmed');
                $table->json('operating_hours')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'festival_edition_id']);
            });
        }

        // POS devices assigned to vendors
        if (!Schema::hasTable('vendor_pos_devices')) {
            Schema::create('vendor_pos_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->string('device_uid')->index();
                $table->string('name')->nullable();
                $table->string('status')->default('active');
                $table->timestamp('last_seen_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'device_uid']);
            });
        }

        // Product categories (per vendor per edition)
        if (!Schema::hasTable('vendor_product_categories')) {
            Schema::create('vendor_product_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['vendor_id', 'festival_edition_id', 'slug'], 'vpc_vendor_edition_slug_unique');
            });
        }

        // Products (menu items)
        if (!Schema::hasTable('vendor_products')) {
            Schema::create('vendor_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_product_category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents');
                $table->string('currency', 3)->default('RON');
                $table->string('image_url')->nullable();
                $table->boolean('is_available')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('variants')->nullable();
                $table->json('allergens')->nullable();
                $table->json('tags')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['vendor_id', 'festival_edition_id', 'slug'], 'vp_vendor_edition_slug_unique');
                $table->index(['vendor_id', 'is_available']);
            });
        }

        // Sales line items
        if (!Schema::hasTable('vendor_sale_items')) {
            Schema::create('vendor_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vendor_product_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('wristband_transaction_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('vendor_pos_device_id')->nullable()->constrained()->nullOnDelete();
                $table->string('product_name');
                $table->string('category_name')->nullable();
                $table->string('variant_name')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedInteger('unit_price_cents');
                $table->unsignedInteger('total_cents');
                $table->string('currency', 3)->default('RON');
                $table->unsignedInteger('commission_cents')->default(0);
                $table->decimal('commission_rate', 5, 2)->default(0);
                $table->string('operator')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'festival_edition_id', 'created_at'], 'vsi_vendor_edition_date');
                $table->index(['festival_edition_id', 'created_at']);
            });
        }

        // Add festival_edition_id to existing tables
        if (!Schema::hasColumn('wristbands', 'festival_edition_id')) {
            Schema::table('wristbands', function (Blueprint $table) {
                $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('wristband_transactions', 'festival_edition_id')) {
            Schema::table('wristband_transactions', function (Blueprint $table) {
                $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('wristband_transactions', 'vendor_id')) {
            Schema::table('wristband_transactions', function (Blueprint $table) {
                $table->foreignId('vendor_id')->nullable()->after('vendor_location')
                    ->constrained()->nullOnDelete();
                $table->foreignId('vendor_pos_device_id')->nullable()->after('vendor_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('festival_days', 'festival_edition_id')) {
            Schema::table('festival_days', function (Blueprint $table) {
                $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('festival_passes', 'festival_edition_id')) {
            Schema::table('festival_passes', function (Blueprint $table) {
                $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('festival_passes', 'festival_edition_id')) {
            Schema::table('festival_passes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('festival_edition_id');
            });
        }

        if (Schema::hasColumn('festival_days', 'festival_edition_id')) {
            Schema::table('festival_days', function (Blueprint $table) {
                $table->dropConstrainedForeignId('festival_edition_id');
            });
        }

        if (Schema::hasColumn('wristband_transactions', 'vendor_pos_device_id')) {
            Schema::table('wristband_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vendor_pos_device_id');
                $table->dropConstrainedForeignId('vendor_id');
                $table->dropConstrainedForeignId('festival_edition_id');
            });
        }

        if (Schema::hasColumn('wristbands', 'festival_edition_id')) {
            Schema::table('wristbands', function (Blueprint $table) {
                $table->dropConstrainedForeignId('festival_edition_id');
            });
        }

        Schema::dropIfExists('vendor_sale_items');
        Schema::dropIfExists('vendor_products');
        Schema::dropIfExists('vendor_product_categories');
        Schema::dropIfExists('vendor_pos_devices');
        Schema::dropIfExists('vendor_edition');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('festival_editions');
    }
};
