<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantPage extends Model
{
    use Translatable;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'title',
        'slug',
        'content',
        'menu_location',
        'menu_order',
        'is_published',
        'meta',
    ];

    protected $casts = [
        'title' => 'array',
        'content' => 'array',
        'meta' => 'array',
        'is_published' => 'boolean',
    ];

    protected array $translatable = ['title', 'content'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TenantPage::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TenantPage::class, 'parent_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeInMenu($query, string $location)
    {
        return $query->where('menu_location', $location)
            ->where('is_published', true)
            ->orderBy('menu_order');
    }

    public function scopeHeaderMenu($query)
    {
        return $query->inMenu('header');
    }

    public function scopeFooterMenu($query)
    {
        return $query->inMenu('footer');
    }
}
