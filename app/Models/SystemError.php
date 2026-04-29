<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single source of truth for runtime errors and warnings surfaced across
 * the platform. Populated by SystemErrorMonologHandler (logs), the global
 * exception reporter (bootstrap/app.php), and observers that mirror DB
 * failures from email/webhook/job tables.
 */
class SystemError extends Model
{
    use HasUuids;

    protected $table = 'system_errors';

    public $timestamps = false;

    protected $fillable = [
        'level',
        'level_name',
        'channel',
        'source',
        'category',
        'subcategory',
        'message',
        'fingerprint',
        'exception_class',
        'exception_file',
        'exception_line',
        'stack_trace',
        'context',
        'request_url',
        'request_method',
        'request_ip',
        'request_user_agent',
        'request_user_type',
        'request_user_id',
        'tenant_id',
        'marketplace_client_id',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledged_note',
        'created_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'exception_line' => 'integer',
        'context' => 'array',
        'request_user_id' => 'integer',
        'tenant_id' => 'integer',
        'marketplace_client_id' => 'integer',
        'acknowledged_at' => 'datetime',
        'acknowledged_by' => 'integer',
        'created_at' => 'datetime',
    ];

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function severityLabel(): string
    {
        return match (true) {
            $this->level >= 600 => 'emergency',
            $this->level >= 550 => 'alert',
            $this->level >= 500 => 'critical',
            $this->level >= 400 => 'error',
            $this->level >= 300 => 'warning',
            $this->level >= 250 => 'notice',
            $this->level >= 200 => 'info',
            default => 'debug',
        };
    }

    public function severityColor(): string
    {
        return match (true) {
            $this->level >= 500 => 'danger',
            $this->level >= 400 => 'warning',
            $this->level >= 300 => 'gray',
            default => 'info',
        };
    }
}
