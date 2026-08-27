<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal upstream stubs for the live-chat test suite. The full schema cannot
 * run on SQLite (DECISIONS.md D-002) and the container has no Postgres, so the
 * chat tests run on an isolated in-memory connection with only these stubs plus
 * the real chat migrations. Just enough columns for the code paths under test
 * (MarketplaceClient::find graceful null, operator name lookups).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('public_name')->nullable();
            $table->string('api_key')->nullable();
            $table->string('status')->default('active');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_admins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('support_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_departments');
        Schema::dropIfExists('marketplace_admins');
        Schema::dropIfExists('marketplace_clients');
    }
};
