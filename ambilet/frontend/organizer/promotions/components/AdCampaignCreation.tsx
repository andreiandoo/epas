/**
 * AdCampaignCreation Component
 * Configuration UI for Ad Campaign Creation promotion type
 */

import React, { useState, useEffect } from 'react';
import { PromotionOption, AdCreationFormState, AdPlatform } from '../types';

interface AdCampaignCreationProps {
  options: PromotionOption[];
  initialState?: AdCreationFormState;
  onConfigChange: (config: AdCreationFormState & { optionIds: number[] }) => void;
  onPriceUpdate: (price: number) => void;
}

// Platform info
const platformInfo: Record<AdPlatform, { name: string; icon: string; description: string }> = {
  [AdPlatform.FACEBOOK]: {
    name: 'Facebook & Instagram',
    icon: '📘',
    description: 'Reach audiences on Facebook and Instagram',
  },
  [AdPlatform.GOOGLE]: {
    name: 'Google Ads',
    icon: '🔍',
    description: 'Search, Display, and YouTube campaigns',
  },
  [AdPlatform.TIKTOK]: {
    name: 'TikTok',
    icon: '🎵',
    description: 'Engage younger audiences with video ads',
  },
};

export const AdCampaignCreation: React.FC<AdCampaignCreationProps> = ({
  options,
  initialState,
  onConfigChange,
  onPriceUpdate,
}) => {
  const [selectedPlatforms, setSelectedPlatforms] = useState<AdPlatform[]>(
    initialState?.selectedPlatforms || []
  );
  const [campaignName, setCampaignName] = useState<string>(
    initialState?.campaignName || ''
  );
  const [budget, setBudget] = useState<number>(initialState?.budget || 500);
  const [durationDays, setDurationDays] = useState<number>(
    initialState?.durationDays || 14
  );
  const [startDate, setStartDate] = useState<string>(
    initialState?.startDate || new Date().toISOString().split('T')[0]
  );
  const [targetAudience, setTargetAudience] = useState({
    locations: initialState?.targetAudience?.locations || [],
    ageRange: initialState?.targetAudience?.ageRange || { min: 18, max: 65 },
    interests: initialState?.targetAudience?.interests || [],
  });
  const [adCopy, setAdCopy] = useState<string>(initialState?.adCopy || '');
  const [landingUrl, setLandingUrl] = useState<string>(
    initialState?.landingUrl || ''
  );

  // Get option for a platform
  const getOptionForPlatform = (platform: AdPlatform): PromotionOption | undefined => {
    const platformToCode: Record<AdPlatform, string> = {
      [AdPlatform.FACEBOOK]: 'facebook_campaign',
      [AdPlatform.GOOGLE]: 'google_campaign',
      [AdPlatform.TIKTOK]: 'tiktok_campaign',
    };

    return options.find((o) => o.code === platformToCode[platform]);
  };

  // Get multi-platform option
  const getMultiPlatformOption = (): PromotionOption | undefined => {
    return options.find((o) => o.code === 'multi_platform_campaign');
  };

  // Calculate price
  useEffect(() => {
    let totalPrice = 0;
    const optionIds: number[] = [];

    // Check if all platforms selected - use multi-platform option
    const multiOption = getMultiPlatformOption();
    if (selectedPlatforms.length === 3 && multiOption) {
      const setupFee = multiOption.unitCost || 0;
      const managementFee = budget * (multiOption.metadata?.management_fee_percent || 12) / 100;
      totalPrice = setupFee + managementFee;
      optionIds.push(multiOption.id);
    } else {
      // Individual platform pricing
      selectedPlatforms.forEach((platform) => {
        const option = getOptionForPlatform(platform);
        if (option) {
          const setupFee = option.unitCost || 0;
          const managementFee = budget * (option.metadata?.management_fee_percent || 15) / 100;
          totalPrice += setupFee + managementFee;
          optionIds.push(option.id);
        }
      });
    }

    onPriceUpdate(Math.round(totalPrice * 100) / 100);

    onConfigChange({
      selectedPlatforms,
      campaignName,
      budget,
      durationDays,
      startDate,
      targetAudience,
      adCopy,
      landingUrl,
      optionIds,
    });
  }, [selectedPlatforms, campaignName, budget, durationDays, startDate, targetAudience, adCopy, landingUrl, options]);

  const togglePlatform = (platform: AdPlatform) => {
    setSelectedPlatforms((prev) =>
      prev.includes(platform)
        ? prev.filter((p) => p !== platform)
        : [...prev, platform]
    );
  };

  // Calculate end date
  const endDate = new Date(startDate);
  endDate.setDate(endDate.getDate() + durationDays - 1);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          Ad Campaign Creation
        </h3>
        <p className="text-sm text-gray-600">
          Our team will create and manage high-performing ad campaigns for your event.
        </p>
      </div>

      {/* Platform Selection */}
      <div className="space-y-3">
        <label className="block text-sm font-medium text-gray-700">
          Select Advertising Platforms
        </label>

        <div className="grid gap-3">
          {Object.values(AdPlatform).map((platform) => {
            const info = platformInfo[platform];
            const option = getOptionForPlatform(platform);
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
                  <div className="font-medium text-gray-900">{info.name}</div>
                  <div className="text-sm text-gray-500">{info.description}</div>
                </div>

                {/* Price */}
                {option && (
                  <div className="text-right">
                    <div className="font-semibold text-gray-900">
                      {option.unitCost?.toFixed(2)} RON
                    </div>
                    <div className="text-xs text-gray-500">
                      setup + {option.metadata?.management_fee_percent || 15}% management
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>

        {selectedPlatforms.length === 3 && (
          <div className="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center">
            <span className="text-green-600 mr-2">✨</span>
            <span className="text-sm text-green-800">
              Multi-platform discount applied! Only 12% management fee.
            </span>
          </div>
        )}
      </div>

      {/* Campaign Details */}
      <div className="space-y-4">
        <h4 className="font-medium text-gray-900">Campaign Details</h4>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Campaign Name
          </label>
          <input
            type="text"
            value={campaignName}
            onChange={(e) => setCampaignName(e.target.value)}
            placeholder="e.g., Summer Concert Promo"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Ad Budget (RON)
            </label>
            <input
              type="number"
              min={100}
              step={50}
              value={budget}
              onChange={(e) => setBudget(parseInt(e.target.value, 10) || 0)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
            <p className="text-xs text-gray-500 mt-1">
              This is your ad spend budget (separate from our service fee)
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Duration
            </label>
            <select
              value={durationDays}
              onChange={(e) => setDurationDays(parseInt(e.target.value, 10))}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            >
              <option value={7}>7 days</option>
              <option value={14}>14 days</option>
              <option value={21}>21 days</option>
              <option value={30}>30 days</option>
            </select>
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
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

      {/* Target Audience */}
      <div className="space-y-4">
        <h4 className="font-medium text-gray-900">Target Audience</h4>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Target Locations (comma-separated)
          </label>
          <input
            type="text"
            value={targetAudience.locations.join(', ')}
            onChange={(e) =>
              setTargetAudience((prev) => ({
                ...prev,
                locations: e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
              }))
            }
            placeholder="e.g., Bucharest, Cluj-Napoca, Romania"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Min Age
            </label>
            <input
              type="number"
              min={13}
              max={65}
              value={targetAudience.ageRange.min}
              onChange={(e) =>
                setTargetAudience((prev) => ({
                  ...prev,
                  ageRange: { ...prev.ageRange, min: parseInt(e.target.value, 10) || 18 },
                }))
              }
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Max Age
            </label>
            <input
              type="number"
              min={13}
              max={65}
              value={targetAudience.ageRange.max}
              onChange={(e) =>
                setTargetAudience((prev) => ({
                  ...prev,
                  ageRange: { ...prev.ageRange, max: parseInt(e.target.value, 10) || 65 },
                }))
              }
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Interests (comma-separated)
          </label>
          <input
            type="text"
            value={targetAudience.interests.join(', ')}
            onChange={(e) =>
              setTargetAudience((prev) => ({
                ...prev,
                interests: e.target.value.split(',').map((s) => s.trim()).filter(Boolean),
              }))
            }
            placeholder="e.g., music, concerts, festivals, nightlife"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
      </div>

      {/* Ad Content */}
      <div className="space-y-4">
        <h4 className="font-medium text-gray-900">Ad Content</h4>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Ad Copy / Message
          </label>
          <textarea
            value={adCopy}
            onChange={(e) => setAdCopy(e.target.value)}
            rows={4}
            placeholder="Describe what message you want to convey in the ads..."
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Landing URL
          </label>
          <input
            type="url"
            value={landingUrl}
            onChange={(e) => setLandingUrl(e.target.value)}
            placeholder="https://ambilet.ro/your-event"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
        </div>
      </div>

      {/* Cost Summary */}
      <div className="bg-gray-50 rounded-lg p-4">
        <h4 className="font-medium text-gray-900 mb-3">Cost Summary</h4>
        <div className="space-y-2 text-sm">
          <div className="flex justify-between">
            <span className="text-gray-600">Service Fee (setup + management)</span>
            <span className="font-medium">Calculated above</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-600">Ad Budget (paid to platforms)</span>
            <span className="font-medium">{budget.toFixed(2)} RON</span>
          </div>
          <div className="flex justify-between">
            <span className="text-gray-600">Campaign Duration</span>
            <span className="font-medium">
              {new Date(startDate).toLocaleDateString()} - {endDate.toLocaleDateString()}
            </span>
          </div>
        </div>
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

export default AdCampaignCreation;
