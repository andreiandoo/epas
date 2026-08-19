<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de audit pentru comenzi sterse din pagina noua "Comenzi" leisure.
 * Order-ul e sters fizic din DB dar snapshotul complet (JSONB) e persistat
 * aici pentru audit + reconstructie evenutuala. Nota interna e obligatorie.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('leisure_order_deletion_logs', function (Blueprint $t) {
            $t->id();

            // Multi-tenant scope
            $t->unsignedBigInteger('marketplace_client_id')->index();
            $t->unsignedBigInteger('marketplace_organizer_id')->nullable()->index();
            $t->unsignedBigInteger('event_id')->nullable()->index();

            // Order metadata (snapshot la momentul stergerii — orderul original nu mai exista)
            $t->unsignedBigInteger('order_id')->nullable()->comment('Frozen ref - order-ul e sters');
            $t->string('order_number', 64)->index();
            $t->string('order_source', 32)->nullable();
            $t->string('order_status', 32)->nullable();
            $t->decimal('order_total', 12, 2)->nullable();
            $t->string('order_currency', 8)->nullable()->default('RON');
            $t->timestamp('order_paid_at')->nullable();
            $t->timestamp('order_created_at')->nullable();

            // Client info
            $t->string('customer_name')->nullable();
            $t->string('customer_email')->nullable();
            $t->string('customer_phone', 32)->nullable();

            // POS context (daca era comanda de casa)
            $t->string('payment_method', 16)->nullable(); // cash|card|invoice|online
            $t->unsignedBigInteger('cashier_session_id')->nullable();
            $t->string('cashier_operator_name')->nullable();

            // Tickets count + full snapshot (order + tickets + meta) pentru audit complet
            $t->integer('tickets_count')->nullable();
            $t->jsonb('snapshot')->nullable();

            // Cine + de ce
            $t->text('note'); // OBLIGATORIU la delete
            $t->string('deleted_by_type', 32)->nullable(); // organizer | team_member | admin
            $t->unsignedBigInteger('deleted_by_id')->nullable();
            $t->string('deleted_by_name')->nullable();
            $t->string('deleted_by_email')->nullable();

            // Session snapshot regeneration bookkeeping
            $t->boolean('cashier_snapshot_regenerated')->default(false);

            $t->timestamp('deleted_at')->useCurrent();
            $t->timestamps();

            $t->index(['event_id', 'deleted_at']);
            $t->index(['marketplace_client_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leisure_order_deletion_logs');
    }
};
