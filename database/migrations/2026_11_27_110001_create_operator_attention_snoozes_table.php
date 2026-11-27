<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // „Amână 1h” din bara de atenție: ascunde temporar un item doar pentru operatorul respectiv.
    public function up(): void
    {
        if (Schema::hasTable('operator_attention_snoozes')) {
            return;
        }

        Schema::create('operator_attention_snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_admin_id')->constrained('marketplace_admins')->cascadeOnDelete();
            $table->string('item_key', 64);
            $table->timestamp('until');
            $table->unique(['marketplace_admin_id', 'item_key'], 'op_attention_snooze_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_attention_snoozes');
    }
};
