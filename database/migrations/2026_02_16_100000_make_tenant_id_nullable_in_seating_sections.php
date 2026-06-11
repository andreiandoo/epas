<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make tenant_id nullable to allow marketplace-only sections
     * that inherit null tenant_id from their parent layout.
     */
    public function up(): void
    {
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('seating_sections', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->change();

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
        Schema::table('seating_sections', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('seating_sections', function (Blueprint $table) {
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
