<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DomainVerification extends Model
{
    protected $fillable = [
        'domain_id',
        'tenant_id',
        'verification_token',
        'verification_method',
        'status',
        'verified_at',
        'expires_at',
        'attempts',
        'last_attempt_at',
        'last_error',
        'verification_data',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'verification_data' => 'array',
    ];

    public const METHOD_DNS_TXT = 'dns_txt';
    public const METHOD_META_TAG = 'meta_tag';
    public const METHOD_FILE_UPLOAD = 'file_upload';

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    protected static function booted(): void
    {
        static::creating(function (DomainVerification $verification) {
            if (empty($verification->verification_token)) {
                $verification->verification_token = Str::random(64);
            }
            if (empty($verification->expires_at)) {
                $verification->expires_at = now()->addDays(7);
            }
        });
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getDnsRecordName(): string
    {
        return '_tixello-verify';
    }

    public function getDnsRecordValue(): string
    {
        return $this->verification_token;
    }

    public function getMetaTagHtml(): string
    {
        return sprintf(
            '<meta name="tixello-verification" content="%s">',
            $this->verification_token
        );
    }

    public function getFileUploadPath(): string
    {
        return '/.well-known/tixello-verify.txt';
    }

    public function getFileUploadContent(): string
    {
        return $this->verification_token;
    }

    public function markAsVerified(): void
    {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'last_error' => $error,
            'last_attempt_at' => now(),
        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        $this->update(['last_attempt_at' => now()]);
    }
}
