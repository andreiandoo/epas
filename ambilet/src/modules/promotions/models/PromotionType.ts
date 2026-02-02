/**
 * PromotionType Model
 * Represents the catalog of available promotion types
 */

import {
  PromotionType as IPromotionType,
  PromotionCategory,
  CostModel,
} from '../types/promotion.types';
import { PromotionOption } from './PromotionOption';

export class PromotionType implements IPromotionType {
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

  constructor(data: Partial<IPromotionType>) {
    this.id = data.id || 0;
    this.name = data.name || '';
    this.slug = data.slug || '';
    this.category = data.category || PromotionCategory.FEATURING;
    this.description = data.description || null;
    this.icon = data.icon || null;
    this.baseCost = data.baseCost || 0;
    this.costModel = data.costModel || CostModel.FIXED;
    this.isActive = data.isActive ?? true;
    this.sortOrder = data.sortOrder || 0;
    this.createdAt = data.createdAt ? new Date(data.createdAt) : new Date();
    this.updatedAt = data.updatedAt ? new Date(data.updatedAt) : new Date();
    this.options = data.options;
  }

  /**
   * Check if this promotion type supports per-unit pricing
   */
  isPerUnitPricing(): boolean {
    return this.costModel === CostModel.PER_UNIT;
  }

  /**
   * Check if this promotion type is subscription-based
   */
  isSubscription(): boolean {
    return this.costModel === CostModel.SUBSCRIPTION;
  }

  /**
   * Get the category display name
   */
  getCategoryDisplayName(): string {
    const displayNames: Record<PromotionCategory, string> = {
      [PromotionCategory.FEATURING]: 'Event Featuring',
      [PromotionCategory.EMAIL_MARKETING]: 'Email Marketing',
      [PromotionCategory.AD_TRACKING]: 'Ad Campaign Tracking',
      [PromotionCategory.AD_CREATION]: 'Ad Campaign Creation',
    };
    return displayNames[this.category];
  }

  /**
   * Convert database row to model
   */
  static fromDatabase(row: any): PromotionType {
    return new PromotionType({
      id: row.id,
      name: row.name,
      slug: row.slug,
      category: row.category as PromotionCategory,
      description: row.description,
      icon: row.icon,
      baseCost: parseFloat(row.base_cost),
      costModel: row.cost_model as CostModel,
      isActive: row.is_active,
      sortOrder: row.sort_order,
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
      name: this.name,
      slug: this.slug,
      category: this.category,
      categoryDisplayName: this.getCategoryDisplayName(),
      description: this.description,
      icon: this.icon,
      baseCost: this.baseCost,
      costModel: this.costModel,
      isActive: this.isActive,
      sortOrder: this.sortOrder,
      options: this.options?.map(opt => opt.toJSON()),
      createdAt: this.createdAt.toISOString(),
      updatedAt: this.updatedAt.toISOString(),
    };
  }
}

export default PromotionType;
