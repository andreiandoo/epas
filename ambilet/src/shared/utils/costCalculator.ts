/**
 * Cost Calculator Utility
 * Helper functions for calculating promotion costs
 */

export interface PricingTier {
  minQuantity: number;
  maxQuantity: number | null;
  unitPrice: number;
}

/**
 * Find the applicable pricing tier for a given quantity
 */
export function findApplicableTier(
  tiers: PricingTier[],
  quantity: number
): PricingTier | null {
  return tiers.find(
    (tier) =>
      quantity >= tier.minQuantity &&
      (tier.maxQuantity === null || quantity <= tier.maxQuantity)
  ) || null;
}

/**
 * Calculate total price with tiered pricing
 */
export function calculateTieredPrice(
  tiers: PricingTier[],
  quantity: number,
  fallbackPrice: number = 0
): number {
  const tier = findApplicableTier(tiers, quantity);
  if (tier) {
    return tier.unitPrice * quantity;
  }
  return fallbackPrice * quantity;
}

/**
 * Calculate price with duration (for featuring)
 */
export function calculateDurationBasedPrice(
  unitPricePerDay: number,
  durationDays: number,
  costModifier: number = 1.0
): number {
  return unitPricePerDay * durationDays * costModifier;
}

/**
 * Calculate email marketing price
 */
export function calculateEmailMarketingPrice(
  tiers: PricingTier[],
  recipientCount: number,
  fallbackUnitPrice: number = 0.05
): { unitPrice: number; totalPrice: number } {
  const tier = findApplicableTier(tiers, recipientCount);
  const unitPrice = tier?.unitPrice || fallbackUnitPrice;
  const totalPrice = unitPrice * recipientCount;

  return {
    unitPrice: roundToDecimals(unitPrice, 4),
    totalPrice: roundToDecimals(totalPrice, 2),
  };
}

/**
 * Calculate ad campaign creation price
 */
export function calculateAdCreationPrice(
  setupFee: number,
  adBudget: number,
  managementFeePercent: number
): { setupFee: number; managementFee: number; totalServiceFee: number } {
  const managementFee = adBudget * (managementFeePercent / 100);
  const totalServiceFee = setupFee + managementFee;

  return {
    setupFee: roundToDecimals(setupFee, 2),
    managementFee: roundToDecimals(managementFee, 2),
    totalServiceFee: roundToDecimals(totalServiceFee, 2),
  };
}

/**
 * Calculate tax amount
 */
export function calculateTax(
  subtotal: number,
  taxRate: number
): number {
  return roundToDecimals(subtotal * (taxRate / 100), 2);
}

/**
 * Calculate final total
 */
export function calculateTotal(
  subtotal: number,
  discountAmount: number,
  taxRate: number
): { taxableAmount: number; taxAmount: number; total: number } {
  const taxableAmount = Math.max(0, subtotal - discountAmount);
  const taxAmount = calculateTax(taxableAmount, taxRate);
  const total = roundToDecimals(taxableAmount + taxAmount, 2);

  return {
    taxableAmount: roundToDecimals(taxableAmount, 2),
    taxAmount,
    total,
  };
}

/**
 * Round to specified decimal places
 */
export function roundToDecimals(value: number, decimals: number): number {
  const multiplier = Math.pow(10, decimals);
  return Math.round(value * multiplier) / multiplier;
}

/**
 * Format currency amount
 */
export function formatCurrency(
  amount: number,
  currency: string = 'RON',
  locale: string = 'ro-RO'
): string {
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

/**
 * Calculate volume discount percentage
 */
export function calculateVolumeDiscount(
  tiers: PricingTier[],
  quantity: number
): number {
  if (tiers.length < 2) return 0;

  const baseTier = tiers[0];
  const applicableTier = findApplicableTier(tiers, quantity);

  if (!applicableTier || applicableTier.unitPrice >= baseTier.unitPrice) {
    return 0;
  }

  return roundToDecimals(
    ((baseTier.unitPrice - applicableTier.unitPrice) / baseTier.unitPrice) * 100,
    1
  );
}

/**
 * Validate minimum order amount
 */
export function validateMinimumOrder(
  total: number,
  minimumAmount: number
): { isValid: boolean; shortfall: number } {
  const shortfall = Math.max(0, minimumAmount - total);
  return {
    isValid: total >= minimumAmount,
    shortfall: roundToDecimals(shortfall, 2),
  };
}

/**
 * Calculate daily budget from total budget and duration
 */
export function calculateDailyBudget(
  totalBudget: number,
  durationDays: number
): number {
  if (durationDays <= 0) return 0;
  return roundToDecimals(totalBudget / durationDays, 2);
}

export default {
  findApplicableTier,
  calculateTieredPrice,
  calculateDurationBasedPrice,
  calculateEmailMarketingPrice,
  calculateAdCreationPrice,
  calculateTax,
  calculateTotal,
  roundToDecimals,
  formatCurrency,
  calculateVolumeDiscount,
  validateMinimumOrder,
  calculateDailyBudget,
};
