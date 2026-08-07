<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Keeps shorts out of the feed that we have no right to show, or that this
 * viewer should not be shown (D7).
 *
 * Three independent gates:
 *   - licence window: usage_expires_at is when OUR right to show it ends, which
 *     is not the same as the short's own expires_at;
 *   - territory: an allow/deny list of country codes;
 *   - age: 18+ material needs a confirmed date of birth, not an unchecked box.
 *
 * Applied as query constraints rather than a post-filter so pagination counts
 * stay honest — filtering after the fact returns short pages and breaks the cursor.
 */
class ShortRightsGuard
{
    public function apply(Builder $query, ?MarketplaceCustomer $customer, ?string $countryCode = null): Builder
    {
        $this->applyLicenceWindow($query);
        $this->applyTerritory($query, $countryCode ?? $customer?->country);
        $this->applyAgeGate($query, $customer);

        return $query;
    }

    protected function applyLicenceWindow(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereNull('usage_expires_at')
            ->orWhere('usage_expires_at', '>', now()));
    }

    /**
     * territories is {mode: 'allow'|'deny', codes: ['RO','MD']}.
     *
     * With no country to check against, only unrestricted shorts are served: an
     * unknown location must not be treated as "everywhere is fine".
     */
    protected function applyTerritory(Builder $query, ?string $countryCode): void
    {
        if (! $countryCode) {
            $query->whereNull('territories');

            return;
        }

        $code = mb_strtoupper($countryCode);

        $query->where(function (Builder $q) use ($code) {
            $q->whereNull('territories')
                // JSON containment is not portable across sqlite/pgsql/mysql, so
                // the check is a substring match on the serialised codes. Codes
                // are two letters and quoted in the JSON, which makes "RO" match
                // only "RO" and never the RO inside another word.
                ->orWhere(function (Builder $allow) use ($code) {
                    $allow->where('territories', 'like', '%"mode":"allow"%')
                        ->where('territories', 'like', '%"'.$code.'"%');
                })
                ->orWhere(function (Builder $deny) use ($code) {
                    $deny->where('territories', 'like', '%"mode":"deny"%')
                        ->where('territories', 'not like', '%"'.$code.'"%');
                });
        });
    }

    /**
     * Age-restricted material needs a verified date of birth. An anonymous
     * viewer, or one who never gave a birth date, sees only unrestricted shorts.
     */
    protected function applyAgeGate(Builder $query, ?MarketplaceCustomer $customer): void
    {
        $age = $this->ageOf($customer);

        if ($age === null) {
            $query->where('age_rating', 0);

            return;
        }

        $query->where('age_rating', '<=', $age);
    }

    protected function ageOf(?MarketplaceCustomer $customer): ?int
    {
        $birthDate = $customer?->birth_date;

        if (! $birthDate) {
            return null;
        }

        try {
            return (int) Carbon::parse($birthDate)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Single-short check, for the deep-link and share endpoints where there is
     * no query to constrain.
     */
    public function allows(Short $short, ?MarketplaceCustomer $customer, ?string $countryCode = null): bool
    {
        if ($short->usage_expires_at && $short->usage_expires_at->isPast()) {
            return false;
        }

        $age = $this->ageOf($customer);

        if (($short->age_rating ?? 0) > 0 && ($age === null || $age < $short->age_rating)) {
            return false;
        }

        $territories = $short->territories;

        if (! is_array($territories) || empty($territories['codes'])) {
            return true;
        }

        $code = mb_strtoupper((string) ($countryCode ?? $customer?->country ?? ''));

        if ($code === '') {
            return false;
        }

        $listed = in_array($code, array_map('mb_strtoupper', $territories['codes']), true);

        return ($territories['mode'] ?? 'allow') === 'allow' ? $listed : ! $listed;
    }
}
