<?php

namespace App\Services\Marketplace;

use App\Enums\TenantType;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * One password per email for the accounts a person holds on a marketplace:
 * client (MarketplaceCustomer), organizer (MarketplaceOrganizer) and venue
 * owner (core User whose venue tenant partners with the marketplace).
 *
 * Client and organizer accounts can be registered without confirming the
 * email, so a password only spreads from actions that prove ownership:
 *  - reset link or admin panel          -> every linked account (applyToAll)
 *  - change with the current password   -> accounts that already shared it (applyToMatching)
 *  - venue owner password change (core) -> every linked account (applyFromVenueOwner)
 * Registration never overwrites anything: the new account has to reuse the
 * existing password (conflictingAccounts). Accounts without a password
 * (guest clients) are left alone.
 */
class AccountPasswordSync
{
    private const LABELS = [
        'customer' => 'client',
        'organizer' => 'organizator',
        'venue-owner' => 'locație',
    ];

    /**
     * Accounts on this marketplace that share the email, keyed by type.
     *
     * @return array<string, Model>
     */
    public function linkedAccounts(int $marketplaceClientId, string $email, ?Model $except = null): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return [];
        }

        $accounts = [
            'customer' => MarketplaceCustomer::where('marketplace_client_id', $marketplaceClientId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first(),
            'organizer' => MarketplaceOrganizer::where('marketplace_client_id', $marketplaceClientId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first(),
            'venue-owner' => $this->venueOwner($marketplaceClientId, $email),
        ];

        return array_filter($accounts, fn (?Model $account) => $account !== null
            && !($except
                && get_class($account) === get_class($except)
                && (string) $account->getKey() === (string) $except->getKey()));
    }

    /**
     * Reset link or admin panel: the new password goes to every linked account.
     *
     * @return list<string> types that were updated
     */
    public function applyToAll(int $marketplaceClientId, string $email, string $plainPassword, ?Model $except = null, bool $revokeSessions = false): array
    {
        $hash = null;
        $updated = [];

        foreach ($this->linkedAccounts($marketplaceClientId, $email, $except) as $type => $account) {
            if (!$this->hasPassword($account)) {
                continue;
            }
            $hash ??= Hash::make($plainPassword);
            $this->writeHash($account, $hash, $revokeSessions);
            $updated[] = $type;
        }

        $this->logSync('reset_or_admin', $marketplaceClientId, $except, $updated);

        return $updated;
    }

    /**
     * Change with the current password: only accounts that already used that
     * password follow, so knowing one account's password never takes over
     * another account.
     *
     * @return array{updated: list<string>, different: list<string>}
     */
    public function applyToMatching(int $marketplaceClientId, string $email, string $currentPassword, string $newPassword, Model $except): array
    {
        $hash = null;
        $updated = [];
        $different = [];

        foreach ($this->linkedAccounts($marketplaceClientId, $email, $except) as $type => $account) {
            if (!$this->hasPassword($account)) {
                continue;
            }
            if (!$this->passwordMatches($account, $currentPassword)) {
                $different[] = $type;
                continue;
            }
            $hash ??= Hash::make($newPassword);
            $this->writeHash($account, $hash, false);
            $updated[] = $type;
        }

        $this->logSync('change', $marketplaceClientId, $except, $updated);

        return ['updated' => $updated, 'different' => $different];
    }

    /**
     * Registration: linked accounts whose password differs from the chosen one.
     *
     * @return list<string>
     */
    public function conflictingAccounts(int $marketplaceClientId, string $email, string $plainPassword, string $registeringType): array
    {
        $conflicts = [];

        foreach ($this->linkedAccounts($marketplaceClientId, $email) as $type => $account) {
            if ($type === $registeringType || !$this->hasPassword($account)) {
                continue;
            }
            if (!$this->passwordMatches($account, $plainPassword)) {
                $conflicts[] = $type;
            }
        }

        return $conflicts;
    }

    /**
     * A venue owner's password changed anywhere in core (Tixello panel, admin,
     * invite link, /venue settings): copy it to the client and organizer
     * accounts with the same email on every marketplace the venue partners with.
     */
    public function applyFromVenueOwner(User $user): void
    {
        $hash = $user->getAttributes()['password'] ?? null;
        $previous = $user->getRawOriginal('password');

        // A login-time rehash (hashing cost changed) rewrites the same password;
        // it must not overwrite the other accounts.
        if (!$hash || ($previous && Hash::needsRehash($previous))) {
            return;
        }

        $tenant = $this->tenantOf($user);
        if (!$tenant || !$this->isVenueTenant($tenant)) {
            return;
        }

        $venueIds = $tenant->venues()->pluck('id');
        if ($venueIds->isEmpty()) {
            return;
        }

        $marketplaceIds = Venue::whereIn('id', $venueIds)
            ->where('is_partner', true)
            ->whereNotNull('marketplace_client_id')
            ->pluck('marketplace_client_id')
            ->merge(DB::table('marketplace_venue_partners')
                ->whereIn('venue_id', $venueIds)
                ->where('is_partner', true)
                ->pluck('marketplace_client_id'))
            ->filter()
            ->unique();

        foreach ($marketplaceIds as $marketplaceClientId) {
            $updated = [];
            foreach ($this->linkedAccounts((int) $marketplaceClientId, (string) $user->email, $user) as $type => $account) {
                if ($type === 'venue-owner' || !$this->hasPassword($account)) {
                    continue;
                }
                $this->writeHash($account, $hash, false);
                $updated[] = $type;
            }
            $this->logSync('venue_owner', (int) $marketplaceClientId, $user, $updated);
        }
    }

    /**
     * Line for the reset email when the new password will also apply elsewhere.
     */
    public function resetEmailNotice(int $marketplaceClientId, string $email, Model $except): ?string
    {
        $types = array_keys(array_filter(
            $this->linkedAccounts($marketplaceClientId, $email, $except),
            fn (Model $account) => $this->hasPassword($account)
        ));

        return $types
            ? 'Parola nouă se va aplica și pentru ' . self::names($types) . ' cu această adresă de email.'
            : null;
    }

    /**
     * Error for a registration blocked by conflictingAccounts().
     */
    public static function conflictMessage(array $types): string
    {
        $accounts = implode(' și un ', array_map(fn ($type) => 'cont de ' . self::label($type), $types));
        $which = count($types) > 1 ? 'acele conturi' : 'acel cont';

        return "Există deja un {$accounts} cu acest email, cu altă parolă. Folosește aceeași parolă ca la {$which} sau resetează-o din „Am uitat parola”.";
    }

    public static function appliedNotice(array $types): ?string
    {
        return $types ? 'Parola nouă se aplică și pentru ' . self::names($types) . '.' : null;
    }

    /**
     * Notice for the settings page after applyToMatching(), or null.
     */
    public static function changeNotice(array $result): ?string
    {
        $parts = [];
        if (!empty($result['updated'])) {
            $parts[] = self::appliedNotice($result['updated']);
        }
        if (!empty($result['different'])) {
            $verb = count($result['different']) > 1
                ? 'au altă parolă și au rămas neschimbate'
                : 'are altă parolă și a rămas neschimbat';
            $parts[] = ucfirst(self::names($result['different'])) . " cu același email {$verb}. Ca să folosești o singură parolă peste tot, resetează-o din „Am uitat parola”.";
        }

        return $parts ? implode(' ', $parts) : null;
    }

    private static function names(array $types): string
    {
        return implode(' și ', array_map(fn ($type) => 'contul de ' . self::label($type), $types));
    }

    private static function label(string $type): string
    {
        return self::LABELS[$type] ?? $type;
    }

    private function venueOwner(int $marketplaceClientId, string $email): ?User
    {
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return null;
        }

        $tenant = $this->tenantOf($user);
        if (!$tenant || !$this->isVenueTenant($tenant)) {
            return null;
        }

        return $tenant->venues()->partnerOfMarketplace($marketplaceClientId)->exists() ? $user : null;
    }

    private function tenantOf(User $user): ?Tenant
    {
        // Raw attribute: User::getAttribute('tenant_id') is swapped in demo mode.
        $tenantId = $user->getAttributes()['tenant_id'] ?? null;

        return $tenantId ? Tenant::find($tenantId) : $user->ownedTenant;
    }

    private function isVenueTenant(Tenant $tenant): bool
    {
        $type = $tenant->tenant_type instanceof \BackedEnum
            ? $tenant->tenant_type->value
            : $tenant->tenant_type;

        return $type === TenantType::Venue->value;
    }

    private function hasPassword(Model $account): bool
    {
        $attributes = $account->getAttributes();

        return !empty($attributes['password'])
            || ($account instanceof MarketplaceCustomer && !empty($attributes['wp_password_hash']));
    }

    private function passwordMatches(Model $account, string $plainPassword): bool
    {
        $hash = $account->getAttributes()['password'] ?? null;
        try {
            if ($hash && Hash::check($plainPassword, $hash)) {
                return true;
            }
        } catch (\RuntimeException $e) {
            // Stored value is not a hash this app can verify.
        }

        // Clients imported from WordPress may only have the old hash (same fallback as login).
        return $account instanceof MarketplaceCustomer
            && !empty($account->getAttributes()['wp_password_hash'])
            && $account->verifyAndMigrateWpPassword($plainPassword);
    }

    private function writeHash(Model $account, string $hash, bool $revokeSessions): void
    {
        $values = ['password' => $hash];
        if ($account instanceof MarketplaceCustomer) {
            $values['wp_password_hash'] = null;
        }

        // Query-level update: no model events (no sync loops) and no 'hashed' cast.
        $account->newQuery()->whereKey($account->getKey())->update($values);
        $account->setRawAttributes(array_merge($account->getAttributes(), $values), true);

        if ($revokeSessions) {
            $tokens = $account->tokens();
            if ($account instanceof User) {
                // Only the marketplace venue sessions; other API tokens of the core user stay.
                $tokens->where('name', 'like', 'venue-owner-%');
            }
            $tokens->delete();
        }
    }

    private function logSync(string $reason, int $marketplaceClientId, ?Model $source, array $updated): void
    {
        if (!$updated) {
            return;
        }

        Log::info('Account password synced', [
            'reason' => $reason,
            'marketplace_client_id' => $marketplaceClientId,
            'source' => $source ? class_basename($source) . '#' . $source->getKey() : null,
            'updated' => $updated,
        ]);
    }
}
