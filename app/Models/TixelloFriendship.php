<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * O pereche de prietenie. Vezi migrarea pentru de ce perechea e canonica
 * (account_a_id < account_b_id) si de ce nimic nu devine prietenie automat.
 */
class TixelloFriendship extends Model
{
    protected $table = 'tixello_friendships';

    protected $fillable = [
        'account_a_id', 'account_b_id', 'requested_by',
        'status', 'source', 'blocked_by', 'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    /** Celalalt cont din pereche, fata de cel dat. */
    public function otherId(int $accountId): int
    {
        return $this->account_a_id === $accountId ? $this->account_b_id : $this->account_a_id;
    }
}
