<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Audit-log entry for an agreement / payment lifecycle event. */
class InstallmentEvent extends Model
{
    protected $fillable = [
        'installment_agreement_id',
        'installment_payment_id',
        'type',
        'message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(InstallmentAgreement::class, 'installment_agreement_id');
    }
}
