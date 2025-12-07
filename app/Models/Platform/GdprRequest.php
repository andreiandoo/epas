<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprRequest extends Model
{
    protected $fillable = [
        'request_type',
        'customer_id',
        'email',
        'request_source',
        'status',
        'affected_data',
        'notes',
        'requested_at',
        'processed_at',
        'completed_at',
        'processed_by',
        'export_data',
    ];

    protected $casts = [
        'affected_data' => 'array',
        'export_data' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Request types
    const TYPE_EXPORT = 'export';
    const TYPE_DELETION = 'deletion';
    const TYPE_RECTIFICATION = 'rectification';
    const TYPE_ACCESS = 'access';

    // Statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    // Sources
    const SOURCE_CUSTOMER = 'customer';
    const SOURCE_ADMIN = 'admin';
    const SOURCE_AUTOMATED = 'automated';

    const REQUEST_TYPES = [
        self::TYPE_EXPORT => 'Data Export',
        self::TYPE_DELETION => 'Data Deletion',
        self::TYPE_RECTIFICATION => 'Data Rectification',
        self::TYPE_ACCESS => 'Data Access',
    ];

    const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CoreCustomer::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('request_type', $type);
    }

    // Status management
    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processed_at' => now(),
        ]);
    }

    public function markCompleted(array $exportData = null): void
    {
        $updates = [
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ];

        if ($exportData) {
            $updates['export_data'] = $exportData;
        }

        $this->update($updates);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'notes' => $this->notes . "\nFailed: " . $reason,
        ]);
    }

    // Process the request
    public function process(): bool
    {
        $this->markProcessing();

        try {
            $customer = $this->findCustomer();

            if (!$customer) {
                $this->markFailed('Customer not found');
                return false;
            }

            switch ($this->request_type) {
                case self::TYPE_EXPORT:
                case self::TYPE_ACCESS:
                    $exportData = $customer->exportPersonalData();
                    $this->update(['affected_data' => array_keys($exportData)]);
                    $this->markCompleted($exportData);
                    break;

                case self::TYPE_DELETION:
                    $affectedData = [
                        'email' => $customer->email ? 'deleted' : 'n/a',
                        'phone' => $customer->phone ? 'deleted' : 'n/a',
                        'name' => ($customer->first_name || $customer->last_name) ? 'deleted' : 'n/a',
                        'events_anonymized' => $customer->events()->count(),
                    ];
                    $customer->anonymizeForGdpr();
                    $this->update(['affected_data' => $affectedData]);
                    $this->markCompleted();
                    break;

                case self::TYPE_RECTIFICATION:
                    // Rectification requires manual handling
                    $this->update([
                        'notes' => $this->notes . "\nRequires manual review and correction.",
                    ]);
                    $this->markCompleted();
                    break;
            }

            return true;

        } catch (\Exception $e) {
            $this->markFailed($e->getMessage());
            return false;
        }
    }

    protected function findCustomer(): ?CoreCustomer
    {
        if ($this->customer_id) {
            return CoreCustomer::find($this->customer_id);
        }

        if ($this->email) {
            return CoreCustomer::findByEmail($this->email);
        }

        return null;
    }

    // Static factory
    public static function createExportRequest(string $email, string $source = self::SOURCE_ADMIN): self
    {
        $customer = CoreCustomer::findByEmail($email);

        return static::create([
            'request_type' => self::TYPE_EXPORT,
            'customer_id' => $customer?->id,
            'email' => $email,
            'request_source' => $source,
            'status' => self::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    public static function createDeletionRequest(string $email, string $source = self::SOURCE_ADMIN): self
    {
        $customer = CoreCustomer::findByEmail($email);

        return static::create([
            'request_type' => self::TYPE_DELETION,
            'customer_id' => $customer?->id,
            'email' => $email,
            'request_source' => $source,
            'status' => self::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }
}
