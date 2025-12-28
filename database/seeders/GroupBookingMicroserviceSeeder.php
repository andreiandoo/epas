<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class GroupBookingMicroserviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'group-booking'],
            [
                'name' => ['en' => 'Group Booking', 'ro' => 'Rezervări de Grup'],
                'description' => [
                    'en' => 'Streamline group ticket purchases with bulk ordering, group discounts, and attendee management. Perfect for corporate events, school trips, and tour groups.',
                    'ro' => 'Simplifică achizițiile de bilete în grup cu comenzi în vrac, reduceri de grup și gestionarea participanților. Perfect pentru evenimente corporate, excursii școlare și grupuri turistice.'
                ],
                'short_description' => [
                    'en' => 'Bulk ordering and group discount management',
                    'ro' => 'Comenzi în vrac și gestionarea reducerilor de grup'
                ],
                'price' => 12.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'features' => [
                    'en' => [
                        'Bulk ticket ordering interface',
                        'Tiered group discounts',
                        'Group leader dashboard',
                        'Attendee list management',
                        'Individual ticket distribution',
                        'Group invoice generation',
                        'Partial payment support',
                        'Seat block reservations',
                        'Custom group booking forms',
                        'Approval workflow for large groups',
                        'Group communication tools',
                        'Dietary/accessibility requirements collection',
                        'Group check-in at venue',
                        'Waitlist for sold-out group allocations',
                    ],
                    'ro' => [
                        'Interfață comandă bilete în vrac',
                        'Reduceri de grup pe niveluri',
                        'Tablou de bord lider grup',
                        'Gestionare listă participanți',
                        'Distribuție individuală bilete',
                        'Generare factură grup',
                        'Suport plată parțială',
                        'Rezervări blocuri locuri',
                        'Formulare personalizate rezervare grup',
                        'Flux aprobare pentru grupuri mari',
                        'Instrumente comunicare grup',
                        'Colectare cerințe dietetice/accesibilitate',
                        'Check-in grup la locație',
                        'Listă așteptare pentru alocări grup sold-out',
                    ],
                ],
                'category' => 'sales',
                'status' => 'active',
                'metadata' => [
                    'endpoints' => [
                        'POST /api/group-booking/request',
                        'GET /api/group-booking/{bookingId}',
                        'PUT /api/group-booking/{bookingId}/attendees',
                        'POST /api/group-booking/{bookingId}/distribute',
                        'GET /api/group-booking/discounts/{eventId}',
                    ],
                    'min_group_size' => 10,
                    'max_group_size' => 500,
                    'payment_split' => true,
                ],
            ]
        );

        $this->command->info('✓ Group Booking microservice seeded successfully');
    }
}
