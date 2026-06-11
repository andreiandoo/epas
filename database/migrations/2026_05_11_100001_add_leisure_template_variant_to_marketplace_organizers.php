<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_organizers', 'leisure_template_variant')) {
                $table->string('leisure_template_variant', 32)->nullable()->after('organizer_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_organizers', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_organizers', 'leisure_template_variant')) {
                $table->dropColumn('leisure_template_variant');
            }
        });
    }
};
