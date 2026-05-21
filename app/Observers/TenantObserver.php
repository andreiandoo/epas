<?php

namespace App\Observers;

use App\Enums\TenantType;
use App\Models\Tenant;
use Illuminate\Support\Arr;

class TenantObserver
{
    /**
     * Populate tenant_type-specific defaults (features JSON) before persisting
     * a newly-created tenant. Existing features keys are NEVER overwritten —
     * we only fill gaps, so admin-overridden flags survive subsequent
     * re-saves and explicit feature toggles set during onboarding remain
     * authoritative.
     */
    public function creating(Tenant $tenant): void
    {
        $type = $tenant->tenant_type;
        if (! $type instanceof TenantType) {
            return;
        }

        $defaults = $type->defaultFeatures();
        if (empty($defaults)) {
            return;
        }

        $existing = is_array($tenant->features) ? $tenant->features : [];
        $tenant->features = $this->mergeRecursiveDefaults($existing, $defaults);
    }

    /**
     * Backfill leisure features when an existing tenant is converted to
     * tenant_type=leisure post-creation. Mirrors creating() behaviour so a
     * tenant migrated from another type still gets sane defaults without
     * clobbering anything the operator has already configured.
     */
    public function updating(Tenant $tenant): void
    {
        if (! $tenant->isDirty('tenant_type')) {
            return;
        }

        $type = $tenant->tenant_type;
        if (! $type instanceof TenantType) {
            return;
        }

        $defaults = $type->defaultFeatures();
        if (empty($defaults)) {
            return;
        }

        $existing = is_array($tenant->features) ? $tenant->features : [];
        $tenant->features = $this->mergeRecursiveDefaults($existing, $defaults);
    }

    /**
     * Merge defaults into existing only for keys that don't yet exist (deep).
     * Existing values always win — this is intentionally NOT array_merge_recursive,
     * which would duplicate scalars into arrays.
     */
    protected function mergeRecursiveDefaults(array $existing, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $existing)) {
                $existing[$key] = $value;
                continue;
            }
            if (is_array($value) && is_array($existing[$key])) {
                $existing[$key] = $this->mergeRecursiveDefaults($existing[$key], $value);
            }
            // scalar existing value wins — do not overwrite
        }
        return $existing;
    }
}
