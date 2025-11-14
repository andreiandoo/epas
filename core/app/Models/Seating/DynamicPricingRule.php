<?php

namespace App\Models\Seating;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicPricingRule extends Model
{
    protected $fillable = [
        'tenant_id',
        'scope',
        'scope_ref',
        'strategy',
        'params',
        'active',
    ];

    protected $casts = [
        'params' => 'array',
        'active' => 'boolean',
    ];

    protected $attributes = [
        'active' => true,
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($rule) {
            if (!$rule->tenant_id && auth()->check() && isset(auth()->user()->tenant_id)) {
                $rule->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /**
     * Relationships
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(DynamicPriceOverride::class, 'source_rule_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForScope($query, string $scope, ?string $scopeRef = null)
    {
        $query->where('scope', $scope);

        if ($scopeRef !== null) {
            $query->where('scope_ref', $scopeRef);
        }

        return $query;
    }

    /**
     * Activate the rule
     */
    public function activate(): bool
    {
        $this->active = true;
        return $this->save();
    }

    /**
     * Deactivate the rule
     */
    public function deactivate(): bool
    {
        $this->active = false;
        return $this->save();
    }

    /**
     * Get param value
     */
    public function getParam(string $key, $default = null)
    {
        return data_get($this->params, $key, $default);
    }
}
