<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manage promo code templates for quick code creation
 */
class PromoCodeTemplateService
{
    public function __construct(
        protected PromoCodeService $promoCodeService
    ) {}

    /**
     * Create a template
     *
     * @param string $tenantId
     * @param array $data
     * @return array
     */
    public function create(string $tenantId, array $data): array
    {
        $templateId = (string) Str::uuid();

        DB::table('promo_code_templates')->insert([
            'id' => $templateId,
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => $data['value'],
            'applies_to' => $data['applies_to'] ?? 'cart',
            'min_purchase_amount' => $data['min_purchase_amount'] ?? null,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'min_tickets' => $data['min_tickets'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'validity_days' => $data['validity_days'] ?? 30,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->getById($templateId);
    }

    /**
     * Get template by ID
     *
     * @param string $templateId
     * @return array
     */
    public function getById(string $templateId): array
    {
        $template = DB::table('promo_code_templates')
            ->where('id', $templateId)
            ->first();

        if (!$template) {
            throw new \Exception('Template not found');
        }

        return $this->formatTemplate($template);
    }

    /**
     * List templates for a tenant
     *
     * @param string $tenantId
     * @param array $filters
     * @return array
     */
    public function list(string $tenantId, array $filters = []): array
    {
        $query = DB::table('promo_code_templates')
            ->where('tenant_id', $tenantId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        $templates = $query->orderBy('created_at', 'desc')->get();

        return array_map(fn($t) => $this->formatTemplate($t), $templates->toArray());
    }

    /**
     * Create promo code from template
     *
     * @param string $templateId
     * @param array $overrides
     * @return array
     */
    public function createFromTemplate(string $templateId, array $overrides = []): array
    {
        $template = $this->getById($templateId);

        $promoData = [
            'code' => $overrides['code'] ?? null, // Will auto-generate if not provided
            'name' => $overrides['name'] ?? $template['name'],
            'description' => $template['description'],
            'type' => $template['type'],
            'value' => $template['value'],
            'applies_to' => $template['applies_to'],
            'min_purchase_amount' => $template['min_purchase_amount'],
            'max_discount_amount' => $template['max_discount_amount'],
            'min_tickets' => $template['min_tickets'],
            'usage_limit' => $template['usage_limit'],
            'usage_limit_per_customer' => $template['usage_limit_per_customer'],
            'expires_at' => now()->addDays($template['validity_days']),
        ];

        // Apply any additional overrides
        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $promoData)) {
                $promoData[$key] = $value;
            }
        }

        return $this->promoCodeService->create($template['tenant_id'], $promoData);
    }

    /**
     * Format template for API responses
     *
     * @param object $template
     * @return array
     */
    protected function formatTemplate(object $template): array
    {
        return [
            'id' => $template->id,
            'tenant_id' => $template->tenant_id,
            'name' => $template->name,
            'description' => $template->description,
            'type' => $template->type,
            'value' => (float) $template->value,
            'applies_to' => $template->applies_to,
            'min_purchase_amount' => $template->min_purchase_amount ? (float) $template->min_purchase_amount : null,
            'max_discount_amount' => $template->max_discount_amount ? (float) $template->max_discount_amount : null,
            'min_tickets' => $template->min_tickets,
            'usage_limit' => $template->usage_limit,
            'usage_limit_per_customer' => $template->usage_limit_per_customer,
            'validity_days' => $template->validity_days,
            'is_active' => (bool) $template->is_active,
            'metadata' => $template->metadata ? json_decode($template->metadata, true) : null,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }
}
