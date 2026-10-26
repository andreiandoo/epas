<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O înregistrare în registrul acordurilor: cine, ce text a acceptat sau retras, când și de unde. */
class TixelloBuyerConsent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id', 'email', 'type', 'action', 'text_version', 'text',
        'source', 'tenant_id', 'order_id', 'ip', 'user_agent',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(TixelloBuyerAccount::class, 'account_id');
    }
}
