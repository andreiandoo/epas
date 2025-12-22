<?php

namespace App\Models\Tax\Traits;

use App\Models\Tax\TaxAuditLog;
use App\Events\Tax\TaxConfigurationChanged;

trait Auditable
{
    protected static bool $auditingEnabled = true;

    protected array $auditExclude = ['updated_at', 'created_at'];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            if (static::$auditingEnabled) {
                TaxAuditLog::logCreated($model);
                static::dispatchConfigurationChangedEvent($model, 'created');
            }
        });

        static::updating(function ($model) {
            if (static::$auditingEnabled) {
                $model->auditOldValues = $model->getOriginal();
            }
        });

        static::updated(function ($model) {
            if (static::$auditingEnabled && isset($model->auditOldValues)) {
                $oldValues = collect($model->auditOldValues)
                    ->except($model->getAuditExclude())
                    ->toArray();
                $newValues = collect($model->getAttributes())
                    ->except($model->getAuditExclude())
                    ->toArray();

                // Only log if there are actual changes
                if ($oldValues !== $newValues) {
                    TaxAuditLog::logUpdated($model, $oldValues);
                    static::dispatchConfigurationChangedEvent($model, 'updated', $oldValues);
                }

                unset($model->auditOldValues);
            }
        });

        static::deleted(function ($model) {
            if (static::$auditingEnabled) {
                TaxAuditLog::logDeleted($model);
                static::dispatchConfigurationChangedEvent($model, 'deleted');
            }
        });

        // For soft deletes
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                if (static::$auditingEnabled) {
                    TaxAuditLog::logRestored($model);
                    static::dispatchConfigurationChangedEvent($model, 'restored');
                }
            });
        }
    }

    protected static function dispatchConfigurationChangedEvent($model, string $action, ?array $previousData = null): void
    {
        $taxType = match (get_class($model)) {
            'App\\Models\\Tax\\GeneralTax' => 'general',
            'App\\Models\\Tax\\LocalTax' => 'local',
            'App\\Models\\Tax\\TaxExemption' => 'exemption',
            default => 'unknown',
        };

        $user = auth()->user();

        TaxConfigurationChanged::dispatch(
            tenantId: $model->tenant_id,
            taxType: $taxType,
            taxId: $model->id,
            action: $action,
            taxData: $model->toArray(),
            previousData: $previousData,
            userId: $user?->id,
            userName: $user?->name ?? 'System'
        );
    }

    public static function disableAuditing(): void
    {
        static::$auditingEnabled = false;
    }

    public static function enableAuditing(): void
    {
        static::$auditingEnabled = true;
    }

    public static function withoutAuditing(callable $callback): mixed
    {
        static::disableAuditing();
        try {
            return $callback();
        } finally {
            static::enableAuditing();
        }
    }

    public function getAuditExclude(): array
    {
        return $this->auditExclude ?? ['updated_at', 'created_at'];
    }

    public function auditLogs()
    {
        return $this->morphMany(TaxAuditLog::class, 'auditable');
    }

    public function getRecentAuditLogs(int $limit = 10)
    {
        return $this->auditLogs()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
