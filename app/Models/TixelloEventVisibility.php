<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Exceptiile de la regula generala de vizibilitate, per eveniment.
 * Regula generala sta pe cont (`friends_visibility`) si e „nimeni" implicit.
 */
class TixelloEventVisibility extends Model
{
    protected $table = 'tixello_event_visibility';

    protected $fillable = ['tixello_account_id', 'event_id', 'visible'];

    protected $casts = ['visible' => 'boolean'];
}
