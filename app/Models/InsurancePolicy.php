<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsurancePolicy extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ti_policies';

    protected $fillable = [
        'tenant_id', 'order_ref', 'ticket_ref', 'insurer', 'premium_amount',
        'currency', 'tax_amount', 'status', 'policy_number', 'policy_doc_url',
        'policy_doc_path', 'provider_payload', 'error_message', 'refund_amount',
        'refunded_at', 'voided_at', 'metadata',
    ];

    protected $casts = [
        'premium_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'provider_payload' => 'array',
        'metadata' => 'array',
        'refunded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(InsuranceEvent::class, 'policy_id');
    }

    public function markAsIssued(string $policyNumber, ?string $docUrl = null): void
    {
        $this->update([
            'status' => 'issued',
            'policy_number' => $policyNumber,
            'policy_doc_url' => $docUrl,
        ]);
    }

    public function markAsVoided(): void
    {
        $this->update([
            'status' => 'voided',
            'voided_at' => now(),
        ]);
    }

    public function markAsRefunded(float $amount): void
    {
        $this->update([
            'status' => 'refunded',
            'refund_amount' => $amount,
            'refunded_at' => now(),
        ]);
    }

    public function markAsError(string $message): void
    {
        $this->update([
            'status' => 'error',
            'error_message' => $message,
        ]);
    }

    public function canBeVoided(): bool
    {
        return in_array($this->status, ['pending', 'issued']);
    }

    public function canBeRefunded(): bool
    {
        return $this->status === 'issued';
    }
}
