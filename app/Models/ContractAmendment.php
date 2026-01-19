<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAmendment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'contract_version_id',
        'amendment_number',
        'title',
        'description',
        'content',
        'file_path',
        'status',
        'sent_at',
        'signed_at',
        'signed_by',
        'signature_data',
        'signature_ip',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    /**
     * Generate the next amendment number for a tenant
     */
    public static function generateNumber(Tenant $tenant): string
    {
        $count = static::where('tenant_id', $tenant->id)->count() + 1;
        return ($tenant->contract_number ?? 'CTR-' . $tenant->id) . '-AMD-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Check if amendment is signed
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed' && $this->signed_at !== null;
    }
}
