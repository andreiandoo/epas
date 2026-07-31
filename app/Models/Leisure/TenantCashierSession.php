<?php

namespace App\Models\Leisure;

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cashier shift on a leisure tenant's operator panel: opened with a float,
 * closed with a counted snapshot. Every POS sale is stamped with the open
 * session so the Z report can reconcile the drawer against the orders.
 */
class TenantCashierSession extends Model
{
    use HasFactory;

    protected $table = 'tenant_cashier_sessions';

    protected $fillable = [
        'tenant_id', 'event_id', 'team_member_id',
        'opened_at', 'closed_at', 'opened_label', 'opening_float_cents',
        'closing_snapshot', 'opening_notes', 'closing_notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'closing_snapshot' => 'array',
        'opening_float_cents' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TenantTeamMember::class, 'team_member_id');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /** The tenant's currently-open shift, if any. */
    public static function currentFor(int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)
            ->whereNull('closed_at')
            ->latest('opened_at')
            ->first();
    }
}
