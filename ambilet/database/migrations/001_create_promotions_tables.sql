-- Migration: Create Promotions Tables
-- Description: Database schema for Organizer Promotions Feature
-- Created: 2026-01-25

-- ============================================
-- ENUM TYPES
-- ============================================

CREATE TYPE promotion_category AS ENUM (
    'featuring',
    'email_marketing',
    'ad_tracking',
    'ad_creation'
);

CREATE TYPE cost_model AS ENUM (
    'fixed',
    'per_unit',
    'percentage',
    'subscription'
);

CREATE TYPE order_status AS ENUM (
    'draft',
    'pending_payment',
    'paid',
    'processing',
    'active',
    'completed',
    'cancelled',
    'refunded'
);

CREATE TYPE order_item_status AS ENUM (
    'pending',
    'active',
    'completed',
    'cancelled'
);

CREATE TYPE email_recipient_status AS ENUM (
    'pending',
    'sent',
    'delivered',
    'opened',
    'clicked',
    'bounced',
    'unsubscribed'
);

CREATE TYPE ad_platform AS ENUM (
    'facebook',
    'google',
    'tiktok'
);

CREATE TYPE audience_type AS ENUM (
    'whole_database',
    'filtered_database',
    'past_clients'
);

CREATE TYPE featuring_placement AS ENUM (
    'home_page',
    'category_page',
    'genre_page',
    'city_page',
    'general'
);

-- ============================================
-- CORE TABLES
-- ============================================

-- Promotion types catalog
CREATE TABLE promotion_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    category promotion_category NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    base_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    cost_model cost_model NOT NULL,
    is_active BOOLEAN DEFAULT true,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Promotion sub-options (placements, platforms, audience types)
