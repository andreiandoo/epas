<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log de audit pentru comenzi sterse din pagina leisure /organizator/leisure-orders.
 * Snapshot-ul complet al orderului + biletelor e persistat in `snapshot` JSONB
 * pentru reconstructie retrospectiva. Nota interna e obligatorie.
 */
class LeisureOrderDeletionLog extends Model
{
    protected $table = 'leisure_order_deletion_logs';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'event_id',
        'order_id',
        'order_number',
        'order_source',
        'order_status',
        'order_total',
        'order_currency',
        'order_paid_at',
        'order_created_at',
        'customer_name',
        'customer_email',
        'customer_phone',
        'payment_method',
        'cashier_session_id',
        'cashier_operator_name',
        'tickets_count',
        'snapshot',
        'note',
        'deleted_by_type',
        'deleted_by_id',
        'deleted_by_name',
        'deleted_by_email',
        'cashier_snapshot_regenerated',
        'deleted_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'order_paid_at' => 'datetime',
        'order_created_at' => 'datetime',
        'deleted_at' => 'datetime',
        'order_total' => 'decimal:2',
        'cashier_snapshot_regenerated' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
