<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agency_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete()
                ->comment('The agency tenant');
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete()
                ->comment('The global artist record');

            $table->string('contract_type', 32)->default('booking')
                ->comment('exclusive|non_exclusive|management|booking');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable()
                ->comment('Agency commission percentage for this artist');
            $table->json('territory')->nullable()
                ->comment('Countries/regions covered: ["RO","HU","MD"]');
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('active')->comment('active|inactive|pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'artist_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['artist_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_artists');
    }
};
