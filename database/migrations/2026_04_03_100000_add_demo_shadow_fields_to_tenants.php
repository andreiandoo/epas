<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_demo_shadow')->default(false)->after('status');
            $table->foreignId('demo_shadow_id')->nullable()->after('is_demo_shadow')
                ->constrained('tenants')->nullOnDelete();
            $table->string('demo_dataset')->nullable()->after('demo_shadow_id');
            $table->foreignId('demo_parent_id')->nullable()->after('demo_dataset')
                ->constrained('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('demo_shadow_id');
            $table->dropConstrainedForeignId('demo_parent_id');
            $table->dropColumn(['is_demo_shadow', 'demo_dataset']);
        });
    }
};
