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
        if (Schema::hasTable('ticket_templates')) {
            return;
        }

        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Template metadata
            $table->string('name')->comment('Template name for identification');
            $table->text('description')->nullable()->comment('Template description');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');

            // Template JSON schema
            // Contains: meta, assets, layers, variables
            $table->json('template_data')->comment('Complete template definition');

            // Preview image
            $table->string('preview_image')->nullable()->comment('Path to preview PNG @2x');

            // Version control
            $table->integer('version')->default(1)->comment('Template version number');
            $table->foreignId('parent_id')->nullable()->constrained('ticket_templates')->onDelete('set null')
                ->comment('Reference to previous version');

            // Usage tracking
            $table->boolean('is_default')->default(false)->comment('Is this the default template');
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'is_default']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_templates');
    }
};
