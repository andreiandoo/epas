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
        if (Schema::hasTable('platform_credentials')) {
            return;
        }

        Schema::create('platform_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->text('password')->nullable(); // Encrypted
            $table->enum('category', [
                'social_content',
                'saas_review',
                'startup_directory',
                'business_listing',
                'developer_tech',
                'integration_marketplace',
                'community_forum',
            ])->index();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
