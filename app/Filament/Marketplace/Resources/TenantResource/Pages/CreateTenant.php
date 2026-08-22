<?php

namespace App\Filament\Marketplace\Resources\TenantResource\Pages;

use App\Enums\TenantType;
use App\Filament\Marketplace\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Owner-user + linked-venues are extracted out of $data (all dehydrated
     * false in the schema) and picked up again in afterCreate. That keeps
     * Tenant::create clean — it only receives real tenant columns.
     */
    protected array $extracted = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->extracted = [
            'owner_first_name' => trim((string) ($data['owner_first_name'] ?? '')),
            'owner_last_name' => trim((string) ($data['owner_last_name'] ?? '')),
            'owner_email' => strtolower(trim((string) ($data['owner_email'] ?? ''))),
            'owner_password' => (string) ($data['owner_password'] ?? ''),
            'linked_venue_ids' => is_array($data['linked_venue_ids'] ?? null)
                ? array_values(array_filter(array_map('intval', $data['linked_venue_ids'])))
                : [],
        ];

        unset(
            $data['owner_first_name'],
            $data['owner_last_name'],
            $data['owner_email'],
            $data['owner_password'],
            $data['linked_venue_ids'],
        );

        // Enforce origin + tenant_type server-side even if the hidden fields
        // were tampered with — the resource is venue-only, marketplace-owned.
        $mcId = TenantResource::getMarketplaceClient()?->id;
        if (!$mcId) {
            throw ValidationException::withMessages([
                'public_name' => 'Marketplace context lipsă — reautentifică-te.',
            ]);
        }
        $data['tenant_type'] = TenantType::Venue->value;
        $data['created_by_marketplace_client_id'] = $mcId;
        $data['status'] = $data['status'] ?? 'active';

        // Email collision — the owner-user email must be free. Any existing
        // account (organizer, artist, editor, other tenant owner) means we
        // refuse creation with a clear message. Handling "link to existing"
        // is a follow-up feature the operator specifically asked to keep
        // off the table for now.
        $existing = User::where('email', $this->extracted['owner_email'])->first();
        if ($existing) {
            $role = $existing->role ?? 'necunoscut';
            $ownedTenant = Tenant::where('owner_id', $existing->id)->first();
            $usage = $ownedTenant
                ? "cont tenant existent \"" . ($ownedTenant->public_name ?? $ownedTenant->name) . '"'
                : "cont existent cu rol \"{$role}\"";
            throw ValidationException::withMessages([
                'owner_email' => "Adresa {$this->extracted['owner_email']} este deja folosită de un {$usage}. Alege alt email — linkarea la un cont existent nu e suportată din marketplace panel.",
            ]);
        }

        // Public name default → tenant name if missing (Tenant::name is
        // required in the DB but the marketplace UI only asks for
        // public_name).
        if (empty($data['name'])) {
            $data['name'] = $this->extracted['owner_first_name'] !== ''
                ? trim($this->extracted['owner_first_name'] . ' ' . $this->extracted['owner_last_name'])
                : ($data['public_name'] ?? 'Venue Owner');
        }
        if (empty($data['slug'])) {
            $base = Str::slug($data['public_name'] ?? $data['name']);
            $slug = $base;
            $i = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $base . '-' . (++$i);
            }
            $data['slug'] = $slug;
        }

        return $data;
    }

    /**
     * All post-create mutations run in a single transaction so a partial
     * failure (e.g. venue sync fails) can't leave a tenant with no owner
     * or vice versa.
     */
    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;

        DB::transaction(function () use ($tenant) {
            $mcId = $tenant->created_by_marketplace_client_id;

            // 1) Create the owner user (email pre-checked as free in the
            //    mutate step). Role='tenant' keeps this account on the same
            //    path as tenant accounts created via /admin/tenants — the
            //    AmBilet Android app checks user → tenant → venues from
            //    there.
            //
            //    Prod schema note: users.first_name / users.last_name don't
            //    exist on live even though the Model marks them fillable
            //    (leftover local-dev columns). We only write to users.name
            //    to avoid the schema-drift crash — the two form inputs are
            //    concatenated here.
            $fullName = trim($this->extracted['owner_first_name'] . ' ' . $this->extracted['owner_last_name']);
            $owner = User::create([
                'name' => $fullName !== '' ? $fullName : $this->extracted['owner_email'],
                'email' => $this->extracted['owner_email'],
                'password' => Hash::make($this->extracted['owner_password']),
                'role' => 'tenant',
                'tenant_id' => $tenant->id,
                'marketplace_client_id' => $mcId,
                'email_verified_at' => now(),
            ]);

            $tenant->owner_id = $owner->id;
            $tenant->save();

            // 2) Link the selected venues by pointing venues.tenant_id at
            //    this tenant. Per operator request (2026-08-22) the venue
            //    catalog is NOT filtered on marketplace_client_id — any
            //    venue in the DB is linkable, so we no longer clamp here.
            if (!empty($this->extracted['linked_venue_ids'])) {
                Venue::query()
                    ->whereIn('id', $this->extracted['linked_venue_ids'])
                    ->update(['tenant_id' => $tenant->id]);
            }
        });

        Notification::make()
            ->title('Venue owner creat')
            ->body('Cont creat + locațiile linked. Ownerul se poate autentifica cu emailul + parola configurate.')
            ->success()
            ->send();
    }
}
