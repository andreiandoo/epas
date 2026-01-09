<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('docs')) {
            return;
        }

        Schema::create('docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('type')->default('general'); // component, module, microservice, api, guide
            $table->string('version')->default('1.0.0');
            $table->string('status')->default('draft'); // draft, published, archived
            $table->boolean('is_public')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable(); // For additional structured data
            $table->json('tags')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('docs')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_public']);
            $table->index('type');
            $table->fullText(['title', 'content', 'excerpt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs');
    }
};
