<?php

namespace App\Filament\Tenant\Resources\TenantTeamMemberResource\Pages;

use App\Filament\Tenant\Resources\TenantTeamMemberResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTenantTeamMember extends CreateRecord
{
    protected static string $resource = TenantTeamMemberResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = auth()->user()?->tenant?->id;

        $userData = $this->data['user'] ?? [];
        $email = $userData['email'] ?? null;
        $name = $userData['name'] ?? null;
        $initialPassword = $this->data['initial_password'] ?? null;

        if (! $email) {
            throw new \RuntimeException('Email-ul operatorului este obligatoriu.');
        }

        // Find or create the underlying User record. We deliberately do NOT
        // automatically attach the user to a different tenant if one exists —
        // an operator can belong to one tenant's leisure team only via this
        // pivot. Multi-tenant operators come later if ever needed.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name ?? $email,
                'password' => Hash::make($initialPassword ?: Str::random(16)),
                'tenant_id' => $tenantId,
            ]
        );

        // If user existed but no password was rotated, that's fine — they
        // already have one. If they exist and we got a new initial_password,
        // we DON'T overwrite (security: changing operator passwords goes
        // through the password reset flow).

        $data['tenant_id'] = $tenantId;
        $data['user_id'] = $user->id;

        if (! isset($data['accepted_at']) && ($data['status'] ?? null) === 'active') {
            $data['accepted_at'] = now();
        }

        return $data;
    }
}
