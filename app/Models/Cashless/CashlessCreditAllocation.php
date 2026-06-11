<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessCreditAllocation extends Model
{
    protected $table = 'cashless_credit_allocations';

    protected $fillable = [
        'tenant_id', 'festival_edition_id', 'cashless_account_id', 'allocated_by',
        'allocation_type', 'amount_cents', 'total_allocated_cents',
        'period_start', 'period_end', 'is_active', 'notes', 'meta',
    ];

    protected $casts = [
        'amount_cents'          => 'integer',
        'total_allocated_cents' => 'integer',
        'period_start'          => 'date',
        'period_end'            => 'date',
        'is_active'             => 'boolean',
        'meta'                  => 'array',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function edition(): BelongsTo { return $this->belongsTo(FestivalEdition::class, 'festival_edition_id'); }
    public function account(): BelongsTo { return $this->belongsTo(CashlessAccount::class, 'cashless_account_id'); }
    public function allocatedByUser(): BelongsTo { return $this->belongsTo(User::class, 'allocated_by'); }

    public function isDue(): bool
    {
        if (! $this->is_active) return false;
        if ($this->allocation_type === 'one_time' && $this->total_allocated_cents > 0) return false;
        if ($this->period_end && now()->gt($this->period_end)) return false;
        return true;
    }

    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeDaily($query) { return $query->where('allocation_type', 'daily'); }
}
