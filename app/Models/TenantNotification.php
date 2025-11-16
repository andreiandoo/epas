<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantNotification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'type',
        'priority',
        'title',
        'message',
        'data',
        'action_url',
        'action_text',
        'status',
        'read_at',
        'channels',
        'sent_email',
        'sent_whatsapp',
        'sent_at',
        'related_type',
        'related_id',
    ];

    protected $casts = [
        'data' => 'array',
        'channels' => 'array',
        'sent_email' => 'boolean',
        'sent_whatsapp' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Type constants
     */
    const TYPE_MICROSERVICE_EXPIRING = 'microservice_expiring';
    const TYPE_MICROSERVICE_EXPIRED = 'microservice_expired';
    const TYPE_MICROSERVICE_SUSPENDED = 'microservice_suspended';
    const TYPE_EFACTURA_REJECTED = 'efactura_rejected';
    const TYPE_EFACTURA_FAILED = 'efactura_failed';
    const TYPE_WHATSAPP_CREDITS_LOW = 'whatsapp_credits_low';
    const TYPE_INVITATION_BATCH_COMPLETED = 'invitation_batch_completed';
    const TYPE_INVOICE_FAILED = 'invoice_failed';
    const TYPE_SYSTEM_ALERT = 'system_alert';

    /**
     * Priority constants
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Status constants
     */
    const STATUS_UNREAD = 'unread';
    const STATUS_READ = 'read';
    const STATUS_ARCHIVED = 'archived';

    /**
     * Mark as read
     */
    public function markAsRead(): void
    {
        if ($this->status === self::STATUS_UNREAD) {
            $this->update([
                'status' => self::STATUS_READ,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Mark as archived
     */
    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    /**
     * Check if notification is unread
     */
    public function isUnread(): bool
    {
        return $this->status === self::STATUS_UNREAD;
    }

    /**
     * Check if notification is urgent
     */
    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    /**
     * Scope: Unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('status', self::STATUS_UNREAD);
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
     * Scope: By priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Urgent only
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', self::PRIORITY_URGENT);
    }

    /**
     * Scope: Recent (last N days)
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
