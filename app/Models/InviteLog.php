<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inv_logs';

    public $timestamps = false; // Only created_at

    protected $fillable = [
        'invite_id',
        'type',
        'payload',
        'actor_id',
        'actor_type',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (!$log->created_at) {
                $log->created_at = now();
            }
        });
    }

    /**
     * Relationships
     */

    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class, 'invite_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scopes
     */

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $limit = 100)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Static factory methods for common log types
     */

    public static function logGenerate(Invite $invite, ?User $actor = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'generate',
            'payload' => [
                'batch_id' => $invite->batch_id,
                'invite_code' => $invite->invite_code,
            ],
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'admin' : 'system',
        ]);
    }

    public static function logRender(Invite $invite, array $urls): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'render',
            'payload' => [
                'urls' => $urls,
            ],
            'actor_type' => 'system',
        ]);
    }

    public static function logEmail(Invite $invite, array $details): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'email',
            'payload' => $details,
            'actor_type' => 'system',
        ]);
    }

    public static function logDownload(Invite $invite, string $file, ?string $ip = null, ?string $userAgent = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'download',
            'payload' => [
                'file' => $file,
            ],
            'actor_type' => 'guest',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public static function logOpen(Invite $invite, ?string $ip = null, ?string $userAgent = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'open',
            'payload' => [],
            'actor_type' => 'guest',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    public static function logVoid(Invite $invite, ?User $actor = null, ?string $reason = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'void',
            'payload' => [
                'reason' => $reason,
            ],
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'admin' : 'system',
        ]);
    }

    public static function logResend(Invite $invite, ?User $actor = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'resend',
            'payload' => [
                'attempt' => $invite->send_attempts + 1,
            ],
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'admin' : 'system',
        ]);
    }

    public static function logCheckIn(Invite $invite, string $gateRef): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'check_in',
            'payload' => [
                'gate' => $gateRef,
                'scanned_at' => now()->toIso8601String(),
            ],
            'actor_type' => 'system',
        ]);
    }

    public static function logError(Invite $invite, string $code, string $message, array $context = []): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'error',
            'payload' => [
                'code' => $code,
                'message' => $message,
                'context' => $context,
            ],
            'actor_type' => 'system',
        ]);
    }

    public static function logStatusChange(Invite $invite, string $from, string $to, ?User $actor = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'status_change',
            'payload' => [
                'from' => $from,
                'to' => $to,
            ],
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'admin' : 'system',
        ]);
    }

    public static function logRecipientUpdate(Invite $invite, array $oldData, array $newData, ?User $actor = null): self
    {
        return static::create([
            'invite_id' => $invite->id,
            'type' => 'recipient_update',
            'payload' => [
                'old' => $oldData,
                'new' => $newData,
            ],
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'admin' : 'system',
        ]);
    }
}
