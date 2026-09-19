<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module (bilete.online):
 *
 *   activity_addons         extras on a product (life jacket, photo pack…):
 *                           `included_qty` free per unit bought, then up to
 *                           `max_per_unit` paid per unit
 *   activity_package_items  what a package holds: component product (+ variant)
 *                           x quantity, with the share of the package price used
 *                           for reporting / invoicing
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_addons')) {
            Schema::create('activity_addons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $table->jsonb('name');
                $table->integer('price_cents')->default(0);
                $table->unsignedSmallInteger('included_qty')->default(0);
                $table->unsignedSmallInteger('max_per_unit')->default(5);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['activity_id', 'is_active', 'sort_order'], 'act_addons_listing_idx');
            });
        }

        if (!Schema::hasTable('activity_package_items')) {
            Schema::create('activity_package_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_activity_id')->constrained('activities')->cascadeOnDelete();
                $table->foreignId('component_activity_id')->constrained('activities')->cascadeOnDelete();
                $table->foreignId('component_variant_id')->nullable()
                    ->constrained('activity_variants')->nullOnDelete();
                $table->unsignedSmallInteger('quantity')->default(1);
                $table->integer('allocated_price_cents')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['package_activity_id', 'sort_order'], 'act_package_items_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_package_items');
        Schema::dropIfExists('activity_addons');
    }
};
