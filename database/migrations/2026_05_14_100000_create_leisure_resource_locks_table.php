<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leisure_resource_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('ticket_type_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedInteger('qty')->default(1); // câte unități fizice sunt blocate
            $table->string('status', 16)->default('active'); // active | released | expired
            $table->timestamps();

            $table->foreign('event_id', 'lrlocks_event_fk')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('ticket_type_id', 'lrlocks_tt_fk')->references('id')->on('ticket_types')->onDelete('cascade');
            $table->foreign('order_id', 'lrlocks_order_fk')->references('id')->on('orders')->onDelete('set null');

            // Index critic pentru overlap queries
            $table->index(['ticket_type_id', 'status', 'start_at', 'end_at'], 'lrlocks_overlap_idx');
            $table->index(['event_id', 'start_at'], 'lrlocks_event_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_resource_locks');
    }
};
