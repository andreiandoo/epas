<?php

namespace App\Models\Cashless;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessGdprRequest extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'request_type', 'status',
        'requested_at', 'processed_at', 'processed_by',
        'export_file_path', 'notes', 'meta',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'meta'         => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function processedByUser(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function isPending(): bool { return $this->status === 'pending'; }

    public function scopePending($query) { return $query->where('status', 'pending'); }
}
