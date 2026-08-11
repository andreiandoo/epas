<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Invitatie catre cineva care inca n-are cont Tixello.
 * Se transforma in cerere de prietenie la inregistrare — nu in prietenie.
 */
class TixelloFriendInvite extends Model
{
    protected $table = 'tixello_friend_invites';

    protected $fillable = ['inviter_id', 'email', 'name', 'source', 'converted_at', 'converted_account_id'];

    protected $casts = ['converted_at' => 'datetime'];
}
