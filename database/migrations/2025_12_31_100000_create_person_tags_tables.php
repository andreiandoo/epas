<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tag definitions
        Schema::create('person_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('category', 50)->nullable(); // behavior, demographic, preference, lifecycle, custom
            $table->string('color', 20)->default('#6B7280');
            $table->string('icon', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // System tags can't be deleted
            $table->boolean('is_auto')->default(false); // Has auto-tagging rule
            $table->integer('priority')->default(0); // For ordering
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'category']);
        });

        // Person-tag assignments
        Schema::create('person_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('person_tags')->cascadeOnDelete();
            $table->string('source', 30)->default('manual'); // manual, auto_rule, import, api, segment
            $table->unsignedBigInteger('source_id')->nullable(); // ID of rule/segment/import that assigned
            $table->float('confidence')->nullable(); // For ML-assigned tags
            $table->timestamp('assigned_at');
            $table->timestamp('expires_at')->nullable(); // For temporary tags
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();

            $table->unique(['person_id', 'tag_id']);
            $table->index(['tenant_id', 'tag_id']);
            $table->index(['tenant_id', 'person_id']);
            $table->index(['source', 'source_id']);
            $table->index('expires_at');
        });

        // Auto-tagging rules
        Schema::create('person_tag_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('person_tags')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->jsonb('conditions'); // Same format as audience builder
            $table->string('match_type', 10)->default('all'); // all, any
            $table->boolean('is_active')->default(true);
            $table->boolean('remove_when_unmet')->default(false); // Remove tag when conditions no longer met
            $table->integer('priority')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->integer('last_run_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Tag activity log
        Schema::create('person_tag_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('person_tags')->cascadeOnDelete();
            $table->string('action', 20); // assigned, removed, expired
            $table->string('source', 30);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'person_id']);
            $table->index(['tenant_id', 'tag_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_tag_logs');
        Schema::dropIfExists('person_tag_rules');
        Schema::dropIfExists('person_tag_assignments');
        Schema::dropIfExists('person_tags');
    }
};
