<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'wa_messages';

    protected $fillable = [
        'tenant_id',
        'type',
        'to_phone',
        'template_name',
        'variables',
        'status',
        'error_code',
        'error_message',
        'correlation_ref',
        'bsp_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'cost',
    ];

    protected $casts = [
        'variables' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'cost' => 'decimal:4',
    ];

    /**
     * Status constants
     */
    const STATUS_QUEUED = 'queued';
    const STATUS_SENT = 'sent';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_READ = 'read';
    const STATUS_FAILED = 'failed';

    /**
     * Type constants
     */
    const TYPE_ORDER_CONFIRM = 'order_confirm';
    const TYPE_REMINDER = 'reminder';
    const TYPE_PROMO = 'promo';
    const TYPE_OTP = 'otp';
    const TYPE_OTHER = 'other';

    /**
     * Check if message was successfully delivered
     */
    public function isDelivered(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_READ]);
    }

    /**
     * Check if message failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark as sent
     */
    public function markAsSent(string $bspMessageId, ?float $cost = null): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'bsp_message_id' => $bspMessageId,
            'sent_at' => now(),
            'cost' => $cost,
        ]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => self::STATUS_READ,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    /**
     * Check if message already exists (idempotency)
     */
    public static function alreadyExists(string $tenantId, string $correlationRef, string $templateName): bool
    {
        return static::where('tenant_id', $tenantId)
            ->where('correlation_ref', $correlationRef)
            ->where('template_name', $templateName)
            ->exists();
    }

    /**
     * Scope: By tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: By type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: By status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Failed messages
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope: Recent messages (last N days)
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get template relation (if exists)
     */
    public function template()
    {
        return $this->hasOne(WhatsAppTemplate::class, 'name', 'template_name')
            ->where('tenant_id', $this->tenant_id);
    }
}
