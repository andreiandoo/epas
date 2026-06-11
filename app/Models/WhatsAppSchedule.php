<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSchedule extends Model
{
    use HasFactory;

    protected $table = 'wa_schedules';

    protected $fillable = [
        'tenant_id',
        'message_type',
        'run_at',
        'payload',
        'status',
        'correlation_ref',
        'result',
        'executed_at',
    ];

    protected $casts = [
        'run_at' => 'datetime',
        'payload' => 'array',
        'result' => 'array',
        'executed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_RUN = 'run';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_FAILED = 'failed';

    /**
     * Message type constants
     */
    const TYPE_REMINDER_D7 = 'reminder_d7';
    const TYPE_REMINDER_D3 = 'reminder_d3';
    const TYPE_REMINDER_D1 = 'reminder_d1';
    const TYPE_PROMO = 'promo';
    const TYPE_OTHER = 'other';

    /**
     * Check if schedule is pending execution
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->run_at <= now();
    }

    /**
     * Check if already executed
     */
    public function isExecuted(): bool
    {
        return in_array($this->status, [self::STATUS_RUN, self::STATUS_SKIPPED, self::STATUS_FAILED]);
    }

    /**
     * Mark as run
     */
    public function markAsRun(array $result = []): void
    {
        $this->update([
            'status' => self::STATUS_RUN,
            'result' => $result,
            'executed_at' => now(),
        ]);
    }

    /**
     * Mark as skipped
     */
    public function markAsSkipped(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'result' => ['reason' => $reason],
            'executed_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'result' => ['error' => $error],
            'executed_at' => now(),
        ]);
    }

    /**
     * Create reminder schedules for an order
     */
    public static function createReminders(
        string $tenantId,
        string $orderRef,
        \DateTime $eventStartAt,
        array $recipientData,
        string $timezone = 'Europe/Bucharest'
    ): array {
        $created = [];

        // Calculate reminder times in tenant timezone
        $eventDateTime = (clone $eventStartAt)->setTimezone(new \DateTimeZone($timezone));

        $reminders = [
            ['type' => self::TYPE_REMINDER_D7, 'days' => 7],
            ['type' => self::TYPE_REMINDER_D3, 'days' => 3],
            ['type' => self::TYPE_REMINDER_D1, 'days' => 1],
        ];

        foreach ($reminders as $reminder) {
            $runAt = (clone $eventDateTime)->modify("-{$reminder['days']} days");

            // Skip if reminder time has already passed
            if ($runAt < new \DateTime('now', new \DateTimeZone($timezone))) {
                continue;
            }

            // Check if already scheduled (idempotency)
            $existing = static::where('tenant_id', $tenantId)
                ->where('correlation_ref', $orderRef)
                ->where('message_type', $reminder['type'])
                ->first();

            if ($existing) {
                continue;
            }

            $schedule = static::create([
                'tenant_id' => $tenantId,
                'message_type' => $reminder['type'],
                'run_at' => $runAt,
                'correlation_ref' => $orderRef,
                'payload' => $recipientData,
                'status' => self::STATUS_SCHEDULED,
            ]);

            $created[] = $schedule;
        }

        return $created;
    }

    /**
     * Scope: Pending schedules ready to run
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->where('run_at', '<=', now())
            ->orderBy('run_at', 'asc');
    }

    /**
     * Scope: By tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: By message type
     */
    public function scopeByType($query, string $messageType)
    {
        return $query->where('message_type', $messageType);
    }

    /**
     * Scope: Upcoming schedules
     */
    public function scopeUpcoming($query, int $hours = 24)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->whereBetween('run_at', [now(), now()->addHours($hours)])
            ->orderBy('run_at', 'asc');
    }
}
