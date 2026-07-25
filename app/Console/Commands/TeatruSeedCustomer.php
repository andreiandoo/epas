<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creează un client demo pentru autentificare pe site-ul tenant de test.
 *
 *   php artisan teatru:seed-customer {tenant=17}
 *
 * Credențiale: demo@teatru.tixello.ro / demo1234
 */
class TeatruSeedCustomer extends Command
{
    protected $signature = 'teatru:seed-customer {tenant=17 : ID tenant} {--email=demo@teatru.tixello.ro} {--password=demo1234}';
    protected $description = 'Seed client demo (login) pentru un tenant de test';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant');
        $email = strtolower(trim($this->option('email')));
        $password = $this->option('password');

        $customer = Customer::updateOrCreate(
            ['tenant_id' => $tenantId, 'email' => $email],
            [
                'first_name'        => 'Client',
                'last_name'         => 'Demo',
                'phone'             => '0700000000',
                'primary_tenant_id' => $tenantId,
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        // Asigură legătura în pivotul customer_tenant (login verifică ambele)
        try {
            $customer->tenants()->syncWithoutDetaching([$tenantId]);
        } catch (\Throwable $e) {
            // relația poate lipsi pe unele build-uri — tenant_id direct e suficient
        }

        $this->info('Client demo pregătit (#' . $customer->id . ')');
        $this->line('  Email:  ' . $email);
        $this->line('  Parolă: ' . $password);

        return self::SUCCESS;
    }
}
