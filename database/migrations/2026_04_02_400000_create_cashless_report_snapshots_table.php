<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashless_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('festival_edition_id')->constrained()->cascadeOnDelete();
            $table->string('report_type', 50);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->json('dimensions')->nullable();
            $table->json('metrics');
            $table->timestamps();

            $table->index(['festival_edition_id', 'report_type']);
            $table->index(['report_type', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashless_report_snapshots');
    }
};
