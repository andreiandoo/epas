<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        if (! Schema::hasColumn('tickets', 'checked_in_via')) {
            Schema::table('tickets', function (Blueprint $table) {
                // Source of the check-in. Values we currently emit:
                //   - staff_app          (mobile scanner — Festival/CheckInController)
                //   - activities_app     (activities scanner — Organizer/ActivityCheckInController)
                //   - manual             (admin/organizer flipped status by hand in Filament)
                // NULL = pre-existing records from before this column existed,
                // or third-party flows we haven't tagged yet.
                $table->string('checked_in_via', 32)->nullable()->after('checked_in_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'checked_in_via')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('checked_in_via');
            });
        }
    }
};
