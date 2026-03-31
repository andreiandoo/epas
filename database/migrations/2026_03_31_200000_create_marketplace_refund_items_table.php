<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->constrained('marketplace_refund_requests')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('ticket_types')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->decimal('face_value', 10, 2)->default(0);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->boolean('commission_refunded')->default(false);
            $table->string('status', 32)->default('pending'); // pending, refunded, failed
            $table->timestamps();

            $table->index('refund_request_id');
            $table->index('ticket_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('refund_status', 16)->default('none')->after('status'); // none, refunded
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('refund_status');
        });

        Schema::dropIfExists('marketplace_refund_items');
    }
};
