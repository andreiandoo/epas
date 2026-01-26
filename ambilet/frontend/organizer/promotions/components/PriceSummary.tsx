/**
 * PriceSummary Component
 * Displays order summary with pricing breakdown
 */

import React, { useState } from 'react';
import { CostBreakdown, CostBreakdownItem } from '../types';

interface PriceSummaryProps {
  costBreakdown: CostBreakdown;
  onApplyDiscount?: (code: string) => Promise<void>;
  discountCode?: string | null;
  isLoading?: boolean;
}

export const PriceSummary: React.FC<PriceSummaryProps> = ({
  costBreakdown,
  onApplyDiscount,
  discountCode,
  isLoading = false,
}) => {
  const [discountInput, setDiscountInput] = useState<string>(discountCode || '');
  const [isApplying, setIsApplying] = useState<boolean>(false);
  const [discountError, setDiscountError] = useState<string | null>(null);

  const handleApplyDiscount = async () => {
    if (!discountInput.trim() || !onApplyDiscount) return;

    setIsApplying(true);
    setDiscountError(null);

    try {
      await onApplyDiscount(discountInput.trim());
    } catch (error: any) {
      setDiscountError(error.message || 'Invalid discount code');
    } finally {
      setIsApplying(false);
    }
  };

  const formatCurrency = (amount: number): string => {
    return `${amount.toFixed(2)} ${costBreakdown.currency}`;
  };

  return (
    <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
      {/* Header */}
      <div className="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <h3 className="text-lg font-semibold text-gray-900">Order Summary</h3>
      </div>

      {/* Items */}
      <div className="p-6">
        {isLoading ? (
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse flex justify-between">
                <div className="h-4 bg-gray-200 rounded w-2/3"></div>
                <div className="h-4 bg-gray-200 rounded w-1/5"></div>
              </div>
            ))}
          </div>
        ) : costBreakdown.items.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            <p>No items in your order yet.</p>
            <p className="text-sm mt-1">Select promotions to see pricing.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {/* Line Items */}
            {costBreakdown.items.map((item: CostBreakdownItem, index: number) => (
              <div key={index} className="flex justify-between items-start">
                <div className="flex-1 pr-4">
                  <div className="font-medium text-gray-900">{item.name}</div>
                  <div className="text-sm text-gray-500">
                    {item.quantity > 1 && (
                      <span>
                        {item.quantity} × {formatCurrency(item.unitPrice)}
                      </span>
                    )}
                    {item.description && (
                      <span className="block text-xs">{item.description}</span>
                    )}
                  </div>
                </div>
                <div className="font-medium text-gray-900 whitespace-nowrap">
                  {formatCurrency(item.totalPrice)}
                </div>
              </div>
            ))}

            {/* Divider */}
            <div className="border-t border-gray-200 pt-4 mt-4">
              {/* Subtotal */}
              <div className="flex justify-between text-sm">
                <span className="text-gray-600">Subtotal</span>
                <span className="font-medium">{formatCurrency(costBreakdown.subtotal)}</span>
              </div>

              {/* Discount */}
              {costBreakdown.discountAmount > 0 && (
                <div className="flex justify-between text-sm mt-2">
                  <span className="text-green-600">
                    Discount {discountCode && `(${discountCode})`}
                  </span>
                  <span className="font-medium text-green-600">
                    -{formatCurrency(costBreakdown.discountAmount)}
                  </span>
                </div>
              )}

              {/* Tax */}
              <div className="flex justify-between text-sm mt-2">
                <span className="text-gray-600">
                  Tax ({costBreakdown.taxRate}% VAT)
                </span>
                <span className="font-medium">{formatCurrency(costBreakdown.taxAmount)}</span>
              </div>
            </div>

            {/* Total */}
            <div className="border-t border-gray-200 pt-4 mt-4">
              <div className="flex justify-between items-center">
                <span className="text-lg font-semibold text-gray-900">Total</span>
                <span className="text-2xl font-bold text-primary-600">
                  {formatCurrency(costBreakdown.total)}
                </span>
              </div>
            </div>
          </div>
        )}

        {/* Discount Code Input */}
        {onApplyDiscount && costBreakdown.items.length > 0 && !discountCode && (
          <div className="mt-6 pt-4 border-t border-gray-200">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Have a discount code?
            </label>
            <div className="flex gap-2">
              <input
                type="text"
                value={discountInput}
                onChange={(e) => setDiscountInput(e.target.value.toUpperCase())}
                placeholder="Enter code"
                className="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
              />
              <button
                type="button"
                onClick={handleApplyDiscount}
                disabled={isApplying || !discountInput.trim()}
                className="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isApplying ? 'Applying...' : 'Apply'}
              </button>
            </div>
            {discountError && (
              <p className="text-sm text-red-600 mt-1">{discountError}</p>
            )}
          </div>
        )}

        {/* Applied Discount */}
        {discountCode && costBreakdown.discountAmount > 0 && (
          <div className="mt-6 pt-4 border-t border-gray-200">
            <div className="flex items-center justify-between bg-green-50 rounded-lg p-3">
              <div className="flex items-center">
                <span className="text-green-600 mr-2">✓</span>
                <span className="text-sm text-green-800">
                  Discount code <strong>{discountCode}</strong> applied!
                </span>
              </div>
              <button
                type="button"
                onClick={() => onApplyDiscount?.('')}
                className="text-sm text-green-600 hover:text-green-700"
              >
                Remove
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Payment Notice */}
      <div className="bg-blue-50 px-6 py-4 border-t border-blue-100">
        <div className="flex items-start">
          <svg
            className="w-5 h-5 text-blue-500 mr-3 mt-0.5"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fillRule="evenodd"
              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
              clipRule="evenodd"
            />
          </svg>
          <div className="text-sm text-blue-800">
            <p className="font-medium">Payment Required</p>
            <p className="mt-1">
              Your promotions will be activated after payment is completed.
              Secure payment processing by Stripe.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PriceSummary;
