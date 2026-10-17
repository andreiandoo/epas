<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O cerere de booking trimisă de pe site-ul unei agenții de artiști (formularul de pe
 * pagina artistului sau /booking). Agenția o urmărește în panou: Cereri de booking.
 */
class TenantBookingRequest extends Model
{
    public const STATUSES = ['new' => 'Nouă', 'in_progress' => 'În discuție', 'won' => 'Confirmată', 'lost' => 'Refuzată'];
    public const EVENT_TYPES = ['concert' => 'Concert', 'festival' => 'Festival', 'private' => 'Eveniment privat', 'corporate' => 'Eveniment corporate', 'other' => 'Altceva'];

    protected $fillable = [
        'tenant_id', 'tenant_artist_id', 'artist_name', 'name', 'email', 'phone', 'company', 'event_type', 'event_date',
        'city', 'venue', 'capacity', 'budget', 'message', 'locale', 'status', 'notes', 'consent_ip',
    ];

    protected $casts = [
        'event_date' => 'date',
        'capacity' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(TenantArtist::class, 'tenant_artist_id');
    }
}
