<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row each time a fiscal document of an event is filed with the city
     * hall (emailed, or confirmed as filed through a third-party solution).
     *
     * Kept apart from the document tables on purpose: regenerating a document
     * hard-deletes its rows, and the filing history must survive that.
     */
    public function up(): void
    {
        Schema::create('event_document_filings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('marketplace_tax_registry_id')->nullable()->constrained('marketplace_tax_registries')->nullOnDelete();

            // cerere_avizare | declaratie_impozite | pv_distrugere
            $table->string('document_type', 40);
            // created_at of the document that was filed; a newer document of
            // the same type means this filing no longer covers it.
            $table->timestamp('document_generated_at')->nullable();
            $table->string('file_name')->nullable();

            // email | third_party
            $table->string('method', 20);
            $table->string('sent_to')->nullable();
            $table->string('third_party_name')->nullable();
            $table->unsignedBigInteger('email_log_id')->nullable();

            $table->foreignId('filed_by_id')->nullable()->constrained('marketplace_admins')->nullOnDelete();
            $table->string('filed_by_name')->nullable();
            $table->timestamp('filed_at');

            $table->timestamp('voided_at')->nullable();
            $table->string('voided_by_name')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_document_filings');
    }
};
