<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rapoarte configurabile din panoul marketplace (Rapoarte organizatori,
     * Rapoarte registre taxe): ce coloane vrea adminul să vadă. Comune pentru
     * toți adminii marketplace-ului.
     */
    public function up(): void
    {
        if (Schema::hasTable('marketplace_saved_reports')) {
            return;
        }

        Schema::create('marketplace_saved_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id')->index();
            // organizers | tax_registries
            $table->string('entity', 40);
            $table->string('name', 120);
            $table->json('columns');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_name', 160)->nullable();
            $table->timestamps();

            $table->index(['marketplace_client_id', 'entity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_saved_reports');
    }
};
