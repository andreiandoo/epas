<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Values of closed months on the marketplace dashboard (month stats,
        // billing, commission, chart series), computed once and then read as-is.
        Schema::create('marketplace_dashboard_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('period', 20); // Y-m
            $table->json('payload');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['marketplace_client_id', 'kind', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_dashboard_snapshots');
    }
};
