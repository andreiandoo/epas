<?php

namespace App\Models;

use App\Models\MarketplaceClient;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InviteBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inv_batches';

    protected $fillable = [
        'marketplace_client_id',
        'marketplace_organizer_id',
        'marketplace_event_id',
        'tenant_id',
        'event_ref',
        'name',
        'qty_planned',
        'template_id',
        'options',
        'status',
        'qty_generated',
        'qty_rendered',
        'qty_emailed',
        'qty_downloaded',
        'qty_opened',
        'qty_checked_in',
        'qty_voided',
        'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'qty_planned' => 'integer',
        'qty_generated' => 'integer',
        'qty_rendered' => 'integer',
        'qty_emailed' => 'integer',
        'qty_downloaded' => 'integer',
        'qty_opened' => 'integer',
        'qty_checked_in' => 'integer',
        'qty_voided' => 'integer',
    ];

    /**
     * Relationships
     */

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrganizer::class, 'marketplace_organizer_id');
    }

    public function marketplaceEvent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class, 'marketplace_event_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TicketTemplate::class, 'template_id');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes
     */

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Helper methods
     */

    public function getOption(string $key, $default = null)
    {
        return data_get($this->options, $key, $default);
    }

    public function getWatermark(): string
    {
        return $this->getOption('watermark', 'INVITATION');
    }

    public function getSeatMode(): string
    {
        return $this->getOption('seat_mode', 'none');
    }

    public function getCompletionPercentage(): float
    {
        if ($this->qty_planned === 0) {
            return 0;
        }

        return round(($this->qty_checked_in / $this->qty_planned) * 100, 2);
    }

    public function getEmailedPercentage(): float
    {
        if ($this->qty_planned === 0) {
            return 0;
        }

        return round(($this->qty_emailed / $this->qty_planned) * 100, 2);
    }

    public function getDownloadedPercentage(): float
    {
        if ($this->qty_planned === 0) {
            return 0;
        }

        return round(($this->qty_downloaded / $this->qty_planned) * 100, 2);
    }

    public function incrementGenerated(): void
    {
        $this->increment('qty_generated');
    }

    public function incrementRendered(): void
    {
        $this->increment('qty_rendered');
    }

    public function incrementEmailed(): void
    {
        $this->increment('qty_emailed');
    }

    public function incrementDownloaded(): void
    {
        $this->increment('qty_downloaded');
    }

    public function incrementOpened(): void
    {
        $this->increment('qty_opened');
    }

    public function incrementCheckedIn(): void
    {
        $this->increment('qty_checked_in');
    }

    public function incrementVoided(): void
    {
        $this->increment('qty_voided');
    }

    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['ready', 'sending', 'completed']);
    }

    public function canSendEmails(): bool
    {
        return $this->status === 'ready' && $this->qty_rendered > 0;
    }
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
