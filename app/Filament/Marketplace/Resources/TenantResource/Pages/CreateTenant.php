<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Enums\TenantType;
use App\Filament\Marketplace\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function beforeValidate(): void
    {
        \Log::info('[MarketplaceTenantCreate] beforeValidate reached', ['data' => $this->data]);
    }

    protected function beforeCreate(): void
    {
        \Log::info('[MarketplaceTenantCreate] beforeCreate reached');
    }

    /**
     * Everything happens inside handleRecordCreation because it's the
     * single Filament hook that receives $data directly. Splitting the
     * work between mutateFormDataBeforeCreate (extract to class prop)
     * and afterCreate (read class prop) hits a Livewire lifecycle bug:
     * class properties get re-hydrated between hooks and reset to their
     * default, so the class prop we set in mutate reads back as [] in
     * afterCreate — which is exactly what happened on tenant 38 (user
     * created with blank name+email, zero venues linked). Doing it all
     * in one hook + one transaction removes that fragility entirely.
     */
    protected function handleRecordCreation(array $data): Model
    {
        \Log::info('[MarketplaceTenantCreate] handleRecordCreation reached', [
            'data_keys' => array_keys($data),
            'public_name' => $data['public_name'] ?? null,
            'owner_email' => $data['owner_email'] ?? null,
            'linked_venue_ids' => $data['linked_venue_ids'] ?? null,
        ]);
        $mcId = TenantResource::getMarketplaceClient()?->id;
        if (!$mcId) {
            throw ValidationException::withMessages([
                'public_name' => 'Marketplace context lipsă — reautentifică-te.',
            ]);
        }

        $firstName = trim((string) ($data['owner_first_name'] ?? ''));
        $lastName = trim((string) ($data['owner_last_name'] ?? ''));
        $email = strtolower(trim((string) ($data['owner_email'] ?? '')));
        $password = (string) ($data['owner_password'] ?? '');
        $venueIds = is_array($data['linked_venue_ids'] ?? null)
            ? array_values(array_filter(array_map('intval', $data['linked_venue_ids'])))
            : [];

        if ($email === '' || $password === '') {
            throw ValidationException::withMessages([
                'owner_email' => 'Email + parolă obligatorii pentru contul owner.',
            ]);
        }

        // Email collision — any existing users row (organizer, artist,
        // editor, another tenant owner) means we refuse. Linking to an
        // existing user is deliberately off-scope for the marketplace
        // flow per the 2026-08-22 spec.
        $existing = User::where('email', $email)->first();
        if ($existing) {
            $role = $existing->role ?? 'necunoscut';
            $ownedTenant = Tenant::where('owner_id', $existing->id)->first();
            $usage = $ownedTenant
                ? "cont tenant existent \"" . ($ownedTenant->public_name ?? $ownedTenant->name) . '"'
                : "cont existent cu rol \"{$role}\"";
            throw ValidationException::withMessages([
                'owner_email' => "Adresa {$email} este deja folosită de un {$usage}. Alege alt email — linkarea la un cont existent nu e suportată din marketplace panel.",
            ]);
        }

        $fullName = trim($firstName . ' ' . $lastName);
        $publicName = trim((string) ($data['public_name'] ?? '')) ?: ($fullName ?: 'Venue Owner');

        return DB::transaction(function () use ($data, $mcId, $email, $password, $fullName, $publicName, $venueIds) {
            // 1) Tenant. tenant_type + origin marker are re-stamped
            //    server-side even if the hidden fields were tampered
            //    with. Slug is unique-suffixed against existing rows.
            $baseSlug = Str::slug($publicName);
            $slug = $baseSlug;
            $i = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . (++$i);
            }

            $tenant = Tenant::create([
                'name' => $fullName !== '' ? $fullName : $publicName,
                'public_name' => $publicName,
                'slug' => $slug,
                'locale' => $data['locale'] ?? 'ro',
                'tenant_type' => TenantType::Venue->value,
                'status' => 'active',
                'created_by_marketplace_client_id' => $mcId,
            ]);

            // 2) Owner user. Prod users table has only `name` (no
            //    first_name/last_name — see project_users_schema_drift).
            //    Concat here; the form's two-field UI stays intact.
            $owner = User::create([
                'name' => $fullName !== '' ? $fullName : $email,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'tenant',
                'tenant_id' => $tenant->id,
                'marketplace_client_id' => $mcId,
                'email_verified_at' => now(),
            ]);

            $tenant->owner_id = $owner->id;
            $tenant->save();

            // 3) Link the venues. Whole DB catalog per operator request
            //    (2026-08-22) — no marketplace_client_id filter.
            if (!empty($venueIds)) {
                Venue::whereIn('id', $venueIds)->update(['tenant_id' => $tenant->id]);
            }

            return $tenant;
        });
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Venue owner creat')
            ->body('Cont creat + locațiile linked. Ownerul se poate autentifica cu emailul + parola configurate.')
            ->success();
    }
}
