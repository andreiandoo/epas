<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Permanent cast for a repertoire piece (e.g. "Ion Caramitru as Hamlet")
        // This is the default distribution; event_tenant_artist can override per-performance
        Schema::create('repertoire_tenant_artist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repertoire_id')->constrained('repertoire')->cascadeOnDelete();
            $table->foreignId('tenant_artist_id')->constrained()->cascadeOnDelete();
            $table->string('role_name')->nullable()->comment('Character or role name, e.g. "Hamlet", "Violin I"');
            $table->string('role_type', 32)->nullable()->comment('lead|supporting|ensemble|understudy|guest');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['repertoire_id', 'tenant_artist_id', 'role_name'], 'rep_artist_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repertoire_tenant_artist');
    }
};
