/**
 * Promotion Types and Interfaces
 * Core type definitions for the Organizer Promotions Feature
 */

// ============================================
// ENUMS
// ============================================

export enum PromotionCategory {
  FEATURING = 'featuring',
  EMAIL_MARKETING = 'email_marketing',
  AD_TRACKING = 'ad_tracking',
  AD_CREATION = 'ad_creation',
}

export enum CostModel {
  FIXED = 'fixed',
  PER_UNIT = 'per_unit',
  PERCENTAGE = 'percentage',
  SUBSCRIPTION = 'subscription',
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

export enum OrderItemStatus {
  PENDING = 'pending',
  ACTIVE = 'active',
  COMPLETED = 'completed',
  CANCELLED = 'cancelled',
}

export enum EmailRecipientStatus {
  PENDING = 'pending',
  SENT = 'sent',
  DELIVERED = 'delivered',
  OPENED = 'opened',
  CLICKED = 'clicked',
  BOUNCED = 'bounced',
  UNSUBSCRIBED = 'unsubscribed',
}

export enum AdPlatform {
  FACEBOOK = 'facebook',
  GOOGLE = 'google',
  TIKTOK = 'tiktok',
}

export enum AudienceType {
  WHOLE_DATABASE = 'whole_database',
  FILTERED_DATABASE = 'filtered_database',
  PAST_CLIENTS = 'past_clients',
}

export enum FeaturingPlacement {
  HOME_PAGE = 'home_page',
  CATEGORY_PAGE = 'category_page',
  GENRE_PAGE = 'genre_page',
  CITY_PAGE = 'city_page',
  GENERAL = 'general',
}

// ============================================
// CORE INTERFACES
// ============================================

export interface PromotionType {
  id: number;
  name: string;
  slug: string;
  category: PromotionCategory;
  description: string | null;
  icon: string | null;
  baseCost: number;
  costModel: CostModel;
  isActive: boolean;
  sortOrder: number;
  createdAt: Date;
  updatedAt: Date;
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
  sortOrder: number;
  metadata: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
  pricing?: PromotionPricing[];
}

export interface PromotionPricing {
  id: number;
  promotionOptionId: number;
  tierName: string | null;
  minQuantity: number;
  maxQuantity: number | null;
  unitPrice: number;
  currency: string;
  effectiveFrom: Date;
  effectiveUntil: Date | null;
  isActive: boolean;
  createdAt: Date;
}

// ============================================
// ORDER INTERFACES
// ============================================

export interface PromotionOrder {
  id: number;
  orderNumber: string;
  organizerId: number;
  eventId: number | null;
  status: OrderStatus;
  currency: string;
  subtotal: number;
  discountAmount: number;
  discountCode: string | null;
  taxRate: number;
  taxAmount: number;
  totalAmount: number;
  paymentMethod: string | null;
  paymentId: string | null;
  paymentProvider: string | null;
  paidAt: Date | null;
  expiresAt: Date | null;
  notes: string | null;
  metadata: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
  items?: PromotionOrderItem[];
}

export interface PromotionOrderItem {
  id: number;
  orderId: number;
  promotionTypeId: number;
  promotionOptionId: number;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  startDate: Date | null;
  endDate: Date | null;
  durationDays: number | null;
  status: OrderItemStatus;
  configuration: Record<string, any>;
  metadata: Record<string, any>;
  activatedAt: Date | null;
  completedAt: Date | null;
  createdAt: Date;
  updatedAt: Date;
  promotionType?: PromotionType;
  promotionOption?: PromotionOption;
}

// ============================================
// EMAIL MARKETING INTERFACES
// ============================================

export interface EmailCampaign {
  id: number;
  orderItemId: number;
  organizerId: number;
  eventId: number | null;
  audienceType: AudienceType;
  audienceFilters: AudienceFilters;
  subject: string;
  previewText: string | null;
  templateId: number | null;
  htmlContent: string | null;
  plainTextContent: string | null;
  totalRecipients: number;
  sentCount: number;
  deliveredCount: number;
  openedCount: number;
  clickedCount: number;
  bouncedCount: number;
  unsubscribedCount: number;
  scheduledAt: Date | null;
  startedAt: Date | null;
  completedAt: Date | null;
  status: string;
  createdAt: Date;
  updatedAt: Date;
}

export interface EmailCampaignRecipient {
  id: number;
  campaignId: number;
  userId: number | null;
  email: string;
  firstName: string | null;
  lastName: string | null;
  status: EmailRecipientStatus;
  sentAt: Date | null;
  deliveredAt: Date | null;
  openedAt: Date | null;
  clickedAt: Date | null;
  bouncedAt: Date | null;
  bounceReason: string | null;
  unsubscribedAt: Date | null;
  metadata: Record<string, any>;
  createdAt: Date;
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

// ============================================
// AD TRACKING INTERFACES
// ============================================

export interface AdTrackingConnection {
  id: number;
  organizerId: number;
  platform: AdPlatform;
  accountId: string | null;
  accountName: string | null;
  accessToken: string | null;
  refreshToken: string | null;
  tokenExpiresAt: Date | null;
  isActive: boolean;
  connectedAt: Date;
  lastSyncedAt: Date | null;
  metadata: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
}

export interface AdCampaignTracking {
  id: number;
  orderItemId: number | null;
  connectionId: number | null;
  organizerId: number;
  platform: AdPlatform;
  externalCampaignId: string;
  campaignName: string | null;
  campaignStatus: string | null;
  objective: string | null;
  budget: number | null;
  budgetType: string | null;
  startDate: Date | null;
  endDate: Date | null;
  impressions: number;
  reach: number;
  clicks: number;
  conversions: number;
  spend: number;
  cpc: number | null;
  cpm: number | null;
  ctr: number | null;
  conversionRate: number | null;
  roas: number | null;
  lastSyncedAt: Date | null;
  trackingData: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
}

// ============================================
// AD CREATION INTERFACES
// ============================================

export interface AdCampaignRequest {
  id: number;
  orderItemId: number;
  organizerId: number;
  eventId: number | null;
  platforms: AdPlatform[];
  campaignName: string | null;
  campaignObjective: string | null;
  targetAudience: TargetAudience;
  budget: number;
  budgetType: string;
  durationDays: number | null;
  startDate: Date | null;
  endDate: Date | null;
  creativeAssets: CreativeAsset[];
  adCopy: string | null;
  landingUrl: string | null;
  notes: string | null;
  status: string;
  assignedTo: number | null;
  reviewedAt: Date | null;
  reviewedBy: number | null;
  rejectionReason: string | null;
  externalCampaignIds: Record<string, string>;
  createdAt: Date;
  updatedAt: Date;
}

export interface TargetAudience {
  locations?: string[];
  ageRange?: { min: number; max: number };
  genders?: string[];
  interests?: string[];
  behaviors?: string[];
  customAudiences?: string[];
  lookalikeAudiences?: string[];
}

export interface CreativeAsset {
  type: 'image' | 'video' | 'carousel';
  url: string;
  thumbnailUrl?: string;
  title?: string;
  description?: string;
}

// ============================================
// EVENT FEATURING INTERFACES
// ============================================

export interface FeaturedEventSlot {
  id: number;
  orderItemId: number;
  organizerId: number;
  eventId: number;
  placement: FeaturingPlacement;
  placementCategory: string | null;
  placementGenre: string | null;
  placementCity: string | null;
  position: number | null;
  startDate: Date;
  endDate: Date;
  isActive: boolean;
  impressions: number;
  clicks: number;
  createdAt: Date;
  updatedAt: Date;
}

// ============================================
// PAYMENT INTERFACES
// ============================================

export interface PromotionPayment {
  id: number;
  orderId: number;
  paymentProvider: string;
  externalPaymentId: string | null;
  paymentMethod: string | null;
  amount: number;
  currency: string;
  status: string;
  errorCode: string | null;
  errorMessage: string | null;
  providerResponse: Record<string, any>;
  paidAt: Date | null;
  refundedAt: Date | null;
  refundAmount: number | null;
  createdAt: Date;
  updatedAt: Date;
}

export interface PromotionInvoice {
  id: number;
  orderId: number;
  invoiceNumber: string;
  organizerId: number;
  billingName: string | null;
  billingAddress: string | null;
  billingCity: string | null;
  billingCountry: string | null;
  billingPostalCode: string | null;
  billingVatNumber: string | null;
  subtotal: number;
  taxRate: number | null;
  taxAmount: number | null;
  totalAmount: number;
  currency: string;
  status: string;
  issuedAt: Date;
  dueAt: Date | null;
  paidAt: Date | null;
  pdfUrl: string | null;
  createdAt: Date;
}

// ============================================
// DTO INTERFACES (Data Transfer Objects)
// ============================================

export interface CreateOrderDTO {
  eventId?: number;
  items: CreateOrderItemDTO[];
  discountCode?: string;
  notes?: string;
}

export interface CreateOrderItemDTO {
  promotionTypeId: number;
  promotionOptionId: number;
  quantity?: number;
  startDate?: string;
  endDate?: string;
  durationDays?: number;
  configuration?: Record<string, any>;
}

export interface UpdateOrderDTO {
  items?: CreateOrderItemDTO[];
  discountCode?: string;
  notes?: string;
}

export interface CheckoutDTO {
  paymentMethod: string;
  billingDetails?: BillingDetails;
  returnUrl?: string;
}

export interface BillingDetails {
  name: string;
  address: string;
  city: string;
  country: string;
  postalCode: string;
  vatNumber?: string;
}

export interface EmailCampaignConfigDTO {
  audienceType: AudienceType;
  audienceFilters?: AudienceFilters;
  subject: string;
  previewText?: string;
  htmlContent?: string;
  plainTextContent?: string;
  scheduledAt?: string;
}

export interface AdCampaignRequestDTO {
  platforms: AdPlatform[];
  campaignName?: string;
  campaignObjective?: string;
  targetAudience?: TargetAudience;
  budget: number;
  budgetType?: string;
  durationDays?: number;
  startDate?: string;
  endDate?: string;
  adCopy?: string;
  landingUrl?: string;
  notes?: string;
}

// ============================================
// RESPONSE INTERFACES
// ============================================

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

export interface AudienceCountResponse {
  audienceType: AudienceType;
  count: number;
  estimatedCost: number;
  unitPrice: number;
  filters?: AudienceFilters;
}

export interface PaymentIntentResponse {
  paymentIntentId: string;
  clientSecret: string;
  amount: number;
  currency: string;
  status: string;
}

export interface PromotionAnalytics {
  orderId: number;
  totalSpent: number;
  featuring?: FeaturingAnalytics;
  emailMarketing?: EmailMarketingAnalytics;
  adTracking?: AdTrackingAnalytics;
}

export interface FeaturingAnalytics {
  totalImpressions: number;
  totalClicks: number;
  ctr: number;
  placements: {
    placement: FeaturingPlacement;
    impressions: number;
    clicks: number;
  }[];
}

export interface EmailMarketingAnalytics {
  totalSent: number;
  delivered: number;
  opened: number;
  clicked: number;
  bounced: number;
  unsubscribed: number;
  openRate: number;
  clickRate: number;
  bounceRate: number;
}

export interface AdTrackingAnalytics {
  platforms: {
    platform: AdPlatform;
    impressions: number;
    clicks: number;
    conversions: number;
    spend: number;
    cpc: number;
    ctr: number;
    roas: number;
  }[];
  totalImpressions: number;
  totalClicks: number;
  totalConversions: number;
  totalSpend: number;
}
