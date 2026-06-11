/**
 * AdTrackingSetup Component
 * Configuration UI for Ad Campaign Tracking promotion type
 */

import React, { useState, useEffect } from 'react';
import {
  PromotionOption,
  AdTrackingFormState,
  AdPlatform,
  AdTrackingConnection,
} from '../types';

interface AdTrackingSetupProps {
  options: PromotionOption[];
  initialState?: AdTrackingFormState;
  connectedPlatforms: AdTrackingConnection[];
  onConfigChange: (config: AdTrackingFormState & { optionIds: number[] }) => void;
  onPriceUpdate: (price: number) => void;
  onConnectPlatform: (platform: AdPlatform) => void;
}

// Platform info
const platformInfo: Record<AdPlatform, { name: string; icon: string; color: string; description: string }> = {
  [AdPlatform.FACEBOOK]: {
    name: 'Facebook Ads',
    icon: '📘',
    color: 'bg-blue-500',
    description: 'Track Facebook and Instagram ad campaigns',
  },
  [AdPlatform.GOOGLE]: {
    name: 'Google Ads',
    icon: '🔍',
    color: 'bg-red-500',
    description: 'Track Google Search and Display campaigns',
  },
  [AdPlatform.TIKTOK]: {
    name: 'TikTok Ads',
    icon: '🎵',
    color: 'bg-black',
    description: 'Track TikTok advertising campaigns',
  },
};

export const AdTrackingSetup: React.FC<AdTrackingSetupProps> = ({
  options,
  initialState,
  connectedPlatforms,
  onConfigChange,
  onPriceUpdate,
  onConnectPlatform,
}) => {
  const [selectedPlatforms, setSelectedPlatforms] = useState<AdPlatform[]>(
    initialState?.selectedPlatforms || []
  );
  const [months, setMonths] = useState<number>(initialState?.months || 1);

  // Check if a platform is connected
  const isConnected = (platform: AdPlatform): boolean => {
    return connectedPlatforms.some((c) => c.platform === platform);
  };

  // Get option for a platform
  const getOptionForPlatform = (platform: AdPlatform): PromotionOption | undefined => {
    const platformToCode: Record<AdPlatform, string> = {
      [AdPlatform.FACEBOOK]: 'facebook_tracking',
      [AdPlatform.GOOGLE]: 'google_tracking',
      [AdPlatform.TIKTOK]: 'tiktok_tracking',
    };

    return options.find((o) => o.code === platformToCode[platform]);
  };

  // Get bundle option (all platforms)
  const getBundleOption = (): PromotionOption | undefined => {
    return options.find((o) => o.code === 'all_platforms_tracking');
  };

  // Calculate price
  useEffect(() => {
    let totalPrice = 0;
    const optionIds: number[] = [];

    // Check if all platforms selected - use bundle
    const bundleOption = getBundleOption();
    if (selectedPlatforms.length === 3 && bundleOption) {
      totalPrice = (bundleOption.unitCost || 0) * months;
      optionIds.push(bundleOption.id);
    } else {
      // Individual platform pricing
      selectedPlatforms.forEach((platform) => {
        const option = getOptionForPlatform(platform);
        if (option) {
          totalPrice += (option.unitCost || 0) * months;
          optionIds.push(option.id);
        }
      });
    }

    onPriceUpdate(Math.round(totalPrice * 100) / 100);

    onConfigChange({
      selectedPlatforms,
      months,
      optionIds,
    });
  }, [selectedPlatforms, months, options]);

  const togglePlatform = (platform: AdPlatform) => {
    setSelectedPlatforms((prev) =>
      prev.includes(platform)
        ? prev.filter((p) => p !== platform)
        : [...prev, platform]
    );
  };

  const selectAllPlatforms = () => {
    setSelectedPlatforms(Object.values(AdPlatform));
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          Ad Campaign Tracking Setup
        </h3>
        <p className="text-sm text-gray-600">
          Connect your ad accounts and track campaign performance across platforms.
        </p>
      </div>

      {/* Platform Selection */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <label className="block text-sm font-medium text-gray-700">
            Select Platforms to Track
          </label>
          <button
            type="button"
            onClick={selectAllPlatforms}
            className="text-sm text-primary-600 hover:text-primary-700"
          >
            Select All (Save 20%)
          </button>
        </div>

        <div className="grid gap-3">
          {Object.values(AdPlatform).map((platform) => {
            const info = platformInfo[platform];
            const option = getOptionForPlatform(platform);
            const connected = isConnected(platform);
            const isSelected = selectedPlatforms.includes(platform);

            return (
              <div
                key={platform}
                className={`
                  relative flex items-center p-4 rounded-lg border-2 cursor-pointer
                  transition-all duration-200
                  ${isSelected
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300'
                  }
                `}
                onClick={() => togglePlatform(platform)}
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
                <div className="text-2xl mr-4">{info.icon}</div>

                {/* Content */}
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <span className="font-medium text-gray-900">{info.name}</span>
                    {connected ? (
                      <span className="px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded-full">
                        Connected
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          onConnectPlatform(platform);
                        }}
                        className="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full hover:bg-gray-200"
                      >
                        Connect Account
                      </button>
                    )}
                  </div>
                  <div className="text-sm text-gray-500">{info.description}</div>
                </div>

                {/* Price */}
                {option && (
                  <div className="text-right">
                    <div className="font-semibold text-gray-900">
                      {option.unitCost?.toFixed(2)} RON
                    </div>
                    <div className="text-xs text-gray-500">per month</div>
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {/* Bundle savings indicator */}
        {selectedPlatforms.length === 3 && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center">
            <span className="text-green-600 mr-2">✨</span>
            <span className="text-sm text-green-800">
              You're saving 20% with the All Platforms Bundle!
            </span>
          </div>
        )}
      </div>

      {/* Duration Selection */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Subscription Duration
        </label>
        <select
          value={months}
          onChange={(e) => setMonths(parseInt(e.target.value, 10))}
          className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
        >
          <option value={1}>1 month</option>
          <option value={3}>3 months</option>
          <option value={6}>6 months</option>
          <option value={12}>12 months</option>
        </select>
      </div>

      {/* What's Included */}
      <div className="bg-gray-50 rounded-lg p-4">
        <h4 className="font-medium text-gray-900 mb-3">What's Included:</h4>
        <ul className="space-y-2 text-sm text-gray-600">
          <li className="flex items-center">
            <svg className="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
              />
            </svg>
            Real-time campaign performance tracking
          </li>
          <li className="flex items-center">
            <svg className="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
              />
            </svg>
            Impressions, clicks, and conversion tracking
          </li>
          <li className="flex items-center">
            <svg className="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
              />
            </svg>
            ROI and ROAS analytics
          </li>
          <li className="flex items-center">
            <svg className="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
              />
            </svg>
            Unified dashboard for all platforms
          </li>
          <li className="flex items-center">
            <svg className="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
              />
            </svg>
            Automatic data sync (every 6 hours)
          </li>
        </ul>
      </div>

      {/* Validation */}
      {selectedPlatforms.length === 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
          <p className="text-yellow-800 text-sm">
            Please select at least one platform to continue.
          </p>
        </div>
      )}
    </div>
  );
};

export default AdTrackingSetup;
