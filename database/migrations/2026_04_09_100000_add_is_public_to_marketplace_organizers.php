<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('status');
            }
            if (!Schema::hasColumn('marketplace_organizers', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'is_featured']);
        });
    }
};
