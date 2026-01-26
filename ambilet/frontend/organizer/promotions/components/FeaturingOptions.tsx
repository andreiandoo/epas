/**
 * FeaturingOptions Component
 * Configuration UI for Event Featuring promotion type
 */

import React, { useState, useEffect } from 'react';
import { PromotionOption, FeaturingFormState } from '../types';

interface FeaturingOptionsProps {
  options: PromotionOption[];
  initialState?: FeaturingFormState;
  onConfigChange: (config: FeaturingFormState) => void;
  onPriceUpdate: (price: number) => void;
}

// Placement descriptions
const placementDescriptions: Record<string, string> = {
  home_page: 'Maximum visibility on the main landing page carousel',
  category_page: 'Featured in your event category (Concerts, Sports, etc.)',
  genre_page: 'Featured in specific genre pages (Rock, Jazz, Comedy, etc.)',
  city_page: 'Featured on city/location specific pages',
  general: 'Rotational featuring across multiple pages',
};

// Placement icons
const placementIcons: Record<string, string> = {
  home_page: '🏠',
  category_page: '📁',
  genre_page: '🎵',
  city_page: '🏙️',
  general: '🔄',
};

export const FeaturingOptions: React.FC<FeaturingOptionsProps> = ({
  options,
  initialState,
  onConfigChange,
  onPriceUpdate,
}) => {
  const [selectedPlacements, setSelectedPlacements] = useState<string[]>(
    initialState?.selectedPlacements || []
  );
  const [durationDays, setDurationDays] = useState<number>(
    initialState?.durationDays || 7
  );
  const [startDate, setStartDate] = useState<string>(
    initialState?.startDate || new Date().toISOString().split('T')[0]
  );

  // Calculate total price when selections change
  useEffect(() => {
    let totalPrice = 0;

    selectedPlacements.forEach((code) => {
      const option = options.find((o) => o.code === code);
      if (option) {
        // Find applicable pricing tier
        const pricingTier = option.pricing?.find(
          (tier) =>
            durationDays >= tier.minQuantity &&
            (tier.maxQuantity === null || durationDays <= tier.maxQuantity)
        );

        const unitPrice = pricingTier?.unitPrice || option.unitCost || 0;
        totalPrice += unitPrice * durationDays * option.costModifier;
      }
    });

    onPriceUpdate(Math.round(totalPrice * 100) / 100);

    // Update parent config
    onConfigChange({
      selectedPlacements,
      durationDays,
      startDate,
    });
  }, [selectedPlacements, durationDays, startDate, options]);

  const togglePlacement = (code: string) => {
    setSelectedPlacements((prev) =>
      prev.includes(code)
        ? prev.filter((p) => p !== code)
        : [...prev, code]
    );
  };

  const getOptionPrice = (option: PromotionOption): string => {
    const pricingTier = option.pricing?.find(
      (tier) =>
        durationDays >= tier.minQuantity &&
        (tier.maxQuantity === null || durationDays <= tier.maxQuantity)
    );

    const unitPrice = pricingTier?.unitPrice || option.unitCost || 0;
    const total = unitPrice * durationDays * option.costModifier;

    return `${total.toFixed(2)} RON`;
  };

  // Calculate end date
  const endDate = new Date(startDate);
  endDate.setDate(endDate.getDate() + durationDays - 1);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          Event Featuring Configuration
        </h3>
        <p className="text-sm text-gray-600">
          Select where you want your event to be featured. You can choose multiple placements.
        </p>
      </div>

      {/* Placement Options */}
      <div className="space-y-3">
        <label className="block text-sm font-medium text-gray-700">
          Select Placements
        </label>

        <div className="grid gap-3">
          {options.map((option) => {
            const isSelected = selectedPlacements.includes(option.code);

            return (
              <div
                key={option.id}
                className={`
                  relative flex items-center p-4 rounded-lg border-2 cursor-pointer
                  transition-all duration-200
                  ${isSelected
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300'
                  }
                `}
                onClick={() => togglePlacement(option.code)}
              >
                {/* Checkbox */}
                <div
                  className={`
                    w-5 h-5 rounded border-2 mr-4 flex items-center justify-center
                    ${isSelected
                      ? 'border-primary-500 bg-primary-500'
                      : 'border-gray-300'
                    }
                  `}
                >
                  {isSelected && (
                    <svg className="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                      <path
                        fillRule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clipRule="evenodd"
                      />
                    </svg>
                  )}
                </div>

                {/* Icon */}
                <div className="text-2xl mr-4">
                  {placementIcons[option.code] || '📌'}
                </div>

                {/* Content */}
                <div className="flex-1">
                  <div className="font-medium text-gray-900">{option.name}</div>
                  <div className="text-sm text-gray-500">
                    {placementDescriptions[option.code] || option.description}
                  </div>
                </div>

                {/* Price */}
                <div className="text-right">
                  <div className="font-semibold text-gray-900">
                    {getOptionPrice(option)}
                  </div>
                  <div className="text-xs text-gray-500">
                    for {durationDays} days
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Duration Selection */}
      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Duration
          </label>
          <select
            value={durationDays}
            onChange={(e) => setDurationDays(parseInt(e.target.value, 10))}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value={1}>1 day</option>
            <option value={3}>3 days</option>
            <option value={7}>7 days (1 week)</option>
            <option value={14}>14 days (2 weeks)</option>
            <option value={21}>21 days (3 weeks)</option>
            <option value={30}>30 days (1 month)</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Start Date
          </label>
          <input
            type="date"
            value={startDate}
            min={new Date().toISOString().split('T')[0]}
            onChange={(e) => setStartDate(e.target.value)}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
      </div>

      {/* Date Summary */}
      <div className="bg-gray-50 rounded-lg p-4">
        <div className="flex items-center justify-between text-sm">
          <span className="text-gray-600">Featuring Period:</span>
          <span className="font-medium text-gray-900">
            {new Date(startDate).toLocaleDateString()} - {endDate.toLocaleDateString()}
          </span>
        </div>
      </div>

      {/* Selection Summary */}
      {selectedPlacements.length === 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
          <p className="text-yellow-800 text-sm">
            Please select at least one placement to continue.
          </p>
        </div>
      )}
    </div>
  );
};

export default FeaturingOptions;
