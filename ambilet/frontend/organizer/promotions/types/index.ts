/**
 * Frontend Types for Organizer Promotions
 */

// Enums
export enum PromotionCategory {
  FEATURING = 'featuring',
  EMAIL_MARKETING = 'email_marketing',
  AD_TRACKING = 'ad_tracking',
  AD_CREATION = 'ad_creation',
}

export enum OrderStatus {
  DRAFT = 'draft',
  PENDING_PAYMENT = 'pending_payment',
  PAID = 'paid',
  PROCESSING = 'processing',
  ACTIVE = 'active',
  COMPLETED = 'completed',
  CANCELLED = 'cancelled',
  REFUNDED = 'refunded',
}

export enum AudienceType {
  WHOLE_DATABASE = 'whole_database',
  FILTERED_DATABASE = 'filtered_database',
  PAST_CLIENTS = 'past_clients',
}

export enum AdPlatform {
  FACEBOOK = 'facebook',
  GOOGLE = 'google',
  TIKTOK = 'tiktok',
}

// Interfaces
export interface PromotionType {
  id: number;
  name: string;
  slug: string;
  category: PromotionCategory;
  categoryDisplayName: string;
  description: string | null;
  icon: string | null;
  baseCost: number;
  costModel: string;
  isActive: boolean;
  options?: PromotionOption[];
}

export interface PromotionOption {
  id: number;
  promotionTypeId: number;
  name: string;
  code: string;
  description: string | null;
  costModifier: number;
  unitCost: number | null;
  minQuantity: number | null;
  maxQuantity: number | null;
  minDurationDays: number | null;
  maxDurationDays: number | null;
  isActive: boolean;
  metadata: Record<string, any>;
  pricing?: PricingTier[];
}

export interface PricingTier {
  id: number;
  tierName: string | null;
  minQuantity: number;
  maxQuantity: number | null;
  unitPrice: number;
  currency: string;
}

export interface PromotionOrder {
  id: number;
  orderNumber: string;
  organizerId: number;
  eventId: number | null;
  status: OrderStatus;
  statusDisplayName: string;
  statusColor: string;
  currency: string;
  subtotal: number;
  discountAmount: number;
  discountCode: string | null;
  taxRate: number;
  taxAmount: number;
  totalAmount: number;
  items?: PromotionOrderItem[];
  canBeModified: boolean;
  canBeCancelled: boolean;
  isPaid: boolean;
  isExpired: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface PromotionOrderItem {
  id: number;
  orderId: number;
  promotionTypeId: number;
  promotionOptionId: number;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  startDate: string | null;
  endDate: string | null;
  durationDays: number | null;
  status: string;
  statusDisplayName: string;
  statusColor: string;
  configuration: Record<string, any>;
  displayName: string;
  promotionType?: PromotionType;
  promotionOption?: PromotionOption;
}

export interface CostBreakdown {
  items: CostBreakdownItem[];
  subtotal: number;
  discountAmount: number;
  taxRate: number;
  taxAmount: number;
  total: number;
  currency: string;
}

export interface CostBreakdownItem {
  promotionTypeId: number;
  promotionOptionId: number;
  name: string;
  description: string;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
}

export interface AudienceCount {
  audienceType: AudienceType;
  count: number;
  estimatedCost: number;
  unitPrice: number;
  filters?: AudienceFilters;
}

export interface AudienceFilters {
  cities?: string[];
  countries?: string[];
  ageRange?: { min: number; max: number };
  gender?: string[];
  interests?: string[];
  eventCategories?: string[];
  purchasedInLastDays?: number;
  eventIds?: number[];
}

export interface AdTrackingConnection {
  id: number;
  platform: AdPlatform;
  accountId: string | null;
  accountName: string | null;
  connectedAt: string;
  lastSyncedAt: string | null;
}

export interface AdCampaign {
  id: number;
  platform: AdPlatform;
  externalCampaignId: string;
  campaignName: string | null;
  campaignStatus: string | null;
  impressions: number;
  clicks: number;
  conversions: number;
  spend: number;
  cpc: number | null;
  ctr: number | null;
}

// Form State Types
export interface FeaturingFormState {
  selectedPlacements: string[];
  durationDays: number;
  startDate: string;
}

export interface EmailMarketingFormState {
  audienceType: AudienceType;
  filters: AudienceFilters;
  subject: string;
  previewText: string;
  scheduledAt: string | null;
}

export interface AdTrackingFormState {
  selectedPlatforms: AdPlatform[];
  months: number;
}

export interface AdCreationFormState {
  selectedPlatforms: AdPlatform[];
  campaignName: string;
  budget: number;
  durationDays: number;
  startDate: string;
  targetAudience: {
    locations: string[];
    ageRange: { min: number; max: number };
    interests: string[];
  };
  adCopy: string;
  landingUrl: string;
}

// Cart/Order State
export interface CartItem {
  promotionTypeId: number;
  promotionOptionId: number;
  quantity?: number;
  durationDays?: number;
  startDate?: string;
  endDate?: string;
  configuration?: Record<string, any>;
}

export interface PaymentIntent {
  paymentIntentId: string;
  clientSecret: string;
  amount: number;
  currency: string;
  status: string;
}

// Statistics
export interface PromotionStatistics {
  totalOrders: number;
  totalSpent: number;
  activePromotions: number;
  completedPromotions: number;
}
