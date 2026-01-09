<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_digital_downloads')) {
            return;
        }

        Schema::create('shop_digital_downloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_item_id');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('download_token', 64)->unique();
            $table->integer('download_count')->default(0);
            $table->integer('max_downloads')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamps();

            $table->foreign('order_item_id')->references('id')->on('shop_order_items')->cascadeOnDelete();
            $table->index('download_token');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_digital_downloads');
    }
};
