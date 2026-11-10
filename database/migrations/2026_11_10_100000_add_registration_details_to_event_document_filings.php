<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The city hall's registration of a filed document: the date next to the
 * number, and the registered copy (the PDF completed with both, or the one
 * the city hall sent back).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_document_filings', function (Blueprint $table) {
            if (! Schema::hasColumn('event_document_filings', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('registration_number');
            }
            if (! Schema::hasColumn('event_document_filings', 'registered_file_path')) {
                $table->string('registered_file_path')->nullable();
                $table->string('registered_file_name')->nullable();
                // completed | uploaded
                $table->string('registered_source', 20)->nullable();
                $table->timestamp('registered_at')->nullable();
                $table->string('registered_by_name')->nullable();
                // Copy shown in the organizer's account.
                $table->unsignedBigInteger('registered_organizer_document_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_document_filings', function (Blueprint $table) {
            foreach (['registration_date', 'registered_file_path', 'registered_file_name', 'registered_source', 'registered_at', 'registered_by_name', 'registered_organizer_document_id'] as $column) {
                if (Schema::hasColumn('event_document_filings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
