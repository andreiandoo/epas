<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TenantEventCategory extends Model
{
    use Translatable;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'icon',
        'image',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_tenant_event_category');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
