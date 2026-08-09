<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legatura dintre un cont Tixello si un cont din lumea unui partener.
 *
 * Exista DOAR cu acordul explicit al omului — `consent_source` spune cum s-a
 * nascut. Aplicatia nu creeaza legaturi din proprie initiativa si nu
 * interogheaza niciodata bazele partenerilor ca sa afle unde mai are cineva
 * cont: ar fi enumerare de conturi si ar dezvalui relatia dintre platforme.
 */
class TixelloAccountLink extends Model
{
    protected $fillable = [
        'tixello_account_id', 'kind', 'marketplace_client_id', 'tenant_id',
        'linked_id', 'consent_source', 'consented_at', 'consent_ip', 'status', 'revoked_at',
    ];

    protected $casts = [
        'consented_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(TixelloAccount::class, 'tixello_account_id');
    }

    public function marketplaceCustomer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'linked_id');
    }

    public function marketplaceOrganizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'linked_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeOfKind($q, string $kind)
    {
        return $q->where('kind', $kind);
    }
}
