<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
            $table->string('import_batch_id', 36)->nullable();
            $table->string('barcode', 255);
            $table->string('attendee_first_name', 255)->nullable();
            $table->string('attendee_last_name', 255)->nullable();
            $table->string('attendee_email', 255)->nullable();
            $table->string('ticket_type_name', 255)->nullable();
            $table->string('original_id', 255)->nullable();
            $table->string('status', 32)->default('valid');
            $table->timestamp('checked_in_at')->nullable();
            $table->string('checked_in_by', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'barcode']);
            $table->index('barcode');
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_tickets');
    }
};
