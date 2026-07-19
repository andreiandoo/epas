<?php

namespace App\Services\Installments;

use App\Models\EventFlexiblePaymentConfig;
use App\Models\InstallmentPlan;
use App\Models\MarketplaceClient;
use Illuminate\Support\Collection;

/**
 * Decides which flexible-payment methods (installments / BNPL / delegated pay)
 * are available for a given checkout, and returns priced plan previews.
 *
 * Firm rules (per stakeholder):
 *   1. Microservice + sub-module active on the marketplace.
 *   2. Provider must be tokenization-capable (installments/BNPL only).
 *   3. Method enabled on the event (event_flexible_payment_configs).
 *   4. SINGLE-EVENT CART: if the cart spans multiple events, NONE of the new
 *      methods are offered (schedules/values differ per event) — show a message.
 *   5. Plan eligibility (min/max, fits before event − 1 day, ≤3 months).
 *
 * Decoupled from the cart internals: the checkout passes explicit inputs
 * (event ids, base total, provider, marketplace client).
 */
class FlexiblePaymentEligibilityService
{
    public function __construct(
        protected InstallmentPlanCalculator $calculator,
    ) {}

    /**
     * Top-level availability for a cart.
     *
     * @param int[] $eventIds distinct event ids represented in the cart
     * @return array{
     *   available: bool,
     *   single_event: bool,
     *   methods: array<string,bool>,   // installments|bnpl|delegated_pay => enabled
     *   message: string|null,
     *   event_id: int|null
     * }
     */
    public function availability(?MarketplaceClient $client, string $provider, array $eventIds): array
    {
        $out = [
            'available' => false,
            'single_event' => false,
            'methods' => ['installments' => false, 'bnpl' => false, 'delegated_pay' => false],
            'message' => null,
            'event_id' => null,
        ];

        $eventIds = array_values(array_unique(array_filter($eventIds)));

        // Rule 4 — multiple events → block all new methods.
        if (count($eventIds) > 1) {
            $out['message'] = __('Pentru plata în rate și BNPL este necesar să plasezi comenzi separate, câte una per eveniment.');
            return $out;
        }
        if (count($eventIds) === 0) {
            return $out;
        }

        $eventId = $eventIds[0];
        $out['single_event'] = true;
        $out['event_id'] = $eventId;

        // Rule 1 — microservice sub-modules (marketplace settings).
        $subModules = $this->enabledSubModules($client);

        // Rule 2 — provider tokenization (installments/BNPL need it).
        $tokenizable = in_array($provider, config('installments.tokenizable_providers', ['stripe', 'netopia']), true);

        // Rule 3 — event toggles.
        $config = $this->eventConfig($eventId);
        if (! $config) {
            return $out;
        }

        $out['methods']['installments'] = $subModules['installments'] && $tokenizable && $config->enable_installments;
        $out['methods']['bnpl'] = $subModules['bnpl'] && $tokenizable && $config->enable_bnpl;
        // Delegated pay is not credit → no tokenization requirement.
        $out['methods']['delegated_pay'] = $subModules['delegated_pay'] && $config->enable_delegated_pay;

        $out['available'] = in_array(true, $out['methods'], true);
        return $out;
    }

    /**
     * Priced, eligible plan previews for a single event + base total.
     *
     * @return array<int,array> list of quotes (only eligible plans), each merged
     *                          with plan id/name/type.
     */
    public function plansForEvent(int $eventId, int $baseTotalCents, array $opts = []): array
    {
        $config = $this->eventConfig($eventId);
        if (! $config) {
            return [];
        }

        $platformFeePercent = (float) config('installments.platform_fee_percent_installments', 2.0);
        $eventStart = $opts['event_start_date'] ?? null;
        $startDate = $opts['start_date'] ?? null;

        return $config->plans()
            ->wherePivot('is_active', true)
            ->where('installment_plans.is_active', true)
            ->orderBy('event_installment_plan.sort_order')
            ->get()
            ->filter(function (InstallmentPlan $plan) use ($config) {
                return $plan->isBnpl() ? $config->enable_bnpl : $config->enable_installments;
            })
            ->map(function (InstallmentPlan $plan) use ($baseTotalCents, $config, $platformFeePercent, $eventStart, $startDate) {
                $quote = $this->calculator->quote($plan, $baseTotalCents, [
                    'down_payment_type' => $config->down_payment_type,
                    'down_payment_value' => $config->down_payment_value,
                    'event_start_date' => $eventStart,
                    'start_date' => $startDate,
                    'platform_fee_percent' => $platformFeePercent,
                    'bnpl_max_horizon_days' => $config->bnpl_max_horizon_days,
                ]);

                return array_merge($quote, [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->getTranslation('name'),
                    'plan_type' => $plan->plan_type,
                    'terms_url' => $plan->terms_url,
                ]);
            })
            ->filter(fn ($q) => $q['eligible'])
            ->values()
            ->all();
    }

    /**
     * Marketplace-level sub-module toggles (from the microservice pivot
     * settings). Defaults to all-on when the microservice is active but the
     * settings key is absent.
     */
    protected function enabledSubModules(?MarketplaceClient $client): array
    {
        $default = ['installments' => true, 'bnpl' => true, 'delegated_pay' => true];
        if (! $client) {
            return ['installments' => false, 'bnpl' => false, 'delegated_pay' => false];
        }

        $pivot = $client->microservices()
            ->where('slug', 'flexible-payments')
            ->first()?->pivot;

        // Treat the microservice as active on either signal (the panel uses
        // pivot.status='active' for nav; some flows use is_active) so navigation
        // and actual availability never diverge.
        $active = $pivot && (($pivot->status ?? null) === 'active' || ($pivot->is_active ?? false));
        if (! $active) {
            return ['installments' => false, 'bnpl' => false, 'delegated_pay' => false];
        }

        $settings = is_array($pivot->settings ?? null)
            ? $pivot->settings
            : (json_decode($pivot->settings ?? '[]', true) ?: []);

        return [
            'installments' => (bool) ($settings['enable_installments'] ?? $default['installments']),
            'bnpl' => (bool) ($settings['enable_bnpl'] ?? $default['bnpl']),
            'delegated_pay' => (bool) ($settings['enable_delegated_pay'] ?? $default['delegated_pay']),
        ];
    }

    protected function eventConfig(int $eventId): ?EventFlexiblePaymentConfig
    {
        return EventFlexiblePaymentConfig::where('event_id', $eventId)
            ->orWhere('marketplace_event_id', $eventId)
            ->first();
    }
}
