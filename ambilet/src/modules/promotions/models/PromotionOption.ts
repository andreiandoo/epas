/**
 * PromotionOption Model
 * Represents sub-options for each promotion type (placements, platforms, etc.)
 */

import {
  PromotionOption as IPromotionOption,
  PromotionPricing,
} from '../types/promotion.types';

export class PromotionOption implements IPromotionOption {
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

  constructor(data: Partial<IPromotionOption>) {
    this.id = data.id || 0;
    this.promotionTypeId = data.promotionTypeId || 0;
    this.name = data.name || '';
    this.code = data.code || '';
    this.description = data.description || null;
    this.costModifier = data.costModifier ?? 1.0;
    this.unitCost = data.unitCost ?? null;
    this.minQuantity = data.minQuantity ?? null;
    this.maxQuantity = data.maxQuantity ?? null;
    this.minDurationDays = data.minDurationDays ?? null;
    this.maxDurationDays = data.maxDurationDays ?? null;
    this.isActive = data.isActive ?? true;
    this.sortOrder = data.sortOrder || 0;
    this.metadata = data.metadata || {};
    this.createdAt = data.createdAt ? new Date(data.createdAt) : new Date();
    this.updatedAt = data.updatedAt ? new Date(data.updatedAt) : new Date();
    this.pricing = data.pricing;
  }

  /**
   * Get the effective unit price for a given quantity
   * Uses tiered pricing if available
   */
  getEffectiveUnitPrice(quantity: number = 1): number {
    if (!this.pricing || this.pricing.length === 0) {
      return this.unitCost || 0;
    }

    // Find the applicable pricing tier
    const applicableTier = this.pricing.find(tier => {
      const minOk = quantity >= tier.minQuantity;
      const maxOk = tier.maxQuantity === null || quantity <= tier.maxQuantity;
      const dateOk = tier.isActive &&
        new Date(tier.effectiveFrom) <= new Date() &&
        (tier.effectiveUntil === null || new Date(tier.effectiveUntil) >= new Date());
      return minOk && maxOk && dateOk;
    });

    if (applicableTier) {
      return applicableTier.unitPrice;
    }

    // Default to unit cost if no tier matches
    return this.unitCost || 0;
  }

  /**
   * Calculate total cost for a given quantity and duration
   */
  calculateCost(quantity: number = 1, durationDays: number = 1): number {
    const unitPrice = this.getEffectiveUnitPrice(quantity);

    // For duration-based pricing (featuring)
    if (this.minDurationDays !== null) {
      return unitPrice * durationDays * this.costModifier;
    }

    // For quantity-based pricing (email marketing)
    return unitPrice * quantity * this.costModifier;
  }

  /**
   * Check if quantity is within allowed range
   */
  isQuantityValid(quantity: number): boolean {
    if (this.minQuantity !== null && quantity < this.minQuantity) {
      return false;
    }
    if (this.maxQuantity !== null && quantity > this.maxQuantity) {
      return false;
    }
    return true;
  }

  /**
   * Check if duration is within allowed range
   */
  isDurationValid(days: number): boolean {
    if (this.minDurationDays !== null && days < this.minDurationDays) {
      return false;
    }
    if (this.maxDurationDays !== null && days > this.maxDurationDays) {
      return false;
    }
    return true;
  }

  /**
   * Get metadata value by key
   */
  getMetadataValue<T>(key: string, defaultValue: T): T {
    return (this.metadata[key] as T) ?? defaultValue;
  }

  /**
   * Check if this option requires OAuth connection
   */
  requiresOAuth(): boolean {
    return this.getMetadataValue('requires_oauth', false);
  }

  /**
   * Get platform from metadata (for ad-related options)
   */
  getPlatform(): string | null {
    return this.getMetadataValue<string | null>('platform', null);
  }

  /**
   * Get audience type from metadata (for email marketing)
   */
  getAudienceType(): string | null {
    return this.getMetadataValue<string | null>('audience_type', null);
  }

  /**
   * Convert database row to model
   */
  static fromDatabase(row: any): PromotionOption {
    return new PromotionOption({
      id: row.id,
      promotionTypeId: row.promotion_type_id,
      name: row.name,
      code: row.code,
      description: row.description,
      costModifier: parseFloat(row.cost_modifier),
      unitCost: row.unit_cost ? parseFloat(row.unit_cost) : null,
      minQuantity: row.min_quantity,
      maxQuantity: row.max_quantity,
      minDurationDays: row.min_duration_days,
      maxDurationDays: row.max_duration_days,
      isActive: row.is_active,
      sortOrder: row.sort_order,
      metadata: row.metadata || {},
      createdAt: row.created_at,
      updatedAt: row.updated_at,
    });
  }

  /**
   * Convert to JSON response
   */
  toJSON(): Record<string, any> {
    return {
      id: this.id,
      promotionTypeId: this.promotionTypeId,
      name: this.name,
      code: this.code,
      description: this.description,
      costModifier: this.costModifier,
      unitCost: this.unitCost,
      minQuantity: this.minQuantity,
      maxQuantity: this.maxQuantity,
      minDurationDays: this.minDurationDays,
      maxDurationDays: this.maxDurationDays,
      isActive: this.isActive,
      sortOrder: this.sortOrder,
      metadata: this.metadata,
      pricing: this.pricing,
      createdAt: this.createdAt.toISOString(),
      updatedAt: this.updatedAt.toISOString(),
    };
  }
}

export default PromotionOption;
