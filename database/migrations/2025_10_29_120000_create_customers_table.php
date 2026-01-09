<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            return;
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // legăm fiecare customer de un tenant
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('email', 190);
            $table->string('full_name', 190)->nullable();
            $table->string('phone', 32)->nullable();

            // loc pentru date suplimentare (adresă, preferințe, GDPR flags etc.)
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // de obicei email+tenant trebuie să fie unic împreună
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
