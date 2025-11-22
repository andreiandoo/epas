<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id','number','description','issue_date','period_start','period_end','due_date',
        'subtotal','vat_rate','vat_amount','amount','currency','status','meta',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date'   => 'date',
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'meta'       => 'array',
    ];

    public function tenant(): BelongsTo {
        return $this->belongsTo(Tenant::class);
    }

    // scopes utile
    public function scopeOutstanding($q){ return $q->where('status','outstanding'); }
    public function scopePaid($q){ return $q->where('status','paid'); }
}
