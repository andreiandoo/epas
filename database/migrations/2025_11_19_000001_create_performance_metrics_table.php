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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('endpoint')->index();
            $table->string('method', 10)->index();
            $table->decimal('duration_ms', 10, 2);
            $table->smallInteger('status_code')->index();
            $table->decimal('memory_mb', 10, 2);
            $table->integer('query_count')->default(0);
            $table->decimal('query_time_ms', 10, 2)->default(0);
            $table->integer('slow_query_count')->default(0);
            $table->timestamp('created_at')->index();

            // Indexes for performance
            $table->index(['endpoint', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status_code', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
