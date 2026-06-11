<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'customer_id', 'user_id', 'type', 'content', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    const TYPE_NOTE = 'note';
    const TYPE_EMAIL = 'email';
    const TYPE_CALL = 'call';
    const TYPE_MEETING = 'meeting';
    const TYPE_PURCHASE = 'purchase';

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function scopeForCustomer($query, $customerId) { return $query->where('customer_id', $customerId); }
}
