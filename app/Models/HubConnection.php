<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HubConnection extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'hub_connections';

    protected $fillable = [
        'tenant_id',
        'connector_id',
        'status',
        'credentials',
        'token_expires_at',
        'config',
        'last_sync_at',
        'last_error',
        'error_count',
        'created_by',
    ];

    protected $casts = [
        'config' => 'array',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(HubConnector::class, 'connector_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(HubEvent::class, 'connection_id');
    }

    public function syncJobs(): HasMany
    {
        return $this->hasMany(HubSyncJob::class, 'connection_id');
    }

    /**
     * Get decrypted credentials
     */
    public function getDecryptedCredentials(): ?array
    {
        if (!$this->credentials) {
            return null;
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted credentials
     */
    public function setCredentials(array $credentials): void
    {
        $this->credentials = Crypt::encryptString(json_encode($credentials));
        $this->save();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->isPast();
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'error_count' => 0,
            'last_error' => null,
        ]);
    }

    public function disable(): void
    {
        $this->update(['status' => 'disabled']);
    }

    public function markError(string $error): void
    {
        $this->increment('error_count');
        $this->update([
            'last_error' => $error,
            'status' => $this->error_count >= 5 ? 'error' : $this->status,
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'config'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Hub connection {$eventName}")
            ->useLogName('tenant');
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->put('tenant_id', $this->tenant_id);
    }
}
