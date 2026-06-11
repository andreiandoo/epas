<?php

namespace App\Models\Tax;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class TaxAuditLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'user_id',
        'user_name',
        'ip_address',
        'user_agent',
        'reason',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // Relationships

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForTax(Builder $query, string $type, int $id): Builder
    {
        return $query->where('auditable_type', $type)->where('auditable_id', $id);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    // Helpers

    public function getChangedFields(): array
    {
        $changed = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        foreach ($new as $key => $value) {
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $changed[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changed;
    }

    public function getEventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
            default => ucfirst($this->event),
        };
    }

    public function getEventColor(): string
    {
        return match ($this->event) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            'restored' => 'info',
            default => 'gray',
        };
    }

    public function getTaxTypeLabel(): string
    {
        return match ($this->auditable_type) {
            'App\\Models\\Tax\\GeneralTax' => 'General Tax',
            'App\\Models\\Tax\\LocalTax' => 'Local Tax',
            'App\\Models\\Tax\\TaxExemption' => 'Tax Exemption',
            default => class_basename($this->auditable_type),
        };
    }

    // Static factory methods

    public static function logCreated(Model $tax, ?string $reason = null): self
    {
        return static::createLog($tax, 'created', null, $tax->toArray(), $reason);
    }

    public static function logUpdated(Model $tax, array $oldValues, ?string $reason = null): self
    {
        return static::createLog($tax, 'updated', $oldValues, $tax->toArray(), $reason);
    }

    public static function logDeleted(Model $tax, ?string $reason = null): self
    {
        return static::createLog($tax, 'deleted', $tax->toArray(), null, $reason);
    }

    public static function logRestored(Model $tax, ?string $reason = null): self
    {
        return static::createLog($tax, 'restored', null, $tax->toArray(), $reason);
    }

    protected static function createLog(
        Model $tax,
        string $event,
        ?array $oldValues,
        ?array $newValues,
        ?string $reason = null
    ): self {
        $user = auth()->user();
        $request = request();

        return static::create([
            'tenant_id' => $tax->tenant_id,
            'auditable_type' => get_class($tax),
            'auditable_id' => $tax->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'reason' => $reason,
        ]);
    }
}
