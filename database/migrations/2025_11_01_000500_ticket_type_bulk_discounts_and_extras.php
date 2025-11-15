<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('ticket_types', function (Blueprint $table) {
      if (!Schema::hasColumn('ticket_types', 'sku')) {
        $table->string('sku', 64)->nullable()->after('name');
      }
      // currency already exists in base table, skip adding it
      if (!Schema::hasColumn('ticket_types', 'bulk_discounts')) {
        $table->json('bulk_discounts')->nullable()->after('meta');
      }
    });
  }
  public function down(): void {
    Schema::table('ticket_types', function (Blueprint $table) {
      if (Schema::hasColumn('ticket_types', 'bulk_discounts')) $table->dropColumn('bulk_discounts');
      // currency is part of base table, don't drop it
      if (Schema::hasColumn('ticket_types', 'sku')) $table->dropColumn('sku');
    });
  }
};
