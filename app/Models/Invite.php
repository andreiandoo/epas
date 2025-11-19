<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invite extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'inv_invites';

    protected $fillable = [
        'batch_id',
        'tenant_id',
        'invite_code',
        'status',
        'recipient',
        'seat_ref',
        'ticket_ref',
        'qr_data',
        'urls',
        'rendered_at',
        'emailed_at',
        'downloaded_at',
        'opened_at',
        'checked_in_at',
        'voided_at',
        'delivery_status',
        'delivery_error',
        'send_attempts',
        'last_send_attempt_at',
        'gate_ref',
        'gate_scanned_at',
        'meta',
    ];

    protected $casts = [
        'recipient' => 'array',
        'urls' => 'array',
        'meta' => 'array',
        'rendered_at' => 'datetime',
        'emailed_at' => 'datetime',
        'downloaded_at' => 'datetime',
        'opened_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'voided_at' => 'datetime',
        'last_send_attempt_at' => 'datetime',
        'gate_scanned_at' => 'datetime',
        'send_attempts' => 'integer',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (!$invite->invite_code) {
                $invite->invite_code = static::generateUniqueCode();
            }
        });
    }

    /**
     * Generate unique invitation code
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(16)); // 16-char alphanumeric
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * Relationships
     */

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InviteBatch::class, 'batch_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(InviteLog::class, 'invite_id');
    }

    /**
     * Scopes
     */

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeWithRecipient($query)
    {
        return $query->whereNotNull('recipient');
    }

    public function scopeEmailed($query)
    {
        return $query->whereNotNull('emailed_at');
    }

    public function scopeDownloaded($query)
    {
        return $query->whereNotNull('downloaded_at');
    }

    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    public function scopeCheckedIn($query)
    {
        return $query->whereNotNull('checked_in_at');
    }

    public function scopeVoid($query)
    {
        return $query->where('status', 'void');
    }

    /**
     * Helper methods - Recipient
     */

    public function getRecipientName(): ?string
    {
        return data_get($this->recipient, 'name');
    }

    public function getRecipientEmail(): ?string
    {
        return data_get($this->recipient, 'email');
    }

    public function getRecipientPhone(): ?string
    {
        return data_get($this->recipient, 'phone');
    }

    public function getRecipientCompany(): ?string
    {
        return data_get($this->recipient, 'company');
    }

    public function hasRecipient(): bool
    {
        return !empty($this->recipient);
    }

    public function setRecipient(array $data): void
    {
        $this->update(['recipient' => $data]);
    }

    /**
     * Helper methods - URLs
     */

    public function getPdfUrl(): ?string
    {
        return data_get($this->urls, 'pdf');
    }

    public function getPngUrl(): ?string
    {
        return data_get($this->urls, 'png');
    }

    public function getSignedDownloadUrl(): ?string
    {
        return data_get($this->urls, 'signed_download');
    }

    public function getSignedExpiresAt(): ?string
    {
        return data_get($this->urls, 'signed_expires_at');
    }

    public function setUrls(array $urls): void
    {
        $this->update(['urls' => $urls]);
    }

    /**
     * Status transitions
     */

    public function markAsRendered(): void
    {
        $this->update([
            'status' => 'rendered',
            'rendered_at' => now(),
        ]);

        $this->batch->incrementRendered();
    }

    public function markAsEmailed(): void
    {
        $this->update([
            'status' => 'emailed',
            'emailed_at' => now(),
            'delivery_status' => 'sent',
        ]);

        $this->batch->incrementEmailed();
    }

    public function markAsDownloaded(): void
    {
        if (!$this->downloaded_at) {
            $this->update([
                'status' => 'downloaded',
                'downloaded_at' => now(),
            ]);

            $this->batch->incrementDownloaded();
        }
    }

    public function markAsOpened(): void
    {
        if (!$this->opened_at) {
            $this->update([
                'status' => 'opened',
                'opened_at' => now(),
            ]);

            $this->batch->incrementOpened();
        }
    }

    public function markAsCheckedIn(string $gateRef = null): void
    {
        $this->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
            'gate_ref' => $gateRef,
            'gate_scanned_at' => now(),
        ]);

        $this->batch->incrementCheckedIn();
    }

    public function markAsVoid(): void
    {
        $this->update([
            'status' => 'void',
            'voided_at' => now(),
        ]);

        $this->batch->incrementVoided();
    }

    public function recordSendAttempt(bool $success, ?string $error = null): void
    {
        $this->increment('send_attempts');
        $this->update([
            'last_send_attempt_at' => now(),
            'delivery_status' => $success ? 'sent' : 'failed',
            'delivery_error' => $error,
        ]);
    }

    /**
     * Status checks
     */

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function canBeVoided(): bool
    {
        return !$this->isVoid() && !$this->checked_in_at;
    }

    public function canResend(): bool
    {
        return $this->hasRecipient() && !$this->isVoid();
    }

    public function wasEmailed(): bool
    {
        return $this->emailed_at !== null;
    }

    public function wasDownloaded(): bool
    {
        return $this->downloaded_at !== null;
    }

    public function wasOpened(): bool
    {
        return $this->opened_at !== null;
    }

    public function wasCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * Get download URL (for public access)
     */
    public function getPublicDownloadUrl(): string
    {
        return route('api.inv.download', [
            'id' => $this->id,
            'code' => $this->invite_code,
        ]);
    }
}
