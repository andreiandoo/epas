/**
 * PromotionController
 * Handles promotion catalog and pricing endpoints
 */

import { Request, Response, NextFunction } from 'express';
import { PromotionService, PromotionPricingService, EmailMarketingService } from '../services';
import { PromotionCategory, AudienceType, CreateOrderItemDTO } from '../types/promotion.types';

export class PromotionController {
  private promotionService: PromotionService;
  private pricingService: PromotionPricingService;
  private emailMarketingService: EmailMarketingService;

  constructor(
    promotionService: PromotionService,
    pricingService: PromotionPricingService,
    emailMarketingService: EmailMarketingService
  ) {
    this.promotionService = promotionService;
    this.pricingService = pricingService;
    this.emailMarketingService = emailMarketingService;
  }

  /**
   * GET /api/organizer/promotions/types
   * Get all promotion types with options and pricing
   */
  getAllPromotionTypes = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { category } = req.query;

      let types;

      if (category && Object.values(PromotionCategory).includes(category as PromotionCategory)) {
        types = await this.promotionService.getPromotionTypesByCategory(category as PromotionCategory);
      } else {
        types = await this.promotionService.getAllPromotionTypes();
      }

      res.json({
        success: true,
        data: types.map(t => t.toJSON()),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/types/:typeId
   * Get a single promotion type with options
   */
  getPromotionTypeById = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { typeId } = req.params;

      const type = await this.promotionService.getPromotionTypeById(parseInt(typeId, 10));

      if (!type) {
        res.status(404).json({
          success: false,
          error: 'Promotion type not found',
        });
        return;
      }

      res.json({
        success: true,
        data: type.toJSON(),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/types/:typeId/options
   * Get options for a specific promotion type
   */
  getPromotionTypeOptions = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { typeId } = req.params;

      const options = await this.promotionService.getOptionsForType(parseInt(typeId, 10));

      res.json({
        success: true,
        data: options.map(o => o.toJSON()),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/pricing/calculate
   * Calculate price for selected promotions
   */
  calculatePricing = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { items, discountCode } = req.body as {
        items: CreateOrderItemDTO[];
        discountCode?: string;
      };

      if (!items || !Array.isArray(items) || items.length === 0) {
        res.status(400).json({
          success: false,
          error: 'Items array is required',
        });
        return;
      }

      let costBreakdown = await this.pricingService.calculateOrderCost(items);

      if (discountCode) {
        costBreakdown = await this.pricingService.applyDiscountCode(costBreakdown, discountCode);
      }

      res.json({
        success: true,
        data: costBreakdown,
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/email/audience-count
   * Get recipient count for email marketing audience type
   */
  getAudienceCount = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const { type, eventIds, cities, countries, ageMin, ageMax, gender, interests, eventCategories } = req.query;

      if (!type || !Object.values(AudienceType).includes(type as AudienceType)) {
        res.status(400).json({
          success: false,
          error: 'Valid audience type is required',
        });
        return;
      }

      // Build filters from query params
      const filters: any = {};

      if (eventIds) {
        filters.eventIds = (eventIds as string).split(',').map(id => parseInt(id, 10));
      }

      if (cities) {
        filters.cities = (cities as string).split(',');
      }

      if (countries) {
        filters.countries = (countries as string).split(',');
      }

      if (ageMin && ageMax) {
        filters.ageRange = {
          min: parseInt(ageMin as string, 10),
          max: parseInt(ageMax as string, 10),
        };
      }

      if (gender) {
        filters.gender = (gender as string).split(',');
      }

      if (interests) {
        filters.interests = (interests as string).split(',');
      }

      if (eventCategories) {
        filters.eventCategories = (eventCategories as string).split(',');
      }

      const audienceCount = await this.emailMarketingService.getAudienceCount(
        organizerId,
        type as AudienceType,
        Object.keys(filters).length > 0 ? filters : undefined
      );

      res.json({
        success: true,
        data: audienceCount,
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/email/preview-cost
   * Preview email campaign cost based on audience selection
   */
  previewEmailCost = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const { optionId, audienceType, filters } = req.body;

      if (!optionId || !audienceType) {
        res.status(400).json({
          success: false,
          error: 'optionId and audienceType are required',
        });
        return;
      }

      // Get audience count first
      const audienceCount = await this.emailMarketingService.getAudienceCount(
        organizerId,
        audienceType,
        filters
      );

      // Calculate cost
      const costDetails = await this.pricingService.calculateEmailMarketingCost(
        optionId,
        audienceCount.count
      );

      res.json({
        success: true,
        data: {
          audienceType,
          recipientCount: audienceCount.count,
          ...costDetails,
          filters,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/featured
   * Get featured promotion types for dashboard
   */
  getFeaturedPromotions = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const types = await this.promotionService.getFeaturedPromotionTypes();

      res.json({
        success: true,
        data: types.map(t => t.toJSON()),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/search
   * Search promotion types
   */
  searchPromotions = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { q } = req.query;

      if (!q || typeof q !== 'string') {
        res.status(400).json({
          success: false,
          error: 'Search query is required',
        });
        return;
      }

      const types = await this.promotionService.searchPromotionTypes(q);

      res.json({
        success: true,
        data: types.map(t => t.toJSON()),
      });
    } catch (error) {
      next(error);
    }
  };
}

export default PromotionController;
