<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_password_resets')) {
            return;
        }

        Schema::create('marketplace_password_resets', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('type'); // 'organizer' or 'customer'
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'type', 'marketplace_client_id'], 'mkt_pwd_reset_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_password_resets');
    }
};