CREATE TABLE promotion_options (
    id SERIAL PRIMARY KEY,
    promotion_type_id INTEGER NOT NULL REFERENCES promotion_types(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT,
    cost_modifier DECIMAL(5,2) DEFAULT 1.00,
    unit_cost DECIMAL(10,4),
    min_quantity INTEGER DEFAULT 1,
    max_quantity INTEGER,
    min_duration_days INTEGER,
    max_duration_days INTEGER,
    is_active BOOLEAN DEFAULT true,
    sort_order INTEGER DEFAULT 0,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(promotion_type_id, code)
);

-- Dynamic pricing configuration (tier-based pricing)
CREATE TABLE promotion_pricing (
    id SERIAL PRIMARY KEY,
    promotion_option_id INTEGER NOT NULL REFERENCES promotion_options(id) ON DELETE CASCADE,
    tier_name VARCHAR(50),
    min_quantity INTEGER DEFAULT 1,
    max_quantity INTEGER,
    unit_price DECIMAL(10,4) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RON',
    effective_from DATE NOT NULL,
    effective_until DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Organizer promotion orders
CREATE TABLE promotion_orders (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    organizer_id INTEGER NOT NULL,
    event_id INTEGER,
    status order_status DEFAULT 'draft',
    currency VARCHAR(3) DEFAULT 'RON',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    discount_code VARCHAR(50),
    tax_rate DECIMAL(5,2) DEFAULT 19.00,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(50),
    payment_id VARCHAR(100),
    payment_provider VARCHAR(50),
    paid_at TIMESTAMP WITH TIME ZONE,
    expires_at TIMESTAMP WITH TIME ZONE,
    notes TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Individual promotion items in an order
CREATE TABLE promotion_order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES promotion_orders(id) ON DELETE CASCADE,
    promotion_type_id INTEGER NOT NULL REFERENCES promotion_types(id),
    promotion_option_id INTEGER NOT NULL REFERENCES promotion_options(id),
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(10,4) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    start_date DATE,
    end_date DATE,
    duration_days INTEGER,
    status order_item_status DEFAULT 'pending',
    configuration JSONB DEFAULT '{}',
    metadata JSONB DEFAULT '{}',
    activated_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- EMAIL MARKETING TABLES
-- ============================================

-- Email campaigns
CREATE TABLE email_campaigns (
    id SERIAL PRIMARY KEY,
    order_item_id INTEGER NOT NULL REFERENCES promotion_order_items(id) ON DELETE CASCADE,
    organizer_id INTEGER NOT NULL,
    event_id INTEGER,
    audience_type audience_type NOT NULL,
    audience_filters JSONB DEFAULT '{}',
    subject VARCHAR(255) NOT NULL,
    preview_text VARCHAR(255),
    template_id INTEGER,
    html_content TEXT,
    plain_text_content TEXT,
    total_recipients INTEGER DEFAULT 0,
    sent_count INTEGER DEFAULT 0,
    delivered_count INTEGER DEFAULT 0,
    opened_count INTEGER DEFAULT 0,
    clicked_count INTEGER DEFAULT 0,
    bounced_count INTEGER DEFAULT 0,
    unsubscribed_count INTEGER DEFAULT 0,
    scheduled_at TIMESTAMP WITH TIME ZONE,
    started_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    status VARCHAR(20) DEFAULT 'draft',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Email campaign recipients
CREATE TABLE email_campaign_recipients (
    id SERIAL PRIMARY KEY,
    campaign_id INTEGER NOT NULL REFERENCES email_campaigns(id) ON DELETE CASCADE,
    user_id INTEGER,
    email VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    status email_recipient_status DEFAULT 'pending',
    sent_at TIMESTAMP WITH TIME ZONE,
    delivered_at TIMESTAMP WITH TIME ZONE,
    opened_at TIMESTAMP WITH TIME ZONE,
    clicked_at TIMESTAMP WITH TIME ZONE,
    bounced_at TIMESTAMP WITH TIME ZONE,
    bounce_reason TEXT,
    unsubscribed_at TIMESTAMP WITH TIME ZONE,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_email_recipients_campaign ON email_campaign_recipients(campaign_id);
CREATE INDEX idx_email_recipients_status ON email_campaign_recipients(status);
CREATE INDEX idx_email_recipients_email ON email_campaign_recipients(email);

-- ============================================
-- AD CAMPAIGN TABLES
-- ============================================

-- Ad campaign tracking connections
CREATE TABLE ad_tracking_connections (
    id SERIAL PRIMARY KEY,
    organizer_id INTEGER NOT NULL,
    platform ad_platform NOT NULL,
    account_id VARCHAR(100),
    account_name VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP WITH TIME ZONE,
    is_active BOOLEAN DEFAULT true,
    connected_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    last_synced_at TIMESTAMP WITH TIME ZONE,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(organizer_id, platform)
);

-- Ad campaign tracking data
CREATE TABLE ad_campaign_tracking (
    id SERIAL PRIMARY KEY,
    order_item_id INTEGER REFERENCES promotion_order_items(id) ON DELETE SET NULL,
    connection_id INTEGER REFERENCES ad_tracking_connections(id) ON DELETE CASCADE,
    organizer_id INTEGER NOT NULL,
    platform ad_platform NOT NULL,
    external_campaign_id VARCHAR(100) NOT NULL,
    campaign_name VARCHAR(255),
    campaign_status VARCHAR(50),
    objective VARCHAR(100),
    budget DECIMAL(10,2),
    budget_type VARCHAR(20),
    start_date DATE,
    end_date DATE,
    impressions BIGINT DEFAULT 0,
    reach BIGINT DEFAULT 0,
    clicks BIGINT DEFAULT 0,
    conversions INTEGER DEFAULT 0,
    spend DECIMAL(10,2) DEFAULT 0,
    cpc DECIMAL(10,4),
    cpm DECIMAL(10,4),
    ctr DECIMAL(5,4),
    conversion_rate DECIMAL(5,4),
    roas DECIMAL(10,4),
    last_synced_at TIMESTAMP WITH TIME ZONE,
    tracking_data JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(platform, external_campaign_id)
);

CREATE INDEX idx_ad_tracking_organizer ON ad_campaign_tracking(organizer_id);
CREATE INDEX idx_ad_tracking_platform ON ad_campaign_tracking(platform);

-- Ad campaign creation requests
CREATE TABLE ad_campaign_requests (
    id SERIAL PRIMARY KEY,
    order_item_id INTEGER NOT NULL REFERENCES promotion_order_items(id) ON DELETE CASCADE,
    organizer_id INTEGER NOT NULL,
    event_id INTEGER,
    platforms ad_platform[] NOT NULL,
    campaign_name VARCHAR(255),
    campaign_objective VARCHAR(100),
    target_audience JSONB DEFAULT '{}',
    budget DECIMAL(10,2) NOT NULL,
    budget_type VARCHAR(20) DEFAULT 'total',
    duration_days INTEGER,
    start_date DATE,
    end_date DATE,
    creative_assets JSONB DEFAULT '[]',
    ad_copy TEXT,
    landing_url VARCHAR(500),
    notes TEXT,
    status VARCHAR(20) DEFAULT 'pending_review',
    assigned_to INTEGER,
    reviewed_at TIMESTAMP WITH TIME ZONE,
    reviewed_by INTEGER,
    rejection_reason TEXT,
    external_campaign_ids JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- EVENT FEATURING TABLES
-- ============================================

-- Featured events slots
CREATE TABLE featured_event_slots (
    id SERIAL PRIMARY KEY,
    order_item_id INTEGER NOT NULL REFERENCES promotion_order_items(id) ON DELETE CASCADE,
    organizer_id INTEGER NOT NULL,
    event_id INTEGER NOT NULL,
    placement featuring_placement NOT NULL,
    placement_category VARCHAR(100),
    placement_genre VARCHAR(100),
    placement_city VARCHAR(100),
    position INTEGER,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT false,
    impressions BIGINT DEFAULT 0,
    clicks BIGINT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_featured_slots_event ON featured_event_slots(event_id);
CREATE INDEX idx_featured_slots_placement ON featured_event_slots(placement);
CREATE INDEX idx_featured_slots_dates ON featured_event_slots(start_date, end_date);
CREATE INDEX idx_featured_slots_active ON featured_event_slots(is_active) WHERE is_active = true;

-- ============================================
-- PAYMENT & INVOICE TABLES
-- ============================================

-- Payment transactions
CREATE TABLE promotion_payments (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES promotion_orders(id) ON DELETE CASCADE,
    payment_provider VARCHAR(50) NOT NULL,
    external_payment_id VARCHAR(100),
    payment_method VARCHAR(50),
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RON',
    status VARCHAR(20) DEFAULT 'pending',
    error_code VARCHAR(50),
    error_message TEXT,
    provider_response JSONB DEFAULT '{}',
    paid_at TIMESTAMP WITH TIME ZONE,
    refunded_at TIMESTAMP WITH TIME ZONE,
    refund_amount DECIMAL(12,2),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Invoices
CREATE TABLE promotion_invoices (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES promotion_orders(id) ON DELETE CASCADE,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    organizer_id INTEGER NOT NULL,
    billing_name VARCHAR(255),
    billing_address TEXT,
    billing_city VARCHAR(100),
    billing_country VARCHAR(100),
    billing_postal_code VARCHAR(20),
    billing_vat_number VARCHAR(50),
    subtotal DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(5,2),
    tax_amount DECIMAL(10,2),
    total_amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'RON',
    status VARCHAR(20) DEFAULT 'issued',
    issued_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    due_at TIMESTAMP WITH TIME ZONE,
    paid_at TIMESTAMP WITH TIME ZONE,
    pdf_url VARCHAR(500),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

CREATE INDEX idx_promotion_orders_organizer ON promotion_orders(organizer_id);
CREATE INDEX idx_promotion_orders_event ON promotion_orders(event_id);
CREATE INDEX idx_promotion_orders_status ON promotion_orders(status);
CREATE INDEX idx_promotion_orders_created ON promotion_orders(created_at DESC);

CREATE INDEX idx_order_items_order ON promotion_order_items(order_id);
CREATE INDEX idx_order_items_type ON promotion_order_items(promotion_type_id);
CREATE INDEX idx_order_items_status ON promotion_order_items(status);

-- ============================================
-- TRIGGERS FOR UPDATED_AT
-- ============================================

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_promotion_types_updated_at BEFORE UPDATE ON promotion_types
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_promotion_options_updated_at BEFORE UPDATE ON promotion_options
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_promotion_orders_updated_at BEFORE UPDATE ON promotion_orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_promotion_order_items_updated_at BEFORE UPDATE ON promotion_order_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_email_campaigns_updated_at BEFORE UPDATE ON email_campaigns
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_ad_tracking_connections_updated_at BEFORE UPDATE ON ad_tracking_connections
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_ad_campaign_tracking_updated_at BEFORE UPDATE ON ad_campaign_tracking
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_ad_campaign_requests_updated_at BEFORE UPDATE ON ad_campaign_requests
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_featured_event_slots_updated_at BEFORE UPDATE ON featured_event_slots
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_promotion_payments_updated_at BEFORE UPDATE ON promotion_payments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
