<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intenția de a dărui un bilet, ținută între comandă și plată.
 *
 * Vezi migrația `create_tixello_gift_intents_table` pentru de ce e o tabelă
 * separată și nu un transfer direct.
 */
class TixelloGiftIntent extends Model
{
    protected $table = 'tixello_gift_intents';

    protected $fillable = [
        'order_id',
        'marketplace_client_id',
        'tixello_account_id',
        'recipient_account_id',
        'recipient_email',
        'recipient_name',
        'quantity',
        'message',
        'status',
        'error',
        'converted_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'converted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recipientAccount(): BelongsTo
    {
        return $this->belongsTo(TixelloAccount::class, 'recipient_account_id');
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(TixelloAccount::class, 'tixello_account_id');
    }
}
