<?php

namespace App\Services\Leisure;

/**
 * Aruncat de SlotBookingService::reserve() când slot-ul nu mai are stoc.
 * Controllerul prinde excepția și răspunde cu 422 + mesaj user-facing.
 */
class SlotSoldOutException extends \RuntimeException
{
}
