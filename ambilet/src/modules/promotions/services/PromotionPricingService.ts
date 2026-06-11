/**
 * PromotionPricingService
 * Handles all cost calculations for promotions
 */

import { PromotionService } from './PromotionService';
import {
  CostBreakdown,
  CostBreakdownItem,
  CreateOrderItemDTO,
  PromotionCategory,
  CostModel,
} from '../types/promotion.types';

interface PricingConfig {
  defaultTaxRate: number;
  currency: string;
  minimumOrderAmount: number;
}

const DEFAULT_PRICING_CONFIG: PricingConfig = {
  defaultTaxRate: 19.0, // Romanian VAT
  currency: 'RON',
  minimumOrderAmount: 10.0,
};

export class PromotionPricingService {
  private promotionService: PromotionService;
  private config: PricingConfig;

  constructor(promotionService: PromotionService, config?: Partial<PricingConfig>) {
    this.promotionService = promotionService;
    this.config = { ...DEFAULT_PRICING_CONFIG, ...config };
  }

  /**
   * Calculate cost breakdown for a list of order items
   */
  async calculateOrderCost(items: CreateOrderItemDTO[]): Promise<CostBreakdown> {
    const breakdownItems: CostBreakdownItem[] = [];
    let subtotal = 0;

    for (const item of items) {
      const itemCost = await this.calculateItemCost(item);
      breakdownItems.push(itemCost);
      subtotal += itemCost.totalPrice;
    }

    // Round subtotal
    subtotal = Math.round(subtotal * 100) / 100;

    // Calculate tax
    const taxAmount = Math.round((subtotal * this.config.defaultTaxRate / 100) * 100) / 100;
    const total = Math.round((subtotal + taxAmount) * 100) / 100;

    return {
      items: breakdownItems,
      subtotal,
      discountAmount: 0,
      taxRate: this.config.defaultTaxRate,
      taxAmount,
      total,
      currency: this.config.currency,
    };
  }

  /**
   * Calculate cost for a single order item
   */
  async calculateItemCost(item: CreateOrderItemDTO): Promise<CostBreakdownItem> {
    const promotionType = await this.promotionService.getPromotionTypeById(item.promotionTypeId);
    const promotionOption = await this.promotionService.getPromotionOptionById(item.promotionOptionId);

    if (!promotionType || !promotionOption) {
      throw new Error(`Invalid promotion type or option: ${item.promotionTypeId}/${item.promotionOptionId}`);
    }

    // Validate option belongs to type
    const isValid = await this.promotionService.validateOptionBelongsToType(
      item.promotionOptionId,
      item.promotionTypeId
    );

    if (!isValid) {
      throw new Error(`Option ${item.promotionOptionId} does not belong to type ${item.promotionTypeId}`);
    }

    let quantity = item.quantity || 1;
    let durationDays = item.durationDays || 1;
    let unitPrice = 0;
    let totalPrice = 0;

    // Calculate based on cost model
    switch (promotionType.costModel) {
      case CostModel.FIXED:
        // Fixed price per duration (featuring, ad creation)
        unitPrice = promotionOption.getEffectiveUnitPrice(durationDays);
        totalPrice = unitPrice * durationDays;
        break;

      case CostModel.PER_UNIT:
        // Per-unit pricing (email marketing)
        unitPrice = promotionOption.getEffectiveUnitPrice(quantity);
        totalPrice = unitPrice * quantity;
        break;

      case CostModel.SUBSCRIPTION:
        // Monthly subscription (ad tracking)
        unitPrice = promotionOption.getEffectiveUnitPrice(1);
        totalPrice = unitPrice * quantity; // quantity = number of months
        break;

      case CostModel.PERCENTAGE:
        // Percentage of ad spend (for managed campaigns)
        const budget = item.configuration?.budget || 0;
        const feePercent = promotionOption.getMetadataValue('management_fee_percent', 15);
        const setupFee = promotionOption.getEffectiveUnitPrice(1);
        unitPrice = setupFee;
        totalPrice = setupFee + (budget * feePercent / 100);
        break;

      default:
        throw new Error(`Unknown cost model: ${promotionType.costModel}`);
    }

    // Apply cost modifier from option
    totalPrice = totalPrice * promotionOption.costModifier;

    // Round to 2 decimal places
    unitPrice = Math.round(unitPrice * 100) / 100;
    totalPrice = Math.round(totalPrice * 100) / 100;

    return {
      promotionTypeId: item.promotionTypeId,
      promotionOptionId: item.promotionOptionId,
      name: `${promotionType.name} - ${promotionOption.name}`,
      description: promotionOption.description || '',
      quantity: promotionType.costModel === CostModel.PER_UNIT ? quantity : durationDays,
      unitPrice,
      totalPrice,
    };
  }

