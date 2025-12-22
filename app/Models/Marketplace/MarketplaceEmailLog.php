<?php

namespace App\Models\Marketplace;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MarketplaceEmailLog Model
 *
 * Logs all emails sent by a marketplace tenant.
 */
class MarketplaceEmailLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'organizer_id',
        'template_id',
        'recipient_email',
        'recipient_name',
        'recipient_type',
        'subject',
        'body',
        'status',
        'sent_at',
        'failed_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Recipient type constants
     */
    public const RECIPIENT_CUSTOMER = 'customer';
    public const RECIPIENT_ORGANIZER = 'organizer';
    public const RECIPIENT_ADMIN = 'admin';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the marketplace tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Alias for tenant.
     */
    public function marketplace(): BelongsTo
    {
        return $this->tenant();
    }

    /**
     * Get the organizer if this email was related to an organizer.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'organizer_id');
    }

    /**
     * Get the email template used.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEmailTemplate::class, 'template_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope to a specific marketplace.
     */
    public function scopeForMarketplace($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to sent emails.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope to failed emails.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to pending emails.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    // =========================================================================
    // STATUS HELPERS
    // =========================================================================

    /**
     * Mark email as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark email as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Get status color for display.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_PENDING => 'warning',
            default => 'gray',
        };
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_PENDING => 'Pending',
            default => ucfirst($this->status),
        };
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Create a log entry for a sent email.
     */
    public static function logSent(
        int $tenantId,
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $body,
        string $recipientType = self::RECIPIENT_CUSTOMER,
        ?int $templateId = null,
        ?int $organizerId = null,
        array $metadata = []
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'organizer_id' => $organizerId,
            'template_id' => $templateId,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'recipient_type' => $recipientType,
            'subject' => $subject,
            'body' => $body,
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a log entry for a failed email.
     */
    public static function logFailed(
        int $tenantId,
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $body,
        string $errorMessage,
        string $recipientType = self::RECIPIENT_CUSTOMER,
        ?int $templateId = null,
        ?int $organizerId = null,
        array $metadata = []
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'organizer_id' => $organizerId,
            'template_id' => $templateId,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'recipient_type' => $recipientType,
            'subject' => $subject,
            'body' => $body,
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'metadata' => $metadata,
        ]);
    }
}
