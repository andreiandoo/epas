<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // dacă există deja, nu mai crea încă o dată
        if (! \Illuminate\Support\Facades\Schema::hasTable('events')) {
            \Illuminate\Support\Facades\Schema::create('events', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

                $table->string('title', 190);
                $table->string('slug', 190)->unique();

                $table->string('venue', 190)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('country', 120)->nullable();

                $table->timestampTz('starts_at')->index();
                $table->timestampTz('ends_at')->nullable();

                $table->boolean('is_recurring')->default(false);
                $table->string('status', 32)->default('draft');

                $table->string('poster_url')->nullable();

                $table->string('locale', 10)->default('ro');
                $table->json('seo')->nullable();
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
