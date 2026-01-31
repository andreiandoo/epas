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
        if (Schema::hasTable('email_templates')) {
            return;
        }

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Human-readable template name');
            $table->string('slug')->unique()->comment('Unique identifier for template');
            $table->string('subject')->comment('Email subject with variable placeholders');
            $table->text('body')->comment('Email body HTML with variable placeholders');
            $table->string('event_trigger')->nullable()->comment('Platform action that triggers this email');
            $table->text('description')->nullable()->comment('Description of when this template is used');
            $table->json('available_variables')->nullable()->comment('List of available variables for this template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('event_trigger');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
