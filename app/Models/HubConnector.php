<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HubConnector extends Model
{
    use Translatable;

    protected $table = 'hub_connectors';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'slug',
        'name',
        'description',
        'logo_url',
        'icon',
        'category',
        'auth_type',
        'oauth_config',
        'supported_events',
        'supported_actions',
        'config_schema',
        'is_active',
        'is_premium',
        'price',
        'documentation_url',
        'metadata',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'oauth_config' => 'array',
        'supported_events' => 'array',
        'supported_actions' => 'array',
        'config_schema' => 'array',
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
        'price' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(HubConnection::class, 'connector_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeFree($query)
    {
        return $query->where('is_premium', false);
    }

    public function scopePremium($query)
    {
        return $query->where('is_premium', true);
    }
}
