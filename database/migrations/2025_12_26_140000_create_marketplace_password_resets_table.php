<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('type'); // 'organizer' or 'customer'
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'type', 'marketplace_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_password_resets');
    }
};
