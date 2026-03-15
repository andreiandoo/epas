<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('web_templates')) {
            Schema::create('web_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('category'); // WebTemplateCategory enum
                $table->text('description')->nullable();
                $table->string('thumbnail')->nullable();
                $table->string('preview_image')->nullable();
                $table->string('html_template_path')->nullable();
                $table->json('tech_stack')->nullable();
                $table->json('compatible_microservices')->nullable();
                $table->json('default_demo_data')->nullable();
                $table->json('customizable_fields')->nullable();
                $table->json('color_scheme')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);
                $table->string('version')->default('1.0.0');
                $table->timestamps();

                $table->index('category');
                $table->index('is_active');
                $table->index('is_featured');
            });
        }

        if (!Schema::hasTable('web_template_customizations')) {
            Schema::create('web_template_customizations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('web_template_id')->constrained('web_templates')->cascadeOnDelete();
                $table->string('unique_token', 12)->unique();
                $table->string('label')->nullable();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->json('customization_data')->nullable();
                $table->json('demo_data_overrides')->nullable();
                $table->string('status')->default('draft'); // draft, active, expired
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('viewed_count')->default(0);
                $table->timestamp('last_viewed_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('web_template_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_template_customizations');
        Schema::dropIfExists('web_templates');
    }
};
