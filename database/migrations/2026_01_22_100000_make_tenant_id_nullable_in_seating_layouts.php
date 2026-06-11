<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make tenant_id nullable to allow marketplace-only layouts
     * that don't belong to a tenant.
     */
    public function up(): void
    {
        Schema::table('seating_layouts', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('seating_layouts', function (Blueprint $table) {
            // Make tenant_id nullable
            $table->foreignId('tenant_id')
                ->nullable()
                ->change();

            // Re-add the foreign key with cascade on delete
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This assumes all records have a tenant_id set
        // You may need to handle existing NULL values before reverting
        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('seating_layouts', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable(false)
                ->change();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }
};
