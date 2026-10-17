<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Demo-uri live ale template-urilor (demo-{slug}.tics.ro), pe date ale unui
        // tenant demo. Un host = un template; mai multe demo-uri pot folosi același tenant.
        Schema::create('tenant_site_demos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('template', 64);
            $table->string('title');
            $table->text('description')->nullable();
            // Personalizare opțională (aceeași formă ca tenant_sites.draft); goală = valorile template-ului.
            $table->json('data')->nullable();
            $table->boolean('is_listed')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_site_demos');
    }
};
