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
        Schema::create('wallet_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('platform', ['apple', 'google'])->index();
            $table->string('pass_identifier')->unique();
            $table->string('serial_number')->index();
            $table->string('auth_token');
            $table->string('push_token')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'platform']);
            $table->index(['ticket_id', 'platform']);
        });

        Schema::create('wallet_push_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained('wallet_passes')->onDelete('cascade');
            $table->string('device_library_id')->index();
            $table->string('push_token');
            $table->timestamps();

            $table->unique(['pass_id', 'device_library_id']);
        });

        Schema::create('wallet_pass_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pass_id')->constrained('wallet_passes')->onDelete('cascade');
            $table->string('update_type'); // event_changed, cancelled, etc.
            $table->json('changes')->nullable();
            $table->boolean('pushed')->default(false);
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();

            $table->index(['pass_id', 'pushed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_pass_updates');
        Schema::dropIfExists('wallet_push_registrations');
        Schema::dropIfExists('wallet_passes');
    }
};
