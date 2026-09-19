<?php

namespace App\Services\Activities;

/**
 * A cart line can't be bought as it is. The message is shown to the customer
 * (Romanian), so it must never carry internal details.
 */
class ActivityCartException extends \RuntimeException
{
}
