<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractVersion extends Model
{
    protected $fillable = [
        'tenant_id',
        'contract_template_id',
        'version_number',
        'contract_number',
        'file_path',
        'status',
        'generated_at',
        'sent_at',
        'viewed_at',
        'signed_at',
        'signature_ip',
        'signature_data',
        'tenant_data_snapshot',
        'notes',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'signed_at' => 'datetime',
        'tenant_data_snapshot' => 'array',
    ];

    /**
     * Get the tenant that owns this contract version
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the template used for this version
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    /**
     * Check if this version is signed
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed' && $this->signed_at !== null;
    }

    /**
     * Get status badge color
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'generated' => 'info',
            'sent' => 'warning',
            'viewed' => 'primary',
            'signed' => 'success',
            'expired' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'generated' => 'Generated',
            'sent' => 'Sent',
            'viewed' => 'Viewed',
            'signed' => 'Signed',
            'expired' => 'Expired',
            default => ucfirst($this->status),
        };
    }
}
