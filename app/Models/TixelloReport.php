<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un raport de abuz. Vezi migrarea pentru de ce subiectul e polimorf si de ce
 * exista o stare.
 */
class TixelloReport extends Model
{
    protected $table = 'tixello_reports';

    /** Motivele acceptate. Lista e scurta si inchisa: un camp liber ar fi produs
     *  „nu-mi place" si n-ar fi putut fi triat. `other` are nota obligatorie. */
    public const REASONS = ['spam', 'harassment', 'fake_profile', 'inappropriate', 'other'];

    protected $fillable = [
        'reporter_id', 'subject_type', 'subject_id',
        'reason', 'note', 'status', 'resolution', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];
}
