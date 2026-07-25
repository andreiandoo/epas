<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantReview extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'event_id', 'rating', 'title', 'body',
        'status', 'is_anonymous', 'recommend', 'aspects',
    ];

    protected $casts = [
        'aspects' => 'array',
        'is_anonymous' => 'boolean',
        'recommend' => 'boolean',
        'rating' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
