<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documente fiscale pentru evenimentele unui TENANT (cerere de avizare, declarație de impozit pe
 * spectacole, PV de distrugere a biletelor).
 *
 * Tabelele existente (marketplace_tax_templates, event_generated_documents, organizer_documents) cer
 * un marketplace client și un organizator, pe care un eveniment de tenant nu le are. De aceea tenantul
 * își ține propriile șabloane și propriile documente generate în două tabele noi. Nimic existent nu e modificat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_tax_templates')) {
            Schema::create('tenant_tax_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('type', 60);                       // cerere_avizare | declaratie_impozite | pv_distrugere
                $table->string('name');
                $table->longText('html_content')->nullable();
                $table->longText('html_content_page_2')->nullable();
                $table->string('page_orientation', 20)->default('portrait');
                $table->json('general_tax_ids')->nullable();      // taxele generale folosite la timbre (ca la șablonul sursă)
                $table->unsignedBigInteger('source_template_id')->nullable();   // șablonul de marketplace din care a fost copiat
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'type']);
            });
        }

        if (! Schema::hasTable('tenant_event_documents')) {
            Schema::create('tenant_event_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
                $table->unsignedBigInteger('tenant_tax_template_id')->nullable();
                $table->string('type', 60);
                $table->string('filename');
                $table->string('file_path');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->unsignedBigInteger('generated_by_id')->nullable();
                $table->string('generated_by_name')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_event_documents');
        Schema::dropIfExists('tenant_tax_templates');
    }
};
