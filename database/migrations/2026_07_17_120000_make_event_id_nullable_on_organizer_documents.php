<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * organizer_documents.event_id was created via foreignId() (NOT NULL by
 * default), and the later FK-repoint migration never touched nullability.
 * Organizer contracts (document_type = organizer_contract) legitimately have
 * NO event, so every attempt to store one failed with a NOT NULL violation.
 * Make the column nullable. Event-scoped documents (deconturi, etc.) still
 * pass a real event_id, so this is non-breaking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Raw ALTER keeps the existing FK constraint intact — only drops
            // the NOT NULL. (Schema::change() would redefine the column and
            // could disturb the foreign key.)
            DB::statement('ALTER TABLE organizer_documents ALTER COLUMN event_id DROP NOT NULL');
        } else {
            Schema::table('organizer_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Best-effort reverse; only safe when no NULL rows exist. Skipped on
        // pgsql if any organizer_contract rows are present to avoid failing.
        if (DB::getDriverName() === 'pgsql') {
            $hasNulls = DB::table('organizer_documents')->whereNull('event_id')->exists();
            if (!$hasNulls) {
                DB::statement('ALTER TABLE organizer_documents ALTER COLUMN event_id SET NOT NULL');
            }
        } else {
            Schema::table('organizer_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('event_id')->nullable(false)->change();
            });
        }
    }
};
