<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashlessWebhookEndpoint extends Model
{
    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'url', 'secret', 'description',
        'events', 'is_active', 'last_success_at', 'last_failure_at',
        'consecutive_failures', 'meta',
    ];

    protected $casts = [
        'events'               => 'array',
        'is_active'            => 'boolean',
        'last_success_at'      => 'datetime',
        'last_failure_at'      => 'datetime',
        'consecutive_failures' => 'integer',
        'meta'                 => 'array',
    ];

    protected $hidden = ['secret'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CashlessWebhookDelivery::class, 'cashless_webhook_endpoint_id');
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    public function shouldDisable(): bool
    {
        return $this->consecutive_failures >= 10;
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeForEvent($query, string $event) { return $query->whereJsonContains('events', $event); }
}
