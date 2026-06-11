<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_status_logs')) {
            return;
        }

        Schema::create('service_status_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service_name'); // core, api, microservice slug
            $table->string('service_type'); // core, api, microservice
            $table->boolean('is_online')->default(true);
            $table->integer('response_time_ms')->nullable(); // Response time in milliseconds
            $table->string('version')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['service_name', 'checked_at']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_status_logs');
    }
};
