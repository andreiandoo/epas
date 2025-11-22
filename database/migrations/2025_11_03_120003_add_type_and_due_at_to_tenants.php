<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Add type field: single, small, medium, large, premium
            $table->string('type', 32)->nullable()->after('plan')->comment('Client type: single|small|medium|large|premium');

            // Add due_at field for next billing date
            $table->timestamp('due_at')->nullable()->after('type')->comment('Next billing date');

            // Add index for due_at for efficient billing queries
            $table->index('due_at');
            $table->index('type');
        });

        // Note: status field already exists and supports: active|suspended|closed
        // To support additional values (pending|cancelled|terminated), no schema change needed
        // as it's already string(32). Values will be handled at application level.
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['due_at']);
            $table->dropIndex(['type']);
            $table->dropColumn(['type', 'due_at']);
        });
    }
};
