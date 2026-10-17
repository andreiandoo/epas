<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O campanie de newsletter a unui tenant: subiect, text, un buton opțional și limba
 * abonaților cărora li se trimite. Trimiterea: SendTenantNewsletterJob.
 */
class TenantNewsletter extends Model
{
    public const STATUSES = ['draft' => 'Ciornă', 'sending' => 'Se trimite', 'sent' => 'Trimis', 'failed' => 'Eșuat'];
    public const LOCALES = ['all' => 'Toți abonații', 'ro' => 'Doar abonații în română', 'en' => 'Doar abonații în engleză'];

    protected $fillable = [
        'tenant_id', 'subject', 'preheader', 'body', 'button_label', 'button_url', 'locale',
        'status', 'recipients_count', 'sent_count', 'failed_count', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Abonații cărora le-ar pleca acum campania. */
    public function audience()
    {
        return TenantNewsletterSubscriber::where('tenant_id', $this->tenant_id)->active()
            ->when($this->locale !== 'all', fn ($q) => $q->where('locale', $this->locale));
    }
}
