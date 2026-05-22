<?php

namespace App\Services;

use App\Models\MarketplaceCity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Translates the JSON DSL stored on `marketplace_city_intents.filter_rule_json`
 * into Eloquent query conditions on the events table.
 *
 * Supported leaf rules:
 *   {"type":"in_city","param":"$city"}                — joins venue + marketplace_city on slug
 *   {"type":"event_attr","field":"is_indoor","value":true}
 *   {"type":"category_slug","value":"escape-rooms"}
 *   {"type":"cheapest_price_max","value":50}          — value in LEI (converted to cents)
 *   {"type":"cheapest_price_min","value":50}
 *   {"type":"cheapest_price_eq","value":0}            — typically used for free events
 *   {"type":"has_session_today"}
 *   {"type":"has_session_tomorrow"}
 *   {"type":"has_session_this_weekend"}
 *   {"type":"tag","value":"romantic"}                 — audience_tags JSON contains
 *   {"type":"age_includes","value":7}                 — min_age <= 7 AND (max_age IS NULL OR max_age >= 7)
 *
 * Combinators:
 *   {"all":[ ... ]}   — every sub-rule must match (AND)
 *   {"any":[ ... ]}   — at least one sub-rule must match (OR)
 *   {"not": ... }     — single sub-rule negated
 *
 * Bindings:
 *   String values starting with `$` are looked up in the $bindings array.
 *   {"param":"$city"} with $bindings=['city'=>'brasov'] resolves to "brasov".
 */
class IntentFilterResolver
{
    /**
     * @param  Builder  $query   Builder already scoped to a marketplace_client_id.
     * @param  array    $rule    The filter_rule_json contents.
     * @param  array    $bindings  Runtime values (city, marketplace_client_id, locale, ...).
     */
    public function apply(Builder $query, array $rule, array $bindings = []): Builder
    {
        return $this->applyNode($query, $rule, $bindings);
    }

    protected function applyNode(Builder $query, array $node, array $bindings): Builder
    {
        // Combinators short-circuit BEFORE leaf-type dispatch so a rule like
        // {"all":[...], "type":"x"} doesn't accidentally double-apply.
        if (isset($node['all']) && is_array($node['all'])) {
            return $query->where(function ($q) use ($node, $bindings) {
                foreach ($node['all'] as $sub) {
                    $this->applyNode($q, $sub, $bindings);
                }
            });
        }

        if (isset($node['any']) && is_array($node['any'])) {
            return $query->where(function ($q) use ($node, $bindings) {
                foreach ($node['any'] as $i => $sub) {
                    if ($i === 0) {
                        $q->where(function ($qq) use ($sub, $bindings) {
                            $this->applyNode($qq, $sub, $bindings);
                        });
                    } else {
                        $q->orWhere(function ($qq) use ($sub, $bindings) {
                            $this->applyNode($qq, $sub, $bindings);
                        });
                    }
                }
            });
        }

        if (isset($node['not']) && is_array($node['not'])) {
            return $query->whereNot(function ($q) use ($node, $bindings) {
                $this->applyNode($q, $node['not'], $bindings);
            });
        }

        // Leaf rule
        return $this->applyLeaf($query, $node, $bindings);
    }

    protected function applyLeaf(Builder $query, array $rule, array $bindings): Builder
    {
        $type = $rule['type'] ?? null;
        if (!$type) {
            return $query;
        }

        $resolve = fn ($v) => is_string($v) && str_starts_with($v, '$')
            ? ($bindings[ltrim($v, '$')] ?? null)
            : $v;

        switch ($type) {
            case 'in_city':
                $citySlug = $resolve($rule['param'] ?? null);
                if (!$citySlug) {
                    return $query;
                }
                $marketplaceClientId = $bindings['marketplace_client_id'] ?? null;
                if (!$marketplaceClientId) {
                    return $query;
                }
                $city = MarketplaceCity::where('marketplace_client_id', $marketplaceClientId)
                    ->where('slug', $citySlug)
                    ->first();
                if (!$city) {
                    // City slug doesn't exist → return empty result set rather than no filter.
                    return $query->whereRaw('1 = 0');
                }
                return $query->where('marketplace_city_id', $city->id);

            case 'event_attr':
                $field = $rule['field'] ?? null;
                $value = $rule['value'] ?? true;
                if (!$field || !$this->isWhitelistedAttribute($field)) {
                    return $query;
                }
                return $query->where($field, $value);

            case 'category_slug':
                $slug = $resolve($rule['value'] ?? null);
                if (!$slug) {
                    return $query;
                }
                return $query->whereHas('marketplaceEventCategory', function ($q) use ($slug) {
                    $q->where('slug', $slug);
                });

            case 'cheapest_price_max':
                $lei = (int) $resolve($rule['value'] ?? null);
                return $query->whereNotNull('cheapest_price_cents')
                    ->where('cheapest_price_cents', '<=', $lei * 100);

            case 'cheapest_price_min':
                $lei = (int) $resolve($rule['value'] ?? null);
                return $query->whereNotNull('cheapest_price_cents')
                    ->where('cheapest_price_cents', '>=', $lei * 100);

            case 'cheapest_price_eq':
                $lei = (int) $resolve($rule['value'] ?? 0);
                return $query->whereNotNull('cheapest_price_cents')
                    ->where('cheapest_price_cents', '=', $lei * 100);

            case 'has_session_today':
                return $query->where('has_session_today', true);

            case 'has_session_tomorrow':
                return $query->where('has_session_tomorrow', true);

            case 'has_session_this_weekend':
                return $query->where('has_session_this_weekend', true);

            case 'tag':
                $tag = $resolve($rule['value'] ?? null);
                if (!$tag) {
                    return $query;
                }
                // Postgres JSON contains operator. audience_tags stores a flat array of strings.
                return $query->whereJsonContains('audience_tags', $tag);

            case 'age_includes':
                $age = (int) $resolve($rule['value'] ?? null);
                return $query
                    ->where(function ($q) use ($age) {
                        $q->whereNull('min_age')->orWhere('min_age', '<=', $age);
                    })
                    ->where(function ($q) use ($age) {
                        $q->whereNull('max_age')->orWhere('max_age', '>=', $age);
                    });

            default:
                // Unknown rule type — silently ignore so a typo in admin doesn't
                // crash the page. The aggregate count will simply not reflect it.
                return $query;
        }
    }

    /**
     * Guardrail: event_attr rules can only target a fixed set of boolean columns,
     * preventing admins from accidentally filtering on `is_cancelled` or worse.
     */
    protected function isWhitelistedAttribute(string $field): bool
    {
        return in_array($field, [
            'is_indoor',
            'is_outdoor',
            'is_kid_friendly',
            'is_accessible',
            'is_weather_sensitive',
        ], true);
    }
}
