<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice — operator canned responses / macros.
 *
 * Bodies may contain {variables} (e.g. {name}, {event}) expanded at insert
 * time by the operator UI. Optionally scoped to a department.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_canned_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('support_department_id')
                ->nullable()
                ->constrained('support_departments')
                ->nullOnDelete();

            // Slash trigger typed by the operator, e.g. "/refund".
            $table->string('shortcut', 64);
            $table->string('title', 191);
            $table->mediumText('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['marketplace_client_id', 'shortcut'], 'chat_canned_shortcut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_canned_responses');
    }
};
