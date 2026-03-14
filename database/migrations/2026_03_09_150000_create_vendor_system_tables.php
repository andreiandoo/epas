<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Festival editions — groups all data per year/edition
        Schema::create('festival_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                       // "Electric Castle 2026"
            $table->string('slug')->index();
            $table->year('year');
            $table->unsignedTinyInteger('edition_number')->nullable(); // 9th edition
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('draft');   // draft|announced|active|completed|cancelled
            $table->string('currency', 3)->default('RON');
            $table->json('settings')->nullable();         // edition-level overrides
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'year']);
        });

        // Vendors — food/drink/merch operators at festivals
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('email')->index();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('cui')->nullable();            // fiscal code
            $table->string('contact_person')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('status')->default('active');   // active|suspended|inactive
            $table->string('api_token', 80)->nullable()->unique();
            $table->json('meta')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'email']);
        });

        // Pivot: vendor ↔ edition with commission and location
        Schema::create('vendor_edition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('location')->nullable();        // "Zone B, Stand 14"
            $table->string('location_coordinates')->nullable(); // lat,lng for map
            $table->string('vendor_type')->default('food'); // food|drink|merch|services|other
            $table->unsignedDecimal('commission_rate', 5, 2)->default(0); // % organizer takes
            $table->string('commission_mode')->default('percentage'); // percentage|fixed_per_transaction
            $table->unsignedInteger('fixed_commission_cents')->nullable();
            $table->string('status')->default('confirmed'); // pending|confirmed|cancelled
            $table->json('operating_hours')->nullable();    // per-day schedule
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'festival_edition_id']);
        });

        // POS devices assigned to vendors
        Schema::create('vendor_pos_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('device_uid')->index();         // unique hardware ID
            $table->string('name')->nullable();            // "POS #3 Bar"
            $table->string('status')->default('active');   // active|disabled|lost
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'device_uid']);
        });

        // Product categories (per vendor per edition)
        Schema::create('vendor_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('name');                        // "Burgers", "Cocktails"
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'festival_edition_id', 'slug'], 'vpc_vendor_edition_slug_unique');
        });

        // Products (menu items)
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
            $table->json('variants')->nullable();          // [{name:"Large",price_cents:1800}]
            $table->json('allergens')->nullable();          // ["gluten","lactose"]
            $table->json('tags')->nullable();               // ["vegan","spicy"]
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'festival_edition_id', 'slug'], 'vp_vendor_edition_slug_unique');
            $table->index(['vendor_id', 'is_available']);
        });

        // Sales line items — each product sold via wristband charge
        Schema::create('vendor_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wristband_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_pos_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');                 // denormalized for history
            $table->string('category_name')->nullable();
            $table->string('variant_name')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('RON');
            $table->unsignedInteger('commission_cents')->default(0); // organizer cut
            $table->unsignedDecimal('commission_rate', 5, 2)->default(0);
            $table->string('operator')->nullable();         // staff name
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'festival_edition_id', 'created_at'], 'vsi_vendor_edition_date');
            $table->index(['festival_edition_id', 'created_at']);
        });

        // Add festival_edition_id to existing tables
        Schema::table('wristbands', function (Blueprint $table) {
            $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('wristband_transactions', function (Blueprint $table) {
            $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('vendor_location')
                ->constrained()->nullOnDelete();
            $table->foreignId('vendor_pos_device_id')->nullable()->after('vendor_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('festival_days', function (Blueprint $table) {
            $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('festival_passes', function (Blueprint $table) {
            $table->foreignId('festival_edition_id')->nullable()->after('tenant_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('festival_passes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('festival_edition_id');
        });

        Schema::table('festival_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('festival_edition_id');
        });

        Schema::table('wristband_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_pos_device_id');
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropConstrainedForeignId('festival_edition_id');
        });

        Schema::table('wristbands', function (Blueprint $table) {
            $table->dropConstrainedForeignId('festival_edition_id');
        });

        Schema::dropIfExists('vendor_sale_items');
        Schema::dropIfExists('vendor_products');
        Schema::dropIfExists('vendor_product_categories');
        Schema::dropIfExists('vendor_pos_devices');
        Schema::dropIfExists('vendor_edition');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('festival_editions');
    }
};
