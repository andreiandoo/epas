<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_external_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained('festival_editions')->cascadeOnDelete();
            $table->string('import_batch_id', 36)->nullable()->index();
            $table->string('source_name')->nullable();
            $table->string('barcode');
            $table->string('attendee_first_name')->nullable();
            $table->string('attendee_last_name')->nullable();
            $table->string('attendee_email')->nullable();
            $table->string('ticket_type_name')->nullable();
            $table->string('original_id')->nullable();
            $table->string('status', 32)->default('valid');
            $table->timestamp('checked_in_at')->nullable();
            $table->string('checked_in_by')->nullable();
            $table->string('checked_in_gate')->nullable();
            $table->json('day_checkins')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['festival_edition_id', 'barcode']);
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_external_tickets');
    }
};
