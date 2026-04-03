<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DemoCustomerSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;

        $customersData = [
            ['first_name' => 'Ana', 'last_name' => 'Popescu', 'email' => 'demo-ana.popescu@example.com', 'phone' => '+40741000001', 'city' => 'Bucuresti', 'country' => 'RO'],
            ['first_name' => 'Ion', 'last_name' => 'Ionescu', 'email' => 'demo-ion.ionescu@example.com', 'phone' => '+40741000002', 'city' => 'Cluj-Napoca', 'country' => 'RO'],
            ['first_name' => 'Maria', 'last_name' => 'Dumitrescu', 'email' => 'demo-maria.d@example.com', 'phone' => '+40741000003', 'city' => 'Timisoara', 'country' => 'RO'],
            ['first_name' => 'Andrei', 'last_name' => 'Popa', 'email' => 'demo-andrei.popa@example.com', 'phone' => '+40741000004', 'city' => 'Iasi', 'country' => 'RO'],
            ['first_name' => 'Elena', 'last_name' => 'Vasile', 'email' => 'demo-elena.vasile@example.com', 'phone' => '+40741000005', 'city' => 'Brasov', 'country' => 'RO'],
            ['first_name' => 'Mihai', 'last_name' => 'Stan', 'email' => 'demo-mihai.stan@example.com', 'phone' => '+40741000006', 'city' => 'Constanta', 'country' => 'RO'],
            ['first_name' => 'Cristina', 'last_name' => 'Radu', 'email' => 'demo-cristina.radu@example.com', 'phone' => '+40741000007', 'city' => 'Craiova', 'country' => 'RO'],
            ['first_name' => 'Alexandru', 'last_name' => 'Marin', 'email' => 'demo-alex.marin@example.com', 'phone' => '+40741000008', 'city' => 'Sibiu', 'country' => 'RO'],
            ['first_name' => 'Diana', 'last_name' => 'Stoica', 'email' => 'demo-diana.stoica@example.com', 'phone' => '+40741000009', 'city' => 'Oradea', 'country' => 'RO'],
            ['first_name' => 'Robert', 'last_name' => 'Munteanu', 'email' => 'demo-robert.m@example.com', 'phone' => '+40741000010', 'city' => 'Galati', 'country' => 'RO'],
            ['first_name' => 'Ioana', 'last_name' => 'Gheorghe', 'email' => 'demo-ioana.gh@example.com', 'phone' => '+40741000011', 'city' => 'Ploiesti', 'country' => 'RO'],
            ['first_name' => 'Stefan', 'last_name' => 'Lazarescu', 'email' => 'demo-stefan.l@example.com', 'phone' => '+40741000012', 'city' => 'Arad', 'country' => 'RO'],
            ['first_name' => 'Andreea', 'last_name' => 'Nistor', 'email' => 'demo-andreea.n@example.com', 'phone' => '+40741000013', 'city' => 'Pitesti', 'country' => 'RO'],
            ['first_name' => 'Cosmin', 'last_name' => 'Barbu', 'email' => 'demo-cosmin.b@example.com', 'phone' => '+40741000014', 'city' => 'Bacau', 'country' => 'RO'],
            ['first_name' => 'Raluca', 'last_name' => 'Diaconu', 'email' => 'demo-raluca.d@example.com', 'phone' => '+40741000015', 'city' => 'Suceava', 'country' => 'RO'],
            ['first_name' => 'Gabriel', 'last_name' => 'Tudor', 'email' => 'demo-gabriel.t@example.com', 'phone' => '+40741000016', 'city' => 'Targu Mures', 'country' => 'RO'],
            ['first_name' => 'Laura', 'last_name' => 'Enache', 'email' => 'demo-laura.e@example.com', 'phone' => '+40741000017', 'city' => 'Baia Mare', 'country' => 'RO'],
            ['first_name' => 'Vlad', 'last_name' => 'Serban', 'email' => 'demo-vlad.s@example.com', 'phone' => '+40741000018', 'city' => 'Buzau', 'country' => 'RO'],
            ['first_name' => 'Simona', 'last_name' => 'Neagu', 'email' => 'demo-simona.n@example.com', 'phone' => '+40741000019', 'city' => 'Satu Mare', 'country' => 'RO'],
            ['first_name' => 'Bogdan', 'last_name' => 'Voicu', 'email' => 'demo-bogdan.v@example.com', 'phone' => '+40741000020', 'city' => 'Deva', 'country' => 'RO'],
        ];

        $customers = [];
        foreach ($customersData as $cd) {
            $customer = Customer::firstOrCreate(
                ['tenant_id' => $tenantId, 'email' => $cd['email']],
                [
                    'first_name' => $cd['first_name'],
                    'last_name' => $cd['last_name'],
                    'full_name' => $cd['first_name'] . ' ' . $cd['last_name'],
                    'phone' => $cd['phone'],
                    'city' => $cd['city'],
                    'country' => $cd['country'],
                    'primary_tenant_id' => $tenantId,
                ]
            );

            // Ensure customer-tenant pivot
            DB::table('customer_tenant')->insertOrIgnore([
                'customer_id' => $customer->id,
                'tenant_id' => $tenantId,
            ]);

            $customers[] = $customer;
        }

        $this->parent->refs['customers'] = $customers;
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;
        $customerIds = Customer::where('tenant_id', $tenantId)->where('email', 'like', 'demo-%')->pluck('id');
        DB::table('customer_tenant')->whereIn('customer_id', $customerIds)->delete();
        Customer::whereIn('id', $customerIds)->forceDelete();
    }
}
