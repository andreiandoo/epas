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
        if (Schema::hasTable('media_library')) {
            return;
        }

        Schema::create('media_library', function (Blueprint $table) {
            $table->id();

            // File information
            $table->string('filename', 500);
            $table->string('original_filename', 500)->nullable();
            $table->string('path', 1000);
            $table->string('disk', 50)->default('public');
            $table->string('mime_type', 100)->nullable();
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size')->default(0); // File size in bytes

            // Image dimensions (for images only)
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Organization
            $table->string('collection', 100)->nullable()->index(); // e.g., 'artists', 'events', 'products'
            $table->string('directory', 500)->nullable(); // Storage directory

            // Associations (polymorphic)
            $table->nullableMorphs('model'); // model_type, model_id

            // Marketplace scoping
            $table->foreignId('marketplace_client_id')->nullable()->constrained()->nullOnDelete();

            // Metadata
            $table->json('metadata')->nullable(); // Additional data like alt text, caption, etc.
            $table->string('alt_text', 500)->nullable();
            $table->string('title', 500)->nullable();

            // User tracking
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // Timestamps for filtering
            $table->timestamp('file_created_at')->nullable(); // Original file creation date
            $table->timestamps();

            // Indexes for common queries
            $table->index(['collection', 'created_at']);
            $table->index(['marketplace_client_id', 'created_at']);
            $table->index('mime_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_library');
    }
};
