<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('template', 64);
            // Ce editează adminul (ciorna) și ce vede publicul (ultima publicare).
            // Aceeași formă: branding, theme, nav, footer, pages.
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->string('published_template', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            // Deschide ciorna pe site (?preview=TOKEN), fără să o publice.
            $table->string('preview_token', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_sites');
    }
};
