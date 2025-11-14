<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('address', 255)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('website_url', 255)->nullable();
            $table->integer('capacity')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('venues');
    }
};
