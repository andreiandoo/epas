<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'wa_templates';

    protected $fillable = [
        'tenant_id',
        'name',
        'language',
        'category',
        'body',
        'variables',
        'status',
        'provider_meta',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'provider_meta' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DISABLED = 'disabled';

    /**
     * Category constants
     */
    const CATEGORY_ORDER_CONFIRM = 'order_confirm';
    const CATEGORY_REMINDER = 'reminder';
    const CATEGORY_PROMO = 'promo';
    const CATEGORY_OTP = 'otp';
    const CATEGORY_OTHER = 'other';

    /**
     * Check if template is approved and ready to use
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if template can be edited
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    /**
     * Mark as submitted for approval
     */
    public function markAsSubmitted(array $providerMeta = []): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'provider_meta' => array_merge($this->provider_meta ?? [], $providerMeta),
        ]);
    }

    /**
     * Mark as approved
     */
    public function markAsApproved(array $providerMeta = []): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'provider_meta' => array_merge($this->provider_meta ?? [], $providerMeta),
        ]);
    }

    /**
     * Mark as rejected
     */
    public function markAsRejected(string $reason, array $providerMeta = []): void
    {
        $meta = array_merge($this->provider_meta ?? [], $providerMeta, [
            'rejection_reason' => $reason,
        ]);

        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_at' => now(),
            'provider_meta' => $meta,
        ]);
    }

    /**
     * Extract variable names from body
     */
    public function extractVariables(): array
    {
        // Match {{1}}, {{2}} or {variable_name} patterns
        preg_match_all('/\{\{(\d+)\}\}|\{([a-z_]+)\}/', $this->body, $matches);

        $variables = [];

        // Numeric placeholders like {{1}}, {{2}}
        foreach ($matches[1] as $match) {
            if (!empty($match)) {
                $variables[] = "var_{$match}";
            }
        }

        // Named placeholders like {first_name}
        foreach ($matches[2] as $match) {
            if (!empty($match)) {
                $variables[] = $match;
            }
        }

        return array_unique($variables);
    }

    /**
     * Render template with variables
     */
    public function render(array $variables): string
    {
        $body = $this->body;

        // Replace {variable_name} placeholders
        foreach ($variables as $key => $value) {
            $body = str_replace("{{$key}}", $value, $body);
        }

        // Replace {{1}}, {{2}} numeric placeholders
        foreach ($variables as $index => $value) {
            $position = $index + 1;
            $body = str_replace("{{{$position}}}", $value, $body);
        }

        return $body;
    }

    /**
     * Scope: Approved templates for tenant
     */
    public function scopeApproved($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId)
            ->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope: By category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
