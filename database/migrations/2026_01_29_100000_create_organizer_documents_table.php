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
        Schema::create('organizer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('marketplace_events')->cascadeOnDelete();
            $table->foreignId('tax_template_id')->nullable()->constrained('marketplace_tax_templates')->nullOnDelete();

            $table->string('title');
            $table->string('document_type'); // cerere_avizare, declaratie_impozite, etc.
            $table->string('file_path')->nullable(); // Path to generated PDF
            $table->string('file_name')->nullable(); // Original filename
            $table->unsignedBigInteger('file_size')->nullable(); // File size in bytes

            $table->json('document_data')->nullable(); // Snapshot of data used to generate the document
            $table->text('html_content')->nullable(); // Generated HTML content (before PDF conversion)

            $table->timestamp('issued_at')->nullable(); // When the document was issued
            $table->timestamps();

            // Indexes
            $table->index(['marketplace_client_id', 'document_type']);
            $table->index(['marketplace_organizer_id', 'event_id']);
            $table->index('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizer_documents');
    }
};
