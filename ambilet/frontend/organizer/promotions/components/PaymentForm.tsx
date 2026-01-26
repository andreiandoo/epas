/**
 * PaymentForm Component
 * Handles payment method selection and processing
 */

import React, { useState } from 'react';
import { PaymentIntent } from '../types';

interface PaymentFormProps {
  paymentIntent: PaymentIntent | null;
  totalAmount: number;
  currency: string;
  onPaymentComplete: (paymentIntentId: string, paymentMethod: string) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
}

interface PaymentMethod {
  id: string;
  name: string;
  description: string;
  icon: string;
  enabled: boolean;
}

const paymentMethods: PaymentMethod[] = [
  {
    id: 'card',
    name: 'Credit/Debit Card',
    description: 'Pay securely with Visa, Mastercard, or other cards',
    icon: '💳',
    enabled: true,
  },
  {
    id: 'bank_transfer',
    name: 'Bank Transfer',
    description: 'Direct transfer (may take 1-3 business days)',
    icon: '🏦',
    enabled: true,
  },
];

export const PaymentForm: React.FC<PaymentFormProps> = ({
  paymentIntent,
  totalAmount,
  currency,
  onPaymentComplete,
  onCancel,
  isLoading = false,
}) => {
  const [selectedMethod, setSelectedMethod] = useState<string>('card');
  const [isProcessing, setIsProcessing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  // Card form state (simplified - in production use Stripe Elements)
  const [cardNumber, setCardNumber] = useState<string>('');
  const [expiryDate, setExpiryDate] = useState<string>('');
  const [cvv, setCvv] = useState<string>('');
  const [cardName, setCardName] = useState<string>('');

  const formatCardNumber = (value: string): string => {
    const numbers = value.replace(/\D/g, '');
    const groups = numbers.match(/.{1,4}/g) || [];
    return groups.join(' ').substring(0, 19);
  };

  const formatExpiryDate = (value: string): string => {
    const numbers = value.replace(/\D/g, '');
    if (numbers.length >= 2) {
      return `${numbers.substring(0, 2)}/${numbers.substring(2, 4)}`;
    }
    return numbers;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsProcessing(true);

    try {
      // Validate card details (simplified)
      if (selectedMethod === 'card') {
        if (!cardNumber || cardNumber.replace(/\s/g, '').length < 16) {
          throw new Error('Please enter a valid card number');
        }
        if (!expiryDate || expiryDate.length < 5) {
          throw new Error('Please enter a valid expiry date');
        }
        if (!cvv || cvv.length < 3) {
          throw new Error('Please enter a valid CVV');
        }
      }

      // In production, this would use Stripe.js to handle the payment
      // For now, we'll simulate the payment
      if (paymentIntent) {
        await onPaymentComplete(paymentIntent.paymentIntentId, selectedMethod);
      }
    } catch (err: any) {
      setError(err.message || 'Payment failed. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
      {/* Header */}
      <div className="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <h3 className="text-lg font-semibold text-gray-900">Payment Details</h3>
        <p className="text-sm text-gray-600 mt-1">
          Complete your payment to activate your promotions
        </p>
      </div>

      <form onSubmit={handleSubmit} className="p-6 space-y-6">
        {/* Payment Method Selection */}
        <div className="space-y-3">
          <label className="block text-sm font-medium text-gray-700">
            Payment Method
          </label>

          <div className="grid gap-3">
            {paymentMethods.map((method) => (
              <div
                key={method.id}
                className={`
                  relative flex items-center p-4 rounded-lg border-2 cursor-pointer
                  transition-all duration-200
                  ${selectedMethod === method.id
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300'
                  }
                  ${!method.enabled && 'opacity-50 cursor-not-allowed'}
                `}
                onClick={() => method.enabled && setSelectedMethod(method.id)}
              >
                {/* Radio */}
                <div
                  className={`
                    w-5 h-5 rounded-full border-2 mr-4 flex items-center justify-center
                    ${selectedMethod === method.id
                      ? 'border-primary-500 bg-primary-500'
                      : 'border-gray-300'
                    }
                  `}
                >
                  {selectedMethod === method.id && (
                    <div className="w-2 h-2 rounded-full bg-white" />
                  )}
                </div>

                {/* Icon */}
                <div className="text-2xl mr-4">{method.icon}</div>

                {/* Content */}
                <div className="flex-1">
                  <div className="font-medium text-gray-900">{method.name}</div>
                  <div className="text-sm text-gray-500">{method.description}</div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Card Form */}
        {selectedMethod === 'card' && (
          <div className="space-y-4 pt-4 border-t border-gray-200">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Cardholder Name
              </label>
              <input
                type="text"
                value={cardName}
                onChange={(e) => setCardName(e.target.value)}
                placeholder="John Doe"
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Card Number
              </label>
              <input
                type="text"
                value={cardNumber}
                onChange={(e) => setCardNumber(formatCardNumber(e.target.value))}
                placeholder="1234 5678 9012 3456"
                maxLength={19}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                required
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Expiry Date
                </label>
                <input
                  type="text"
                  value={expiryDate}
                  onChange={(e) => setExpiryDate(formatExpiryDate(e.target.value))}
                  placeholder="MM/YY"
                  maxLength={5}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  CVV
                </label>
                <input
                  type="text"
                  value={cvv}
                  onChange={(e) => setCvv(e.target.value.replace(/\D/g, '').substring(0, 4))}
                  placeholder="123"
                  maxLength={4}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-mono"
                  required
                />
              </div>
            </div>
          </div>
        )}

        {/* Bank Transfer Info */}
        {selectedMethod === 'bank_transfer' && (
          <div className="bg-gray-50 rounded-lg p-4 space-y-3">
            <h4 className="font-medium text-gray-900">Bank Transfer Details</h4>
            <div className="text-sm text-gray-600 space-y-2">
              <p><strong>Bank:</strong> Banca Transilvania</p>
              <p><strong>IBAN:</strong> RO49 BTRL 0000 0000 0000 0000</p>
              <p><strong>BIC/SWIFT:</strong> BTRLRO22</p>
              <p><strong>Beneficiary:</strong> Ambilet SRL</p>
              <p><strong>Reference:</strong> {paymentIntent?.paymentIntentId || 'ORDER-XXXXXX'}</p>
            </div>
            <p className="text-xs text-gray-500 mt-4">
              Please include the reference number in your transfer. Your promotions will be
              activated once we receive and verify your payment.
            </p>
          </div>
        )}

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4">
            <div className="flex items-center">
              <svg className="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                  clipRule="evenodd"
                />
              </svg>
              <span className="text-sm text-red-800">{error}</span>
            </div>
          </div>
        )}

        {/* Amount Summary */}
        <div className="bg-primary-50 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <span className="text-primary-900 font-medium">Amount to Pay</span>
            <span className="text-2xl font-bold text-primary-600">
              {totalAmount.toFixed(2)} {currency}
            </span>
          </div>
        </div>

        {/* Security Notice */}
        <div className="flex items-center text-xs text-gray-500">
          <svg className="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
              clipRule="evenodd"
            />
          </svg>
          Your payment information is encrypted and secure. We never store your card details.
        </div>

        {/* Actions */}
        <div className="flex gap-4 pt-4">
          <button
            type="button"
            onClick={onCancel}
            disabled={isProcessing}
            className="flex-1 px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isProcessing || isLoading}
            className="flex-1 px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
          >
            {isProcessing ? (
              <>
                <svg
                  className="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    className="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    strokeWidth="4"
                  />
                  <path
                    className="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  />
                </svg>
                Processing...
              </>
            ) : (
              `Pay ${totalAmount.toFixed(2)} ${currency}`
            )}
          </button>
        </div>
      </form>
    </div>
  );
};

export default PaymentForm;
