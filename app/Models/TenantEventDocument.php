<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Un document fiscal generat pentru un eveniment de tenant (PDF pe discul public). */
class TenantEventDocument extends Model
{
    protected $fillable = [
        'tenant_id', 'event_id', 'tenant_tax_template_id', 'type', 'filename', 'file_path',
        'file_size', 'generated_by_id', 'generated_by_name', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TenantTaxTemplate::class, 'tenant_tax_template_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function typeLabel(): string
    {
        return TenantTaxTemplate::TYPES[$this->type] ?? $this->type;
    }
}
