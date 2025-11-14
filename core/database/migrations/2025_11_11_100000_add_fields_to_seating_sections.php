<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            // Add tenant_id for multi-tenancy
            $table->foreignId('tenant_id')->after('id')->constrained('tenants')->onDelete('cascade');

            // Add section identification
            $table->string('section_code', 20)->after('name')->nullable();

            // Add section type
            $table->string('section_type', 50)->after('section_code')->default('standard');

            // Add price tier relationship
            $table->foreignId('price_tier_id')->after('section_type')->nullable()->constrained('price_tiers')->onDelete('set null');

            // Add positioning and sizing
            $table->integer('x_position')->after('color_hex')->default(100);
            $table->integer('y_position')->after('x_position')->default(100);
            $table->integer('width')->after('y_position')->default(200);
            $table->integer('height')->after('width')->default(150);
            $table->integer('rotation')->after('height')->default(0);

            // Add metadata as separate column
            $table->json('metadata')->after('meta')->nullable();

            // Add indexes
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['price_tier_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn([
                'tenant_id',
                'section_code',
                'section_type',
                'price_tier_id',
                'x_position',
                'y_position',
                'width',
                'height',
                'rotation',
                'metadata',
            ]);
        });
    }
};
