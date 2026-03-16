<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('web_template_feedbacks')) {
            Schema::create('web_template_feedbacks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('web_template_customization_id')
                    ->constrained('web_template_customizations')
                    ->cascadeOnDelete();
                $table->unsignedTinyInteger('rating'); // 1-5 stars
                $table->text('comment')->nullable();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('company')->nullable();
                $table->string('ip_hash', 16)->nullable();
                $table->timestamps();

                $table->index('web_template_customization_id');
                $table->index('rating');
            });
        }

        // Self-service edit token
        if (Schema::hasTable('web_template_customizations')) {
            if (!Schema::hasColumn('web_template_customizations', 'self_service_token')) {
                Schema::table('web_template_customizations', function (Blueprint $table) {
                    $table->string('self_service_token', 32)->nullable()->unique()->after('preview_password');
                    $table->json('self_service_fields')->nullable()->after('self_service_token');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_template_feedbacks');

        if (Schema::hasTable('web_template_customizations')) {
            if (Schema::hasColumn('web_template_customizations', 'self_service_token')) {
                Schema::table('web_template_customizations', function (Blueprint $table) {
                    $table->dropColumn(['self_service_token', 'self_service_fields']);
                });
            }
        }
    }
};
