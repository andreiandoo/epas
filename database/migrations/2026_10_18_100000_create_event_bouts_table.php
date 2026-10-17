<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fight card-ul unei gale (MMA, box, kickbox): meciurile evenimentului, în ordine,
 * pe secțiuni (main event, co-main, main card, prelims), cu cei doi luptători
 * (tenant_artists cu rol „fighter”), categoria, rundele, centura pusă în joc și
 * rezultatul. Pur aditiv: nimic existent nu citește tabela.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_bouts')) {
            return;
        }

        Schema::create('event_bouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('card_section', 24)->default('main_card')->comment('main_event|co_main|main_card|prelims|early_prelims');
            $table->unsignedSmallInteger('sort_order')->default(0)->comment('0 = primul în secțiune');
            $table->foreignId('fighter_a_id')->nullable()->constrained('tenant_artists')->nullOnDelete();
            $table->foreignId('fighter_b_id')->nullable()->constrained('tenant_artists')->nullOnDelete();
            $table->string('fighter_a_name')->nullable()->comment('Când luptătorul nu e în baza de date sau e TBA');
            $table->string('fighter_b_name')->nullable();
            $table->string('weight_class', 64)->nullable()->comment('ex. Ușoară (70 kg)');
            $table->string('discipline', 32)->default('mma')->comment('mma|kickboxing|boxing|muay_thai|grappling');
            $table->unsignedTinyInteger('rounds')->default(3);
            $table->unsignedTinyInteger('round_minutes')->default(5);
            $table->boolean('is_title_fight')->default(false);
            $table->string('title_name')->nullable()->comment('ex. Centura la categoria grea');
            $table->string('status', 16)->default('scheduled')->comment('scheduled|confirmed|completed|cancelled');
            $table->string('winner', 8)->nullable()->comment('a|b|draw|nc');
            $table->string('method', 64)->nullable()->comment('KO/TKO, Submission, Decizie unanimă …');
            $table->unsignedTinyInteger('result_round')->nullable();
            $table->string('result_time', 8)->nullable()->comment('mm:ss');
            $table->json('meta')->nullable()->comment('i18n: {en: {weight_class, title_name, method}}');
            $table->timestamps();

            $table->index(['event_id', 'card_section', 'sort_order']);
            $table->index(['tenant_id', 'fighter_a_id']);
            $table->index(['tenant_id', 'fighter_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_bouts');
    }
};
