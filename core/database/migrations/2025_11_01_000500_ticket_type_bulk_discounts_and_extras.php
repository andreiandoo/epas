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
      if (!Schema::hasColumn('ticket_types', 'currency')) {
        $table->string('currency', 3)->nullable()->after('sku');
      }
      // ai deja price_max / price / discount_percent; dacă nu, adaugă-le aici
      if (!Schema::hasColumn('ticket_types', 'bulk_discounts')) {
        $table->json('bulk_discounts')->nullable()->after('discount_percent');
      }
    });
  }
  public function down(): void {
    Schema::table('ticket_types', function (Blueprint $table) {
      if (Schema::hasColumn('ticket_types', 'bulk_discounts')) $table->dropColumn('bulk_discounts');
      if (Schema::hasColumn('ticket_types', 'currency')) $table->dropColumn('currency');
      if (Schema::hasColumn('ticket_types', 'sku')) $table->dropColumn('sku');
    });
  }
};
