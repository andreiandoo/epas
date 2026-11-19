<?php

namespace App\Models\Sanius;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Contractul de raspundere pentru sanii, semnat pe tableta sau pe hartie. */
class SaniusContract extends Model
{
    public const SIGNED = ['signed', 'signed_paper'];

    public const DOC_TYPES = ['ci', 'passport', 'other'];

    protected $table = 'sanius_contracts';

    protected $fillable = [
        'event_id', 'tab_id', 'status', 'station', 'signer_name', 'doc_type', 'doc_series', 'doc_number',
        'phone', 'address', 'title', 'body', 'template_version', 'body_hash', 'signature_path', 'pdf_path',
        'signed_at', 'signed_ip', 'signed_device', 'created_by_member_id', 'is_test',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'template_version' => 'integer',
        'is_test' => 'boolean',
    ];

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SaniusTab::class, 'tab_id');
    }

    public function isSigned(): bool
    {
        return in_array($this->status, self::SIGNED, true);
    }
}
