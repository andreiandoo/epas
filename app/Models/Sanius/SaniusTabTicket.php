<?php

namespace App\Models\Sanius;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaniusTabTicket extends Model
{
    protected $table = 'sanius_tab_tickets';

    protected $fillable = ['tab_id', 'ticket_id', 'order_id', 'linked_by_member_id', 'linked_at_point'];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SaniusTab::class, 'tab_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
