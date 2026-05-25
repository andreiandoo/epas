<?php

namespace App\Models;

/**
 * Compat alias for `MarketplaceEventCategory`.
 *
 * Semantically the same table — but the original name no longer reflects
 * how the data is used. With the Activities module live (microservice slug
 * `activities-module`), the same category tree is shared between Events
 * and Activities (Escape Rooms, Museums, Adventure Parks, …).
 *
 * This shim allows new code (activity resources, activity controllers,
 * activity SEO pages) to refer to `MarketplaceCategory` while existing
 * code continues to use `MarketplaceEventCategory` without any change.
 *
 *   - Same DB table: `marketplace_event_categories` (inherited via
 *     `$table` on parent).
 *   - Same relationships, scopes, casts, translatable fields, boot logic.
 *   - Both classes resolve to identical rows — `MarketplaceCategory::find(1)`
 *     and `MarketplaceEventCategory::find(1)` return models of different
 *     PHP classes but pointing at the same record.
 *
 * Migration path:
 *   - Phase A0 (this file): introduce alias, no behavior change.
 *   - Later phase: rename DB table, flip the inheritance (parent becomes
 *     `MarketplaceCategory`, `MarketplaceEventCategory` becomes the
 *     deprecated alias). All call sites can stay on whichever name they
 *     used originally.
 *
 * Do not add any logic here. If a method needs to exist, add it on the
 * parent so both class names see the same behavior. This file should
 * remain an intentional one-line subclass.
 */
class MarketplaceCategory extends MarketplaceEventCategory
{
}
