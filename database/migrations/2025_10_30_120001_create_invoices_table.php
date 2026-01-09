<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('invoices')) {
            return;
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 64)->unique();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('RON');
            $table->enum('status', ['outstanding', 'paid', 'cancelled'])->default('outstanding');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('invoices');
    }
};
