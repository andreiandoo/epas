/**
 * usePromotions Hook
 * Handles fetching and managing promotion types and pricing
 */

import { useState, useEffect, useCallback } from 'react';
import {
  PromotionType,
  CostBreakdown,
  CartItem,
  AudienceType,
  AudienceFilters,
  AudienceCount,
  PromotionCategory,
} from '../types';

// API base URL - configure based on environment
const API_BASE_URL = '/api/organizer/promotions';

interface UsePromotionsReturn {
  // Data
  promotionTypes: PromotionType[];
  isLoading: boolean;
  error: string | null;

  // Actions
  fetchPromotionTypes: () => Promise<void>;
  fetchPromotionTypesByCategory: (category: PromotionCategory) => Promise<PromotionType[]>;
  getPromotionTypeById: (id: number) => PromotionType | undefined;
  calculatePricing: (items: CartItem[], discountCode?: string) => Promise<CostBreakdown>;
  getAudienceCount: (audienceType: AudienceType, filters?: AudienceFilters) => Promise<AudienceCount>;
  searchPromotions: (query: string) => Promise<PromotionType[]>;
}

export function usePromotions(): UsePromotionsReturn {
  const [promotionTypes, setPromotionTypes] = useState<PromotionType[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Fetch all promotion types
  const fetchPromotionTypes = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`${API_BASE_URL}/types`);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Failed to fetch promotion types');
      }

      setPromotionTypes(data.data);
    } catch (err: any) {
      setError(err.message);
      console.error('Error fetching promotion types:', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Fetch promotion types by category
  const fetchPromotionTypesByCategory = useCallback(
    async (category: PromotionCategory): Promise<PromotionType[]> => {
      try {
        const response = await fetch(`${API_BASE_URL}/types?category=${category}`);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || 'Failed to fetch promotion types');
        }

        return data.data;
      } catch (err: any) {
        console.error('Error fetching promotion types by category:', err);
        throw err;
      }
    },
    []
  );

  // Get promotion type by ID from local state
  const getPromotionTypeById = useCallback(
    (id: number): PromotionType | undefined => {
      return promotionTypes.find((pt) => pt.id === id);
    },
    [promotionTypes]
  );

  // Calculate pricing for cart items
  const calculatePricing = useCallback(
    async (items: CartItem[], discountCode?: string): Promise<CostBreakdown> => {
      try {
        const response = await fetch(`${API_BASE_URL}/pricing/calculate`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ items, discountCode }),
        });

        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || 'Failed to calculate pricing');
        }

        return data.data;
      } catch (err: any) {
        console.error('Error calculating pricing:', err);
        throw err;
      }
    },
    []
  );

  // Get audience count for email marketing
  const getAudienceCount = useCallback(
    async (audienceType: AudienceType, filters?: AudienceFilters): Promise<AudienceCount> => {
      try {
        const params = new URLSearchParams({ type: audienceType });

        if (filters) {
          if (filters.eventIds?.length) {
            params.append('eventIds', filters.eventIds.join(','));
          }
          if (filters.cities?.length) {
            params.append('cities', filters.cities.join(','));
          }
          if (filters.countries?.length) {
            params.append('countries', filters.countries.join(','));
          }
          if (filters.ageRange) {
            params.append('ageMin', String(filters.ageRange.min));
            params.append('ageMax', String(filters.ageRange.max));
          }
          if (filters.gender?.length) {
            params.append('gender', filters.gender.join(','));
          }
          if (filters.interests?.length) {
            params.append('interests', filters.interests.join(','));
          }
          if (filters.eventCategories?.length) {
            params.append('eventCategories', filters.eventCategories.join(','));
          }
        }

        const response = await fetch(`${API_BASE_URL}/email/audience-count?${params.toString()}`);
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.error || 'Failed to get audience count');
        }

        return data.data;
      } catch (err: any) {
        console.error('Error getting audience count:', err);
        throw err;
      }
    },
    []
  );

  // Search promotions
  const searchPromotions = useCallback(async (query: string): Promise<PromotionType[]> => {
    try {
      const response = await fetch(`${API_BASE_URL}/search?q=${encodeURIComponent(query)}`);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Failed to search promotions');
      }

      return data.data;
    } catch (err: any) {
      console.error('Error searching promotions:', err);
      throw err;
    }
  }, []);

  // Fetch promotion types on mount
  useEffect(() => {
    fetchPromotionTypes();
  }, [fetchPromotionTypes]);

  return {
    promotionTypes,
    isLoading,
    error,
    fetchPromotionTypes,
    fetchPromotionTypesByCategory,
    getPromotionTypeById,
    calculatePricing,
    getAudienceCount,
    searchPromotions,
  };
}

export default usePromotions;
