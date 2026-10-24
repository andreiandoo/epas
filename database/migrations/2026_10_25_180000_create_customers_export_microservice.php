<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Microserviciul „Export clienți”: fără el, datele clienților (și abonații la
 * newsletter) nu se pot descărca din panoul tenantului. Scoate date din Tixello,
 * deci ridică comisionul cu un punct, ca Facebook CAPI și Accounting Connectors.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('microservices')->where('slug', 'customers-export')->exists()) {
            DB::table('microservices')->where('slug', 'customers-export')
                ->update(['raises_commission' => true, 'signup_featured' => true, 'updated_at' => now()]);

            return;
        }

        DB::table('microservices')->insert([
            'slug' => 'customers-export',
            'name' => json_encode(['ro' => 'Export clienți', 'en' => 'Customer Export'], JSON_UNESCAPED_UNICODE),
            'short_description' => json_encode([
                'ro' => 'Descarci lista clienților tăi într-un fișier CSV',
                'en' => 'Download your customer list as a CSV file',
            ], JSON_UNESCAPED_UNICODE),
            'description' => json_encode([
                'ro' => '<p>Descarci oricând, într-un fișier CSV pe care îl deschizi în Excel, clienții care au cumpărat de la tine: nume, email, telefon, oraș, câte comenzi au făcut, cât au cheltuit și dacă sunt abonați la newsletter. La fel și lista abonaților la newsletter. Fiecare export rămâne în jurnalul contului.</p>',
                'en' => '<p>Download, at any time, a CSV file (opens in Excel) with the customers who bought from you: name, email, phone, city, number of orders, total spent and newsletter status. The same goes for your newsletter subscribers. Every export is recorded in the account log.</p>',
            ], JSON_UNESCAPED_UNICODE),
            'price' => 0,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'pricing_model' => 'recurring',
            'category' => 'crm',
            'is_active' => true,
            'sort_order' => 50,
            'raises_commission' => true,
            'signup_featured' => true,
            'signup_hidden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('microservices')->where('slug', 'customers-export')->delete();
    }
};
