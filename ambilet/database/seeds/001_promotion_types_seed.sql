-- Seed Data: Promotion Types and Options
-- Description: Initial promotion types, options, and pricing configuration
-- Created: 2026-01-25

-- ============================================
-- PROMOTION TYPES
-- ============================================

INSERT INTO promotion_types (name, slug, category, description, icon, base_cost, cost_model, sort_order) VALUES
-- Event Featuring
(
    'Event Featuring',
    'event-featuring',
    'featuring',
    'Feature your event on high-visibility pages across the Ambilet marketplace. Get more exposure and drive ticket sales.',
    'star',
    50.00,
    'fixed',
    1
),
-- Email Marketing
(
    'Targeted Email Marketing',
    'email-marketing',
    'email_marketing',
    'Reach potential attendees directly in their inbox with targeted email campaigns.',
    'mail',
    0.00,
    'per_unit',
    2
),
-- Ad Tracking
(
    'Track Ad Campaigns',
    'ad-tracking',
    'ad_tracking',
    'Connect and track your Facebook, Google, and TikTok advertising campaigns with detailed analytics.',
    'chart-line',
    29.00,
    'subscription',
    3
),
-- Ad Creation
(
    'Create Ad Campaigns',
    'ad-creation',
    'ad_creation',
    'Let our experts create and manage high-performing ad campaigns for your event on major platforms.',
    'megaphone',
    200.00,
    'fixed',
    4
);

-- ============================================
-- PROMOTION OPTIONS - Event Featuring
-- ============================================

INSERT INTO promotion_options (promotion_type_id, name, code, description, cost_modifier, min_duration_days, max_duration_days, sort_order) VALUES
-- Event Featuring Options (promotion_type_id = 1)
(1, 'Home Page', 'home_page', 'Premium placement on the main landing page carousel. Maximum visibility.', 2.00, 1, 30, 1),
(1, 'Category Page', 'category_page', 'Featured in your event''s category page (Concerts, Sports, Theater, etc.)', 1.50, 1, 30, 2),
(1, 'Genre Page', 'genre_page', 'Featured in specific genre pages (Rock, Jazz, Comedy, Drama, etc.)', 1.25, 1, 30, 3),
(1, 'City Page', 'city_page', 'Featured on city/location specific pages', 1.00, 1, 30, 4),
(1, 'General Rotation', 'general', 'Rotational featuring across multiple pages', 0.75, 1, 30, 5);

-- ============================================
-- PROMOTION OPTIONS - Email Marketing
-- ============================================

INSERT INTO promotion_options (promotion_type_id, name, code, description, unit_cost, min_quantity, max_quantity, sort_order, metadata) VALUES
-- Email Marketing Options (promotion_type_id = 2)
(2, 'Whole Database', 'whole_database', 'Send to all subscribed users on the platform. Maximum reach.', 0.08, 1000, NULL, 1, '{"audience_type": "whole_database"}'),
(2, 'Filtered Database', 'filtered_database', 'Send to users matching specific criteria (location, interests, demographics).', 0.10, 100, NULL, 2, '{"audience_type": "filtered_database", "supports_filters": true}'),
(2, 'Past Event Clients', 'past_clients', 'Send to attendees from your previous events. Higher engagement rates.', 0.05, 1, NULL, 3, '{"audience_type": "past_clients"}');

-- ============================================
-- PROMOTION OPTIONS - Ad Tracking
-- ============================================

INSERT INTO promotion_options (promotion_type_id, name, code, description, unit_cost, sort_order, metadata) VALUES
-- Ad Tracking Options (promotion_type_id = 3)
(3, 'Facebook Ads Tracking', 'facebook_tracking', 'Connect your Facebook Ads account and track campaign performance.', 29.00, 1, '{"platform": "facebook", "requires_oauth": true}'),
(3, 'Google Ads Tracking', 'google_tracking', 'Connect your Google Ads account and track campaign performance.', 29.00, 2, '{"platform": "google", "requires_oauth": true}'),
(3, 'TikTok Ads Tracking', 'tiktok_tracking', 'Connect your TikTok Ads account and track campaign performance.', 29.00, 3, '{"platform": "tiktok", "requires_oauth": true}'),
(3, 'All Platforms Bundle', 'all_platforms_tracking', 'Track campaigns across all three platforms at a discounted rate.', 69.00, 4, '{"platforms": ["facebook", "google", "tiktok"], "requires_oauth": true, "is_bundle": true}');

-- ============================================
-- PROMOTION OPTIONS - Ad Creation
-- ============================================

