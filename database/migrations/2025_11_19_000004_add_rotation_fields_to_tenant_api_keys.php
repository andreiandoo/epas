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
        Schema::table('tenant_api_keys', function (Blueprint $table) {
            $table->timestamp('deprecated_at')->nullable()->after('expires_at');
            $table->unsignedBigInteger('rotated_from_id')->nullable()->after('deprecated_at');
            $table->unsignedBigInteger('replacement_key_id')->nullable()->after('rotated_from_id');

            // Add indexes
            $table->index('rotated_from_id');
            $table->index('replacement_key_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_api_keys', function (Blueprint $table) {
            $table->dropColumn(['deprecated_at', 'rotated_from_id', 'replacement_key_id']);
        });
    }
};