  /**
   * Calculate email marketing cost preview
   */
  async calculateEmailMarketingCost(
    optionId: number,
    recipientCount: number
  ): Promise<{ unitPrice: number; totalPrice: number; tierName: string | null }> {
    const option = await this.promotionService.getPromotionOptionById(optionId);

    if (!option) {
      throw new Error(`Invalid option: ${optionId}`);
    }

    const unitPrice = option.getEffectiveUnitPrice(recipientCount);
    const totalPrice = Math.round((unitPrice * recipientCount) * 100) / 100;

    // Find the tier name for display
    let tierName: string | null = null;
    if (option.pricing) {
      const applicableTier = option.pricing.find(
        tier =>
          recipientCount >= tier.minQuantity &&
          (tier.maxQuantity === null || recipientCount <= tier.maxQuantity)
      );
      tierName = applicableTier?.tierName || null;
    }

    return { unitPrice, totalPrice, tierName };
  }

  /**
   * Calculate featuring cost for multiple placements
   */
  async calculateFeaturingCost(
    placements: { optionId: number; durationDays: number }[]
  ): Promise<CostBreakdown> {
    const items: CreateOrderItemDTO[] = placements.map(p => ({
      promotionTypeId: 1, // Assuming featuring type ID is 1
      promotionOptionId: p.optionId,
      durationDays: p.durationDays,
    }));

    return this.calculateOrderCost(items);
  }

  /**
   * Calculate ad creation cost
   */
  async calculateAdCreationCost(
    optionIds: number[],
    budget: number,
    durationDays: number
  ): Promise<CostBreakdown> {
    const items: CreateOrderItemDTO[] = optionIds.map(optionId => ({
      promotionTypeId: 4, // Assuming ad creation type ID is 4
      promotionOptionId: optionId,
      durationDays,
      configuration: { budget },
    }));

    return this.calculateOrderCost(items);
  }

  /**
   * Calculate ad tracking cost
   */
  async calculateAdTrackingCost(
    optionIds: number[],
    months: number = 1
  ): Promise<CostBreakdown> {
    const items: CreateOrderItemDTO[] = optionIds.map(optionId => ({
      promotionTypeId: 3, // Assuming ad tracking type ID is 3
      promotionOptionId: optionId,
      quantity: months,
    }));

    return this.calculateOrderCost(items);
  }

  /**
   * Apply discount code to cost breakdown
   */
  async applyDiscountCode(
    breakdown: CostBreakdown,
    discountCode: string
  ): Promise<CostBreakdown> {
    // TODO: Implement discount code lookup from database
    // For now, return breakdown unchanged

    const discount = await this.getDiscountByCode(discountCode);

    if (!discount) {
      return breakdown;
    }

    let discountAmount = 0;

    if (discount.type === 'percentage') {
      discountAmount = breakdown.subtotal * (discount.value / 100);
    } else if (discount.type === 'fixed') {
      discountAmount = Math.min(discount.value, breakdown.subtotal);
    }

    discountAmount = Math.round(discountAmount * 100) / 100;

    const newSubtotal = breakdown.subtotal - discountAmount;
    const newTaxAmount = Math.round((newSubtotal * breakdown.taxRate / 100) * 100) / 100;
    const newTotal = Math.round((newSubtotal + newTaxAmount) * 100) / 100;

    return {
      ...breakdown,
      discountAmount,
      taxAmount: newTaxAmount,
      total: newTotal,
    };
  }

  /**
   * Get discount code details (stub - implement with actual DB)
   */
  private async getDiscountByCode(
    code: string
  ): Promise<{ type: 'percentage' | 'fixed'; value: number } | null> {
    // TODO: Implement actual discount code lookup
    // This is a placeholder
    const discountCodes: Record<string, { type: 'percentage' | 'fixed'; value: number }> = {
      'WELCOME10': { type: 'percentage', value: 10 },
      'FIRST50': { type: 'fixed', value: 50 },
    };

    return discountCodes[code.toUpperCase()] || null;
  }

  /**
   * Validate minimum order amount
   */
  validateMinimumOrderAmount(breakdown: CostBreakdown): boolean {
    return breakdown.total >= this.config.minimumOrderAmount;
  }

  /**
   * Get pricing summary for display
   */
  formatPricingSummary(breakdown: CostBreakdown): string[] {
    const lines: string[] = [];

    for (const item of breakdown.items) {
      lines.push(`${item.name}: ${item.totalPrice.toFixed(2)} ${breakdown.currency}`);
    }

    lines.push('---');
    lines.push(`Subtotal: ${breakdown.subtotal.toFixed(2)} ${breakdown.currency}`);

    if (breakdown.discountAmount > 0) {
      lines.push(`Discount: -${breakdown.discountAmount.toFixed(2)} ${breakdown.currency}`);
    }

    lines.push(`Tax (${breakdown.taxRate}%): ${breakdown.taxAmount.toFixed(2)} ${breakdown.currency}`);
    lines.push(`Total: ${breakdown.total.toFixed(2)} ${breakdown.currency}`);

    return lines;
  }
}

export default PromotionPricingService;
