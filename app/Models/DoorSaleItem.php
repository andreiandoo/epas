<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoorSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'door_sale_id', 'ticket_type_id', 'quantity', 'unit_price', 'total',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function doorSale(): BelongsTo { return $this->belongsTo(DoorSale::class); }
    public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }
}
