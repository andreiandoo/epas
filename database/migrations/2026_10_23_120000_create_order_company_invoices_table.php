<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturi pe persoană juridică cerute la o comandă online.
 *
 * Un rând per emitent: marketplace-ul (comision peste preț + TVA, asigurare,
 * taxe) și fiecare organizator al comenzii (valoarea biletelor). Factura
 * marketplace-ului se emite automat (Oblio); a organizatorului o încarcă el.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_company_invoices')) {
            Schema::create('order_company_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marketplace_client_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('issuer', 20); // marketplace | organizer
                $table->unsignedBigInteger('marketplace_organizer_id')->nullable()->index();
                // marketplace: pending | issued | failed
                // organizer: requested | uploaded | not_applicable
                $table->string('status', 20)->index();
                $table->json('company');
                $table->json('lines')->nullable();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('vat_amount', 12, 2)->default(0);
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency', 3)->default('RON');
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->string('file_path')->nullable();
                $table->string('file_name')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->string('uploaded_by', 60)->nullable();
                $table->timestamp('sent_to_customer_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->text('last_error')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'order_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedBigInteger('order_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_company_invoices');

        if (Schema::hasColumn('invoices', 'order_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('order_id');
            });
        }
    }
};