INSERT INTO promotion_options (promotion_type_id, name, code, description, unit_cost, cost_modifier, sort_order, metadata) VALUES
-- Ad Creation Options (promotion_type_id = 4)
(4, 'Facebook Campaign', 'facebook_campaign', 'We create and manage a Facebook advertising campaign for your event.', 200.00, 1.00, 1, '{"platform": "facebook", "includes_setup": true, "management_fee_percent": 15}'),
(4, 'Google Campaign', 'google_campaign', 'We create and manage a Google Ads campaign for your event.', 250.00, 1.25, 2, '{"platform": "google", "includes_setup": true, "management_fee_percent": 15}'),
(4, 'TikTok Campaign', 'tiktok_campaign', 'We create and manage a TikTok advertising campaign for your event.', 200.00, 1.00, 3, '{"platform": "tiktok", "includes_setup": true, "management_fee_percent": 15}'),
(4, 'Multi-Platform Campaign', 'multi_platform_campaign', 'Coordinated campaigns across Facebook, Google, and TikTok.', 500.00, 2.50, 4, '{"platforms": ["facebook", "google", "tiktok"], "includes_setup": true, "management_fee_percent": 12, "is_bundle": true}');

-- ============================================
-- PRICING TIERS - Event Featuring
-- ============================================

-- Home Page pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(1, 'daily', 1, 7, 100.00, '2026-01-01'),
(1, 'weekly', 7, 14, 85.00, '2026-01-01'),
(1, 'biweekly', 14, 30, 75.00, '2026-01-01');

-- Category Page pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(2, 'daily', 1, 7, 75.00, '2026-01-01'),
(2, 'weekly', 7, 14, 65.00, '2026-01-01'),
(2, 'biweekly', 14, 30, 55.00, '2026-01-01');

-- Genre Page pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(3, 'daily', 1, 7, 60.00, '2026-01-01'),
(3, 'weekly', 7, 14, 50.00, '2026-01-01'),
(3, 'biweekly', 14, 30, 45.00, '2026-01-01');

-- City Page pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(4, 'daily', 1, 7, 50.00, '2026-01-01'),
(4, 'weekly', 7, 14, 40.00, '2026-01-01'),
(4, 'biweekly', 14, 30, 35.00, '2026-01-01');

-- General Rotation pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(5, 'daily', 1, 7, 35.00, '2026-01-01'),
(5, 'weekly', 7, 14, 30.00, '2026-01-01'),
(5, 'biweekly', 14, 30, 25.00, '2026-01-01');

-- ============================================
-- PRICING TIERS - Email Marketing (Volume Discounts)
-- ============================================

-- Whole Database volume pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(6, 'small', 1000, 5000, 0.08, '2026-01-01'),
(6, 'medium', 5001, 25000, 0.06, '2026-01-01'),
(6, 'large', 25001, 100000, 0.04, '2026-01-01'),
(6, 'enterprise', 100001, NULL, 0.03, '2026-01-01');

-- Filtered Database volume pricing
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(7, 'small', 100, 1000, 0.10, '2026-01-01'),
(7, 'medium', 1001, 10000, 0.08, '2026-01-01'),
(7, 'large', 10001, 50000, 0.06, '2026-01-01'),
(7, 'enterprise', 50001, NULL, 0.05, '2026-01-01');

-- Past Clients volume pricing (discounted)
INSERT INTO promotion_pricing (promotion_option_id, tier_name, min_quantity, max_quantity, unit_price, effective_from) VALUES
(8, 'any', 1, 500, 0.05, '2026-01-01'),
(8, 'medium', 501, 2000, 0.04, '2026-01-01'),
(8, 'large', 2001, 10000, 0.03, '2026-01-01'),
(8, 'enterprise', 10001, NULL, 0.02, '2026-01-01');

-- ============================================
-- PRICING - Ad Tracking (Monthly Subscription)
-- ============================================

INSERT INTO promotion_pricing (promotion_option_id, tier_name, unit_price, effective_from) VALUES
(9, 'monthly', 29.00, '2026-01-01'),
(10, 'monthly', 29.00, '2026-01-01'),
(11, 'monthly', 29.00, '2026-01-01'),
(12, 'monthly', 69.00, '2026-01-01');

-- ============================================
-- PRICING - Ad Creation (Setup + Management)
-- ============================================

INSERT INTO promotion_pricing (promotion_option_id, tier_name, unit_price, effective_from) VALUES
(13, 'setup', 200.00, '2026-01-01'),
(14, 'setup', 250.00, '2026-01-01'),
(15, 'setup', 200.00, '2026-01-01'),
(16, 'setup', 500.00, '2026-01-01');
