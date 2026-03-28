<?php

namespace Database\Seeders;

use App\Enums\TenantType;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates one tenant + owner user for each TenantType.
 *
 * All users share the same password: Test1234!
 * Emails follow the pattern: {type}@test.tixello.com
 *
 * Run: php artisan db:seed --class=TenantTypesSeeder
 */
class TenantTypesSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('Test1234!');

        $tenants = [
            [
                'type' => TenantType::TenantArtist,
                'name' => 'Demo Tenant SRL',
                'public_name' => 'Demo Tenant',
                'email' => 'tenant@test.tixello.com',
                'first_name' => 'Ion',
                'last_name' => 'Popescu',
                'domain' => 'demo-tenant.tixello.local',
                'company_name' => 'Demo Tenant SRL',
                'city' => 'București',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Artist,
                'name' => 'Rock Band SRL',
                'public_name' => 'The Rockets',
                'email' => 'artist@test.tixello.com',
                'first_name' => 'Andrei',
                'last_name' => 'Marin',
                'domain' => 'the-rockets.tixello.local',
                'company_name' => 'Rock Band SRL',
                'city' => 'Cluj-Napoca',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Agency,
                'name' => 'Star Agency SRL',
                'public_name' => 'Star Agency',
                'email' => 'agency@test.tixello.com',
                'first_name' => 'Maria',
                'last_name' => 'Ionescu',
                'domain' => 'star-agency.tixello.local',
                'company_name' => 'Star Agency SRL',
                'city' => 'Timișoara',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Venue,
                'name' => 'Club Central SRL',
                'public_name' => 'Club Central',
                'email' => 'venue@test.tixello.com',
                'first_name' => 'Adrian',
                'last_name' => 'Popa',
                'domain' => 'club-central.tixello.local',
                'company_name' => 'Club Central SRL',
                'city' => 'Iași',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Speaker,
                'name' => 'TED Talks RO SRL',
                'public_name' => 'Dr. Elena Voicu',
                'email' => 'speaker@test.tixello.com',
                'first_name' => 'Elena',
                'last_name' => 'Voicu',
                'domain' => 'elena-voicu.tixello.local',
                'company_name' => 'TED Talks RO SRL',
                'city' => 'Brașov',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Competition,
                'name' => 'Sport Events SRL',
                'public_name' => 'Maratonul României',
                'email' => 'competition@test.tixello.com',
                'first_name' => 'Radu',
                'last_name' => 'Stancu',
                'domain' => 'maratonul-romaniei.tixello.local',
                'company_name' => 'Sport Events SRL',
                'city' => 'Sibiu',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::StadiumArena,
                'name' => 'Arena Națională SA',
                'public_name' => 'Arena Națională',
                'email' => 'stadium@test.tixello.com',
                'first_name' => 'Cristian',
                'last_name' => 'Dumitru',
                'domain' => 'arena-nationala.tixello.local',
                'company_name' => 'Arena Națională SA',
                'city' => 'București',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Philharmonic,
                'name' => 'Filarmonica de Stat',
                'public_name' => 'Filarmonica George Enescu',
                'email' => 'philharmonic@test.tixello.com',
                'first_name' => 'Diana',
                'last_name' => 'Constantinescu',
                'domain' => 'filarmonica-enescu.tixello.local',
                'company_name' => 'Filarmonica de Stat',
                'city' => 'București',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Opera,
                'name' => 'Opera Națională București',
                'public_name' => 'Opera Națională',
                'email' => 'opera@test.tixello.com',
                'first_name' => 'Mihai',
                'last_name' => 'Florescu',
                'domain' => 'opera-nationala.tixello.local',
                'company_name' => 'Opera Națională București',
                'city' => 'București',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Theater,
                'name' => 'Teatrul Național SRL',
                'public_name' => 'Teatrul Național',
                'email' => 'theater@test.tixello.com',
                'first_name' => 'Ana',
                'last_name' => 'Gheorghe',
                'domain' => 'teatrul-national.tixello.local',
                'company_name' => 'Teatrul Național SRL',
                'city' => 'Craiova',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Museum,
                'name' => 'Muzeul Național de Artă',
                'public_name' => 'Muzeul de Artă',
                'email' => 'museum@test.tixello.com',
                'first_name' => 'Laura',
                'last_name' => 'Neagu',
                'domain' => 'muzeul-arta.tixello.local',
                'company_name' => 'Muzeul Național de Artă',
                'city' => 'București',
                'country' => 'RO',
            ],
            [
                'type' => TenantType::Festival,
                'name' => 'Festival Productions SRL',
                'public_name' => 'Summer Vibes Festival',
                'email' => 'festival@test.tixello.com',
                'first_name' => 'George',
                'last_name' => 'Radu',
                'domain' => 'summer-vibes.tixello.local',
                'company_name' => 'Festival Productions SRL',
                'city' => 'Constanța',
                'country' => 'RO',
            ],
        ];

        foreach ($tenants as $data) {
            $type = $data['type'];

            // Skip if user with this email already exists
            $existingUser = User::where('email', $data['email'])->first();
            if ($existingUser) {
                $this->command->info("Skipping {$type->label()} — user {$data['email']} already exists.");
                continue;
            }

            // Create owner user
            $user = User::create([
                'name' => $data['first_name'] . ' ' . $data['last_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'email_verified_at' => now(),
                'password' => $password,
                'role' => 'tenant',
            ]);

            // Create tenant
            $slug = Str::slug($data['public_name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }

            $tenant = Tenant::create([
                'name' => $data['name'],
                'public_name' => $data['public_name'],
                'owner_id' => $user->id,
                'slug' => $slug,
                'domain' => $data['domain'],
                'status' => 'active',
                'tenant_type' => $type,
                'plan' => '2percent',
                'locale' => 'ro',
                'commission_mode' => 'included',
                'commission_rate' => 2.00,
                'work_method' => 'mixed',
                'currency' => 'RON',
                'company_name' => $data['company_name'],
                'city' => $data['city'],
                'country' => $data['country'],
                'contact_first_name' => $data['first_name'],
                'contact_last_name' => $data['last_name'],
                'contact_email' => $data['email'],
                'onboarding_completed' => true,
                'onboarding_completed_at' => now(),
                'billing_starts_at' => now(),
                'billing_cycle_days' => 30,
                'settings' => [],
                'features' => [],
            ]);

            // Create primary domain
            Domain::create([
                'tenant_id' => $tenant->id,
                'domain' => $data['domain'],
                'is_primary' => true,
                'is_active' => true,
                'is_managed_subdomain' => true,
            ]);

            $this->command->info("Created {$type->label()} tenant: {$data['public_name']} ({$data['email']})");
        }

        $this->command->newLine();
        $this->command->info('All tenant type demo accounts created!');
        $this->command->info('Login URL: /tenant/login');
        $this->command->info('Password for all accounts: Test1234!');
        $this->command->newLine();
        $this->command->table(
            ['Type', 'Email', 'Tenant Name'],
            collect($tenants)->map(fn ($data) => [
                $data['type']->label(),
                $data['email'],
                $data['public_name'],
            ])->toArray()
        );
    }
}
