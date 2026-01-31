/**
 * PromotionTypeCard Component
 * Displays a single promotion type with options for selection
 */

import React from 'react';
import { PromotionType, PromotionCategory } from '../types';

interface PromotionTypeCardProps {
  promotionType: PromotionType;
  isSelected: boolean;
  onSelect: (typeId: number) => void;
  onConfigure: (typeId: number) => void;
}

// Icon mapping for promotion types
const getIcon = (icon: string | null, category: PromotionCategory): string => {
  const icons: Record<string, string> = {
    star: '⭐',
    mail: '📧',
    'chart-line': '📊',
    megaphone: '📢',
  };

  const categoryIcons: Record<PromotionCategory, string> = {
    [PromotionCategory.FEATURING]: '⭐',
    [PromotionCategory.EMAIL_MARKETING]: '📧',
    [PromotionCategory.AD_TRACKING]: '📊',
    [PromotionCategory.AD_CREATION]: '📢',
  };

  return icons[icon || ''] || categoryIcons[category] || '📌';
};

// Color mapping for categories
const getCategoryColor = (category: PromotionCategory): string => {
  const colors: Record<PromotionCategory, string> = {
    [PromotionCategory.FEATURING]: 'bg-yellow-100 border-yellow-400 text-yellow-800',
    [PromotionCategory.EMAIL_MARKETING]: 'bg-blue-100 border-blue-400 text-blue-800',
    [PromotionCategory.AD_TRACKING]: 'bg-green-100 border-green-400 text-green-800',
    [PromotionCategory.AD_CREATION]: 'bg-purple-100 border-purple-400 text-purple-800',
  };
  return colors[category];
};

export const PromotionTypeCard: React.FC<PromotionTypeCardProps> = ({
  promotionType,
  isSelected,
  onSelect,
  onConfigure,
}) => {
  const handleClick = () => {
    onSelect(promotionType.id);
  };

  const handleConfigure = (e: React.MouseEvent) => {
    e.stopPropagation();
    onConfigure(promotionType.id);
  };

  return (
    <div
      className={`
        relative rounded-lg border-2 p-6 cursor-pointer transition-all duration-200
        ${isSelected
          ? 'border-primary-500 bg-primary-50 shadow-lg'
          : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-md'
        }
      `}
      onClick={handleClick}
    >
      {/* Selection indicator */}
      <div className="absolute top-4 right-4">
        <div
          className={`
            w-6 h-6 rounded-full border-2 flex items-center justify-center
            ${isSelected
              ? 'border-primary-500 bg-primary-500'
              : 'border-gray-300'
            }
          `}
        >
          {isSelected && (
            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
              />
            </svg>
          )}
        </div>
      </div>

      {/* Icon and Title */}
      <div className="flex items-start gap-4 mb-4">
        <div className="text-4xl">
          {getIcon(promotionType.icon, promotionType.category)}
        </div>
        <div className="flex-1">
          <h3 className="text-lg font-semibold text-gray-900">
            {promotionType.name}
          </h3>
          <span
            className={`
              inline-block px-2 py-1 text-xs font-medium rounded-full mt-1
              ${getCategoryColor(promotionType.category)}
            `}
          >
            {promotionType.categoryDisplayName}
          </span>
        </div>
      </div>

      {/* Description */}
      <p className="text-gray-600 text-sm mb-4">
        {promotionType.description}
      </p>

      {/* Options preview */}
      {promotionType.options && promotionType.options.length > 0 && (
        <div className="mb-4">
          <p className="text-xs text-gray-500 mb-2">Available options:</p>
          <div className="flex flex-wrap gap-1">
            {promotionType.options.slice(0, 3).map((option) => (
              <span
                key={option.id}
                className="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded"
              >
                {option.name}
              </span>
            ))}
            {promotionType.options.length > 3 && (
              <span className="px-2 py-1 text-gray-400 text-xs">
                +{promotionType.options.length - 3} more
              </span>
            )}
          </div>
        </div>
      )}

      {/* Pricing hint */}
      <div className="flex items-center justify-between pt-4 border-t border-gray-100">
        <div className="text-sm">
          <span className="text-gray-500">Starting from </span>
          <span className="font-semibold text-gray-900">
            {promotionType.baseCost > 0
              ? `${promotionType.baseCost.toFixed(2)} RON`
              : 'Custom pricing'
            }
          </span>
        </div>

        {isSelected && (
          <button
            onClick={handleConfigure}
            className="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-md hover:bg-primary-600 transition-colors"
          >
            Configure
          </button>
        )}
      </div>
    </div>
  );
};

export default PromotionTypeCard;
