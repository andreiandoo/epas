<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The number the city hall gives a filed document. Lives on the filing,
     * so a document filed again gets its own number.
     */
    public function up(): void
    {
        Schema::table('event_document_filings', function (Blueprint $table) {
            $table->string('registration_number', 100)->nullable()->after('email_log_id');
            $table->timestamp('registration_number_at')->nullable()->after('registration_number');
            $table->string('registration_number_by_name')->nullable()->after('registration_number_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_document_filings', function (Blueprint $table) {
            $table->dropColumn(['registration_number', 'registration_number_at', 'registration_number_by_name']);
        });
    }
};
