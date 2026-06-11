<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // coloana poate fi null la început ca să putem face backfill
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // index util pentru lookup după tenant+email
            $table->index(['tenant_id', 'customer_email']);
        });

        // Backfill: pentru fiecare (tenant_id, customer_email) găsim/creăm customer
        // și setăm orders.customer_id
        DB::transaction(function () {
            // fără Eloquent: ne permitem să rulăm în bucăți mari fără a încărca modele
            $orders = DB::table('orders')
                ->select('id', 'tenant_id', 'customer_email')
                ->whereNotNull('customer_email')
                ->orderBy('id')
                ->chunk(1000, function ($chunk) {
                    foreach ($chunk as $o) {
                        // caută customer
                        $customerId = DB::table('customers')
                            ->where('tenant_id', $o->tenant_id)
                            ->where('email', $o->customer_email)
                            ->value('id');

                        // dacă nu există, îl creăm minimal
                        if (!$customerId) {
                            $customerId = DB::table('customers')->insertGetId([
                                'tenant_id'  => $o->tenant_id,
                                'email'      => $o->customer_email,
                                'full_name'  => null,
                                'phone'      => null,
                                'meta'       => json_encode([]),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('orders')
                            ->where('id', $o->id)
                            ->update(['customer_id' => $customerId]);
                    }
                });
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'customer_email']);
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
