/**
 * EmailMarketingConfig Component
 * Configuration UI for Email Marketing promotion type
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
  PromotionOption,
  EmailMarketingFormState,
  AudienceType,
  AudienceFilters,
  AudienceCount,
} from '../types';

interface EmailMarketingConfigProps {
  options: PromotionOption[];
  initialState?: EmailMarketingFormState;
  onConfigChange: (config: EmailMarketingFormState & { optionId: number; recipientCount: number }) => void;
  onPriceUpdate: (price: number) => void;
  fetchAudienceCount: (
    audienceType: AudienceType,
    filters?: AudienceFilters
  ) => Promise<AudienceCount>;
}

// Audience type descriptions
const audienceDescriptions: Record<AudienceType, { title: string; description: string; icon: string }> = {
  [AudienceType.WHOLE_DATABASE]: {
    title: 'Whole Database',
    description: 'Send to all subscribed users on the platform. Maximum reach.',
    icon: '🌐',
  },
  [AudienceType.FILTERED_DATABASE]: {
    title: 'Filtered Database',
    description: 'Target users by location, interests, demographics, and more.',
    icon: '🎯',
  },
  [AudienceType.PAST_CLIENTS]: {
    title: 'Past Event Clients',
    description: 'Reach attendees from your previous events. Higher engagement rates.',
    icon: '👥',
  },
};

export const EmailMarketingConfig: React.FC<EmailMarketingConfigProps> = ({
  options,
  initialState,
  onConfigChange,
  onPriceUpdate,
  fetchAudienceCount,
}) => {
  const [audienceType, setAudienceType] = useState<AudienceType>(
    initialState?.audienceType || AudienceType.PAST_CLIENTS
  );
  const [filters, setFilters] = useState<AudienceFilters>(
    initialState?.filters || {}
  );
  const [subject, setSubject] = useState<string>(initialState?.subject || '');
  const [previewText, setPreviewText] = useState<string>(
    initialState?.previewText || ''
  );
  const [scheduledAt, setScheduledAt] = useState<string | null>(
    initialState?.scheduledAt || null
  );

  const [audienceCount, setAudienceCount] = useState<AudienceCount | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [showFilters, setShowFilters] = useState<boolean>(false);

  // Get the option for selected audience type
  const getSelectedOption = useCallback((): PromotionOption | undefined => {
    const audienceTypeToCode: Record<AudienceType, string> = {
      [AudienceType.WHOLE_DATABASE]: 'whole_database',
      [AudienceType.FILTERED_DATABASE]: 'filtered_database',
      [AudienceType.PAST_CLIENTS]: 'past_clients',
    };

    return options.find((o) => o.code === audienceTypeToCode[audienceType]);
  }, [options, audienceType]);

  // Fetch audience count when type or filters change
  useEffect(() => {
    const loadAudienceCount = async () => {
      setIsLoading(true);
      try {
        const count = await fetchAudienceCount(
          audienceType,
          audienceType === AudienceType.FILTERED_DATABASE ? filters : undefined
        );
        setAudienceCount(count);
        onPriceUpdate(count.estimatedCost);
      } catch (error) {
        console.error('Failed to fetch audience count:', error);
      } finally {
        setIsLoading(false);
      }
    };

    loadAudienceCount();
  }, [audienceType, filters, fetchAudienceCount]);

  // Update parent config
  useEffect(() => {
    const option = getSelectedOption();
    if (option && audienceCount) {
      onConfigChange({
        audienceType,
        filters,
        subject,
        previewText,
        scheduledAt,
        optionId: option.id,
        recipientCount: audienceCount.count,
      });
    }
  }, [audienceType, filters, subject, previewText, scheduledAt, audienceCount, getSelectedOption]);

  const handleFilterChange = (key: keyof AudienceFilters, value: any) => {
    setFilters((prev) => ({
      ...prev,
      [key]: value,
    }));
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          Email Marketing Configuration
        </h3>
        <p className="text-sm text-gray-600">
          Choose your target audience and configure your email campaign.
        </p>
      </div>

      {/* Audience Type Selection */}
      <div className="space-y-3">
        <label className="block text-sm font-medium text-gray-700">
          Select Target Audience
        </label>

        <div className="grid gap-3">
          {Object.values(AudienceType).map((type) => {
            const info = audienceDescriptions[type];
            const option = options.find(
              (o) => o.metadata?.audience_type === type
            );
            const isSelected = audienceType === type;

            return (
              <div
                key={type}
                className={`
                  relative flex items-center p-4 rounded-lg border-2 cursor-pointer
                  transition-all duration-200
                  ${isSelected
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300'
                  }
                `}
                onClick={() => setAudienceType(type)}
              >
                {/* Radio */}
                <div
                  className={`
                    w-5 h-5 rounded-full border-2 mr-4 flex items-center justify-center
                    ${isSelected
                      ? 'border-primary-500 bg-primary-500'
                      : 'border-gray-300'
                    }
                  `}
                >
                  {isSelected && (
                    <div className="w-2 h-2 rounded-full bg-white" />
                  )}
                </div>

                {/* Icon */}
                <div className="text-2xl mr-4">{info.icon}</div>

                {/* Content */}
                <div className="flex-1">
                  <div className="font-medium text-gray-900">{info.title}</div>
                  <div className="text-sm text-gray-500">{info.description}</div>
                </div>

                {/* Price per email */}
                {option && (
                  <div className="text-right">
                    <div className="font-semibold text-gray-900">
                      {option.unitCost?.toFixed(2)} RON
                    </div>
                    <div className="text-xs text-gray-500">per email</div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Audience Filters (for filtered database) */}
      {audienceType === AudienceType.FILTERED_DATABASE && (
        <div className="space-y-4">
          <button
            type="button"
            onClick={() => setShowFilters(!showFilters)}
            className="flex items-center text-sm font-medium text-primary-600 hover:text-primary-700"
          >
            <span>{showFilters ? 'Hide' : 'Show'} Advanced Filters</span>
            <svg
              className={`ml-2 w-4 h-4 transition-transform ${showFilters ? 'rotate-180' : ''}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          {showFilters && (
            <div className="bg-gray-50 rounded-lg p-4 space-y-4">
              {/* Cities */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Cities (comma-separated)
                </label>
                <input
                  type="text"
                  placeholder="e.g., Bucharest, Cluj-Napoca, Timisoara"
                  value={filters.cities?.join(', ') || ''}
                  onChange={(e) =>
                    handleFilterChange(
                      'cities',
                      e.target.value.split(',').map((s) => s.trim()).filter(Boolean)
                    )
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                />
              </div>

              {/* Age Range */}
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Min Age
                  </label>
                  <input
                    type="number"
                    min="18"
                    max="99"
                    value={filters.ageRange?.min || ''}
                    onChange={(e) =>
                      handleFilterChange('ageRange', {
                        ...filters.ageRange,
                        min: parseInt(e.target.value, 10) || 18,
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Max Age
                  </label>
                  <input
                    type="number"
                    min="18"
                    max="99"
                    value={filters.ageRange?.max || ''}
                    onChange={(e) =>
                      handleFilterChange('ageRange', {
                        ...filters.ageRange,
                        max: parseInt(e.target.value, 10) || 99,
                      })
                    }
                    className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                  />
                </div>
              </div>

              {/* Interests */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Interests (comma-separated)
                </label>
                <input
                  type="text"
                  placeholder="e.g., music, sports, theater"
                  value={filters.interests?.join(', ') || ''}
                  onChange={(e) =>
                    handleFilterChange(
                      'interests',
                      e.target.value.split(',').map((s) => s.trim()).filter(Boolean)
                    )
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                />
              </div>
            </div>
          )}
        </div>
      )}

      {/* Audience Count Summary */}
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center">
            <span className="text-2xl mr-3">📊</span>
            <div>
              <div className="font-medium text-blue-900">Estimated Reach</div>
              <div className="text-sm text-blue-700">
                {isLoading ? (
                  'Calculating...'
                ) : (
                  `${audienceCount?.count.toLocaleString() || 0} recipients`
                )}
              </div>
            </div>
          </div>
          <div className="text-right">
            <div className="text-2xl font-bold text-blue-900">
              {isLoading ? '...' : `${audienceCount?.estimatedCost.toFixed(2) || '0.00'} RON`}
            </div>
            <div className="text-xs text-blue-600">
              estimated cost
            </div>
          </div>
        </div>
      </div>

      {/* Email Details */}
      <div className="space-y-4">
        <h4 className="font-medium text-gray-900">Email Details</h4>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Subject Line *
          </label>
          <input
            type="text"
            value={subject}
            onChange={(e) => setSubject(e.target.value)}
            placeholder="Enter your email subject"
            maxLength={100}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
          <p className="text-xs text-gray-500 mt-1">
            {subject.length}/100 characters
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Preview Text
          </label>
          <input
            type="text"
            value={previewText}
            onChange={(e) => setPreviewText(e.target.value)}
            placeholder="Brief preview shown in inbox"
            maxLength={150}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
          <p className="text-xs text-gray-500 mt-1">
            {previewText.length}/150 characters
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Schedule Send (optional)
          </label>
          <input
            type="datetime-local"
            value={scheduledAt || ''}
            min={new Date().toISOString().slice(0, 16)}
            onChange={(e) => setScheduledAt(e.target.value || null)}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          />
          <p className="text-xs text-gray-500 mt-1">
            Leave empty to send after payment is confirmed
          </p>
        </div>
      </div>

      {/* Validation */}
      {!subject && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
          <p className="text-yellow-800 text-sm">
            Please enter an email subject to continue.
          </p>
        </div>
      )}
    </div>
  );
};

export default EmailMarketingConfig;
