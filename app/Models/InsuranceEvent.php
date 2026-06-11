<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ti_events';

    public $timestamps = false;

    protected $fillable = [
        'policy_id', 'type', 'payload', 'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (!$event->created_at) {
                $event->created_at = now();
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class, 'policy_id');
    }

    public static function logIssue(InsurancePolicy $policy, array $data): self
    {
        return static::create([
            'policy_id' => $policy->id,
            'type' => 'issue',
            'payload' => $data,
        ]);
    }

    public static function logVoid(InsurancePolicy $policy, ?string $reason = null): self
    {
        return static::create([
            'policy_id' => $policy->id,
            'type' => 'void',
            'payload' => ['reason' => $reason],
        ]);
    }

    public static function logRefund(InsurancePolicy $policy, float $amount): self
    {
        return static::create([
            'policy_id' => $policy->id,
            'type' => 'refund',
            'payload' => ['amount' => $amount],
        ]);
    }

    public static function logError(InsurancePolicy $policy, string $code, string $message): self
    {
        return static::create([
            'policy_id' => $policy->id,
            'type' => 'error',
            'payload' => ['code' => $code, 'message' => $message],
        ]);
    }
}
