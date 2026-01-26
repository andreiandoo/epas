/**
 * CreatePromotion Page
 * Wizard-style page for creating promotion orders
 */

import React, { useState, useEffect, useMemo } from 'react';
import { usePromotions, usePromotionOrder } from '../hooks';
import {
  PromotionTypeCard,
  FeaturingOptions,
  EmailMarketingConfig,
  AdTrackingSetup,
  AdCampaignCreation,
  PriceSummary,
  PaymentForm,
} from '../components';
import {
  PromotionType,
  PromotionCategory,
  CartItem,
  CostBreakdown,
  AdTrackingConnection,
} from '../types';

// Wizard steps
enum WizardStep {
  SELECT_EVENT = 0,
  SELECT_PROMOTIONS = 1,
  CONFIGURE = 2,
  REVIEW = 3,
  PAYMENT = 4,
  CONFIRMATION = 5,
}

interface CreatePromotionProps {
  eventId?: number;
  eventName?: string;
  onComplete: (orderId: number) => void;
  onCancel: () => void;
}

export const CreatePromotion: React.FC<CreatePromotionProps> = ({
  eventId,
  eventName,
  onComplete,
  onCancel,
}) => {
  // Hooks
  const {
    promotionTypes,
    isLoading: typesLoading,
    calculatePricing,
    getAudienceCount,
  } = usePromotions();

  const {
    currentOrder,
    paymentIntent,
    isLoading: orderLoading,
    error,
    createOrder,
    initiateCheckout,
    confirmPayment,
  } = usePromotionOrder();

  // State
  const [currentStep, setCurrentStep] = useState<WizardStep>(
    eventId ? WizardStep.SELECT_PROMOTIONS : WizardStep.SELECT_EVENT
  );
  const [selectedEventId, setSelectedEventId] = useState<number | undefined>(eventId);
  const [selectedTypeIds, setSelectedTypeIds] = useState<number[]>([]);
  const [activeConfigTypeId, setActiveConfigTypeId] = useState<number | null>(null);
  const [configurations, setConfigurations] = useState<Record<number, any>>({});
  const [cartItems, setCartItems] = useState<CartItem[]>([]);
  const [costBreakdown, setCostBreakdown] = useState<CostBreakdown | null>(null);
  const [discountCode, setDiscountCode] = useState<string | null>(null);
  const [connectedPlatforms] = useState<AdTrackingConnection[]>([]); // TODO: Fetch from API

  // Get selected promotion types
  const selectedTypes = useMemo(() => {
    return promotionTypes.filter((t) => selectedTypeIds.includes(t.id));
  }, [promotionTypes, selectedTypeIds]);

  // Calculate pricing when cart changes
  useEffect(() => {
    const updatePricing = async () => {
      if (cartItems.length === 0) {
        setCostBreakdown(null);
        return;
      }

      try {
        const breakdown = await calculatePricing(cartItems, discountCode || undefined);
        setCostBreakdown(breakdown);
      } catch (err) {
        console.error('Error calculating pricing:', err);
      }
    };

    updatePricing();
  }, [cartItems, discountCode, calculatePricing]);

  // Handle promotion type selection
  const togglePromotionType = (typeId: number) => {
    setSelectedTypeIds((prev) =>
      prev.includes(typeId)
        ? prev.filter((id) => id !== typeId)
        : [...prev, typeId]
    );
  };

  // Handle configuration change for a promotion type
  const handleConfigChange = (typeId: number, config: any) => {
    setConfigurations((prev) => ({
      ...prev,
      [typeId]: config,
    }));

    // Update cart items based on configuration
    updateCartItemsForType(typeId, config);
  };

  // Update cart items for a specific type
  const updateCartItemsForType = (typeId: number, config: any) => {
    const type = promotionTypes.find((t) => t.id === typeId);
    if (!type) return;

    // Remove existing items for this type
    let updatedItems = cartItems.filter((item) => item.promotionTypeId !== typeId);

    // Add new items based on configuration
    switch (type.category) {
      case PromotionCategory.FEATURING:
        // Add item for each selected placement
        if (config.selectedPlacements) {
          config.selectedPlacements.forEach((placementCode: string) => {
            const option = type.options?.find((o) => o.code === placementCode);
            if (option) {
              updatedItems.push({
                promotionTypeId: typeId,
                promotionOptionId: option.id,
                durationDays: config.durationDays,
                startDate: config.startDate,
              });
            }
          });
        }
        break;

      case PromotionCategory.EMAIL_MARKETING:
        if (config.optionId) {
          updatedItems.push({
            promotionTypeId: typeId,
            promotionOptionId: config.optionId,
            quantity: config.recipientCount,
            configuration: {
              audienceType: config.audienceType,
              filters: config.filters,
              subject: config.subject,
              previewText: config.previewText,
              scheduledAt: config.scheduledAt,
            },
          });
        }
        break;

      case PromotionCategory.AD_TRACKING:
        if (config.optionIds) {
          config.optionIds.forEach((optionId: number) => {
            updatedItems.push({
              promotionTypeId: typeId,
              promotionOptionId: optionId,
              quantity: config.months,
            });
          });
        }
        break;

      case PromotionCategory.AD_CREATION:
        if (config.optionIds) {
          config.optionIds.forEach((optionId: number) => {
            updatedItems.push({
              promotionTypeId: typeId,
              promotionOptionId: optionId,
              durationDays: config.durationDays,
              startDate: config.startDate,
              configuration: {
                platforms: config.selectedPlatforms,
                campaignName: config.campaignName,
                budget: config.budget,
                targetAudience: config.targetAudience,
                adCopy: config.adCopy,
                landingUrl: config.landingUrl,
              },
            });
          });
        }
        break;
    }

    setCartItems(updatedItems);
  };

  // Handle price update from config component
  const handlePriceUpdate = (typeId: number, price: number) => {
    // Price is calculated from cart items, so we don't need to track it separately
  };

  // Proceed to checkout
  const handleProceedToPayment = async () => {
    try {
      // Create order
      const order = await createOrder({
        eventId: selectedEventId,
        items: cartItems,
        discountCode: discountCode || undefined,
      });

      // Initiate checkout
      await initiateCheckout(order.id);

      setCurrentStep(WizardStep.PAYMENT);
    } catch (err) {
      console.error('Error creating order:', err);
    }
  };

  // Handle payment completion
  const handlePaymentComplete = async (paymentIntentId: string, paymentMethod: string) => {
    if (!currentOrder) return;

    try {
      await confirmPayment(currentOrder.id, paymentIntentId, paymentMethod);
      setCurrentStep(WizardStep.CONFIRMATION);
    } catch (err) {
      console.error('Error confirming payment:', err);
    }
  };

  // Apply discount code
  const handleApplyDiscount = async (code: string) => {
    setDiscountCode(code || null);
    // Pricing will recalculate via useEffect
  };

  // Render configuration component for a type
  const renderConfigComponent = (type: PromotionType) => {
    const config = configurations[type.id] || {};

    switch (type.category) {
      case PromotionCategory.FEATURING:
        return (
          <FeaturingOptions
            options={type.options || []}
            initialState={config}
            onConfigChange={(c) => handleConfigChange(type.id, c)}
            onPriceUpdate={(p) => handlePriceUpdate(type.id, p)}
          />
        );

      case PromotionCategory.EMAIL_MARKETING:
        return (
          <EmailMarketingConfig
            options={type.options || []}
            initialState={config}
            onConfigChange={(c) => handleConfigChange(type.id, c)}
            onPriceUpdate={(p) => handlePriceUpdate(type.id, p)}
            fetchAudienceCount={getAudienceCount}
          />
        );

      case PromotionCategory.AD_TRACKING:
        return (
          <AdTrackingSetup
            options={type.options || []}
            initialState={config}
            connectedPlatforms={connectedPlatforms}
            onConfigChange={(c) => handleConfigChange(type.id, c)}
            onPriceUpdate={(p) => handlePriceUpdate(type.id, p)}
            onConnectPlatform={(platform) => {
              // TODO: Implement OAuth flow
              console.log('Connect platform:', platform);
            }}
          />
        );

      case PromotionCategory.AD_CREATION:
        return (
          <AdCampaignCreation
            options={type.options || []}
            initialState={config}
            onConfigChange={(c) => handleConfigChange(type.id, c)}
            onPriceUpdate={(p) => handlePriceUpdate(type.id, p)}
          />
        );

      default:
        return <div>Unknown promotion type</div>;
    }
  };

  // Step titles
  const stepTitles = [
    'Select Event',
    'Choose Promotions',
    'Configure',
    'Review Order',
    'Payment',
    'Confirmation',
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <button
                onClick={onCancel}
                className="mr-4 text-gray-500 hover:text-gray-700"
              >
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
              <h1 className="text-xl font-semibold text-gray-900">
                Create Promotion
              </h1>
            </div>

            {/* Progress Steps */}
            <div className="hidden md:flex items-center space-x-4">
              {stepTitles.slice(eventId ? 1 : 0, -1).map((title, index) => {
                const stepIndex = eventId ? index + 1 : index;
                const isActive = currentStep === stepIndex;
                const isCompleted = currentStep > stepIndex;

                return (
                  <div key={title} className="flex items-center">
                    <div
                      className={`
                        w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium
                        ${isCompleted ? 'bg-primary-500 text-white' : ''}
                        ${isActive ? 'bg-primary-100 text-primary-600 border-2 border-primary-500' : ''}
                        ${!isActive && !isCompleted ? 'bg-gray-200 text-gray-500' : ''}
                      `}
                    >
                      {isCompleted ? '✓' : stepIndex + 1}
                    </div>
                    <span className={`ml-2 text-sm ${isActive ? 'text-primary-600 font-medium' : 'text-gray-500'}`}>
                      {title}
                    </span>
                    {index < stepTitles.length - 3 && (
                      <div className="w-8 h-px bg-gray-300 mx-4"></div>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main Content */}
          <div className="lg:col-span-2">
            {/* Step 1: Select Promotions */}
            {currentStep === WizardStep.SELECT_PROMOTIONS && (
              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-2">
                  Choose Promotion Types
                </h2>
                <p className="text-gray-600 mb-6">
                  Select one or more promotion types for your event. You can configure each one in the next step.
                </p>

                {typesLoading ? (
                  <div className="space-y-4">
                    {[1, 2, 3, 4].map((i) => (
                      <div key={i} className="bg-white rounded-lg border border-gray-200 p-6 animate-pulse">
                        <div className="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                        <div className="h-4 bg-gray-200 rounded w-full mb-2"></div>
                        <div className="h-4 bg-gray-200 rounded w-2/3"></div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="space-y-4">
                    {promotionTypes.map((type) => (
                      <PromotionTypeCard
                        key={type.id}
                        promotionType={type}
                        isSelected={selectedTypeIds.includes(type.id)}
                        onSelect={togglePromotionType}
                        onConfigure={(id) => {
                          if (!selectedTypeIds.includes(id)) {
                            togglePromotionType(id);
                          }
                          setActiveConfigTypeId(id);
                          setCurrentStep(WizardStep.CONFIGURE);
                        }}
                      />
                    ))}
                  </div>
                )}

                <div className="mt-8 flex justify-end">
                  <button
                    onClick={() => {
                      if (selectedTypeIds.length > 0) {
                        setActiveConfigTypeId(selectedTypeIds[0]);
                        setCurrentStep(WizardStep.CONFIGURE);
                      }
                    }}
                    disabled={selectedTypeIds.length === 0}
                    className="px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Continue to Configure
                  </button>
                </div>
              </div>
            )}

            {/* Step 2: Configure */}
            {currentStep === WizardStep.CONFIGURE && activeConfigTypeId && (
              <div>
                {/* Tabs for multiple types */}
                {selectedTypes.length > 1 && (
                  <div className="flex space-x-1 mb-6 border-b border-gray-200">
                    {selectedTypes.map((type) => (
                      <button
                        key={type.id}
                        onClick={() => setActiveConfigTypeId(type.id)}
                        className={`
                          px-4 py-2 text-sm font-medium rounded-t-lg
                          ${activeConfigTypeId === type.id
                            ? 'bg-white border-t border-l border-r border-gray-200 text-primary-600 -mb-px'
                            : 'text-gray-500 hover:text-gray-700'
                          }
                        `}
                      >
                        {type.name}
                      </button>
                    ))}
                  </div>
                )}

                {/* Configuration Form */}
                <div className="bg-white rounded-lg border border-gray-200 p-6">
                  {selectedTypes
                    .filter((t) => t.id === activeConfigTypeId)
                    .map((type) => (
                      <div key={type.id}>{renderConfigComponent(type)}</div>
                    ))}
                </div>

                <div className="mt-8 flex justify-between">
                  <button
                    onClick={() => setCurrentStep(WizardStep.SELECT_PROMOTIONS)}
                    className="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50"
                  >
                    Back
                  </button>
                  <button
                    onClick={() => setCurrentStep(WizardStep.REVIEW)}
                    disabled={cartItems.length === 0}
                    className="px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Review Order
                  </button>
                </div>
              </div>
            )}

            {/* Step 3: Review */}
            {currentStep === WizardStep.REVIEW && (
              <div>
                <h2 className="text-lg font-semibold text-gray-900 mb-6">
                  Review Your Order
                </h2>

                {/* Order Items */}
                <div className="bg-white rounded-lg border border-gray-200 divide-y divide-gray-200 mb-8">
                  {cartItems.map((item, index) => {
                    const type = promotionTypes.find((t) => t.id === item.promotionTypeId);
                    const option = type?.options?.find((o) => o.id === item.promotionOptionId);

                    return (
                      <div key={index} className="p-4">
                        <div className="flex justify-between items-start">
                          <div>
                            <div className="font-medium text-gray-900">
                              {type?.name} - {option?.name}
                            </div>
                            <div className="text-sm text-gray-500 mt-1">
                              {item.durationDays && `${item.durationDays} days`}
                              {item.quantity && item.quantity > 1 && ` × ${item.quantity}`}
                              {item.startDate && ` • Starting ${item.startDate}`}
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>

                <div className="flex justify-between">
                  <button
                    onClick={() => setCurrentStep(WizardStep.CONFIGURE)}
                    className="px-6 py-3 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50"
                  >
                    Back
                  </button>
                  <button
                    onClick={handleProceedToPayment}
                    disabled={orderLoading}
                    className="px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600 disabled:opacity-50"
                  >
                    {orderLoading ? 'Processing...' : 'Proceed to Payment'}
                  </button>
                </div>
              </div>
            )}

            {/* Step 4: Payment */}
            {currentStep === WizardStep.PAYMENT && paymentIntent && costBreakdown && (
              <PaymentForm
                paymentIntent={paymentIntent}
                totalAmount={costBreakdown.total}
                currency={costBreakdown.currency}
                onPaymentComplete={handlePaymentComplete}
                onCancel={() => setCurrentStep(WizardStep.REVIEW)}
                isLoading={orderLoading}
              />
            )}

            {/* Step 5: Confirmation */}
            {currentStep === WizardStep.CONFIRMATION && currentOrder && (
              <div className="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <div className="text-6xl mb-4">✅</div>
                <h2 className="text-2xl font-bold text-gray-900 mb-2">
                  Payment Successful!
                </h2>
                <p className="text-gray-600 mb-6">
                  Your order #{currentOrder.orderNumber} has been confirmed.
                  Your promotions are now being processed.
                </p>
                <button
                  onClick={() => onComplete(currentOrder.id)}
                  className="px-6 py-3 bg-primary-500 text-white font-medium rounded-lg hover:bg-primary-600"
                >
                  View Order Details
                </button>
              </div>
            )}

            {/* Error Display */}
            {error && (
              <div className="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                <p className="text-red-800">{error}</p>
              </div>
            )}
          </div>

          {/* Sidebar - Price Summary */}
          <div className="lg:col-span-1">
            <div className="sticky top-24">
              <PriceSummary
                costBreakdown={costBreakdown || {
                  items: [],
                  subtotal: 0,
                  discountAmount: 0,
                  taxRate: 19,
                  taxAmount: 0,
                  total: 0,
                  currency: 'RON',
                }}
                onApplyDiscount={handleApplyDiscount}
                discountCode={discountCode}
                isLoading={typesLoading}
              />

              {/* Event Info */}
              {selectedEventId && eventName && (
                <div className="mt-4 bg-white rounded-lg border border-gray-200 p-4">
                  <div className="text-sm text-gray-500">Promoting Event:</div>
                  <div className="font-medium text-gray-900">{eventName}</div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CreatePromotion;
