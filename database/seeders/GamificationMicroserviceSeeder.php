<?php

namespace Database\Seeders;

use App\Models\Microservice;
use Illuminate\Database\Seeder;

class GamificationMicroserviceSeeder extends Seeder
{
    public function run(): void
    {
        Microservice::updateOrCreate(
            ['slug' => 'gamification'],
            [
                'name' => [
                    'en' => 'Gamification & Loyalty',
                    'ro' => 'Gamificare & Loialitate',
                ],
                'description' => [
                    'en' => 'Reward your customers with points for purchases, referrals, birthdays and more. Build customer loyalty with a comprehensive points system including tiers, redemption at checkout, and referral tracking.',
                    'ro' => 'Recompenseaza-ti clientii cu puncte pentru achizitii, referinte, zile de nastere si multe altele. Construieste loialitatea clientilor cu un sistem complet de puncte incluzand niveluri, rascumparare la checkout si urmarirea referintelor.',
                ],
                'short_description' => [
                    'en' => 'Customer loyalty points & rewards program',
                    'ro' => 'Program de puncte si recompense pentru clienti',
                ],
                'price' => 15.00,
                'currency' => 'EUR',
                'billing_cycle' => 'monthly',
                'pricing_model' => 'recurring',
                'category' => 'marketing',
                'status' => 'active',
                'icon' => 'star',
                'sort_order' => 45,
                'features' => [
                    'en' => [
                        'Earn points on orders (configurable percentage)',
                        'Birthday bonus points',
                        'Signup welcome bonus',
                        'Referral program with trackable links',
                        'Points redemption at checkout',
                        'Customer tiers/levels system',
                        'Points expiration settings',
                        'Admin dashboard with analytics',
                        'Customer account points history',
                        'Configurable point actions',
                    ],
                    'ro' => [
                        'Castiga puncte la comenzi (procent configurabil)',
                        'Puncte bonus de ziua de nastere',
                        'Bonus de bun venit la inregistrare',
                        'Program de referinte cu linkuri urmaribile',
                        'Rascumparare puncte la checkout',
                        'Sistem de niveluri/trepte pentru clienti',
                        'Setari de expirare puncte',
                        'Panou admin cu statistici',
                        'Istoric puncte in contul clientului',
                        'Actiuni configurabile pentru puncte',
                    ],
                ],
                'metadata' => [
                    'endpoints' => [
                        '/gamification/balance',
                        '/gamification/history',
                        '/gamification/redeem',
                        '/gamification/config',
                        '/gamification/referral',
                        '/gamification/tiers',
                    ],
                    'database_tables' => [
                        'gamification_configs',
                        'gamification_actions',
                        'customer_points',
                        'points_transactions',
                        'referrals',
                    ],
                    'integrations' => [
                        'checkout',
                        'orders',
                        'customers',
                    ],
                    'limits' => [
                        'max_actions' => 20,
                        'max_tiers' => 10,
                    ],
                ],
            ]
        );
    }
}
