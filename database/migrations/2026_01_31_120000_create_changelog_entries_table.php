<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('commit_hash', 40)->unique();
            $table->string('short_hash', 8);
            $table->string('type', 20)->default('other'); // feat, fix, refactor, docs, style, test, chore
            $table->string('scope', 50)->nullable(); // marketplace, organizer, seating, analytics, etc.
            $table->string('module', 50)->nullable(); // grouped module name
            $table->text('message');
            $table->text('description')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->timestamp('committed_at');
            $table->json('files_changed')->nullable();
            $table->integer('additions')->default(0);
            $table->integer('deletions')->default(0);
            $table->boolean('is_breaking')->default(false);
            $table->boolean('is_visible')->default(true); // hide deploy commits, etc.
            $table->timestamps();

            $table->index('type');
            $table->index('scope');
            $table->index('module');
            $table->index('committed_at');
            $table->index('is_visible');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
