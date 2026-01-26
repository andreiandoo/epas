/**
 * PromotionOrderController
 * Handles promotion order management endpoints
 */

import { Request, Response, NextFunction } from 'express';
import {
  PromotionOrderService,
  EmailMarketingService,
  AdTrackingService,
  AdCampaignCreationService,
} from '../services';
import {
  CreateOrderDTO,
  UpdateOrderDTO,
  OrderStatus,
  EmailCampaignConfigDTO,
  AdCampaignRequestDTO,
  AdPlatform,
} from '../types/promotion.types';

export class PromotionOrderController {
  private orderService: PromotionOrderService;
  private emailMarketingService: EmailMarketingService;
  private adTrackingService: AdTrackingService;
  private adCampaignCreationService: AdCampaignCreationService;

  constructor(
    orderService: PromotionOrderService,
    emailMarketingService: EmailMarketingService,
    adTrackingService: AdTrackingService,
    adCampaignCreationService: AdCampaignCreationService
  ) {
    this.orderService = orderService;
    this.emailMarketingService = emailMarketingService;
    this.adTrackingService = adTrackingService;
    this.adCampaignCreationService = adCampaignCreationService;
  }

  /**
   * POST /api/organizer/promotions/orders
   * Create a new promotion order
   */
  createOrder = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const dto: CreateOrderDTO = req.body;

      if (!dto.items || !Array.isArray(dto.items) || dto.items.length === 0) {
        res.status(400).json({
          success: false,
          error: 'At least one promotion item is required',
        });
        return;
      }

      const order = await this.orderService.createOrder(organizerId, dto);

      res.status(201).json({
        success: true,
        data: order.toJSON(),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/orders
   * Get all orders for the organizer
   */
  getOrders = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const { status, limit, offset } = req.query;

      let statusFilter: OrderStatus | OrderStatus[] | undefined;
      if (status) {
        statusFilter = (status as string).split(',') as OrderStatus[];
        if (statusFilter.length === 1) {
          statusFilter = statusFilter[0];
        }
      }

      const result = await this.orderService.getOrdersByOrganizer(organizerId, {
        status: statusFilter,
        limit: limit ? parseInt(limit as string, 10) : 20,
        offset: offset ? parseInt(offset as string, 10) : 0,
      });

      res.json({
        success: true,
        data: result.orders.map(o => o.toJSON()),
        pagination: {
          total: result.total,
          limit: limit ? parseInt(limit as string, 10) : 20,
          offset: offset ? parseInt(offset as string, 10) : 0,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/orders/:orderId
   * Get a specific order
   */
  getOrderById = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      res.json({
        success: true,
        data: order.toJSON(),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * PATCH /api/organizer/promotions/orders/:orderId
   * Update an order (while in draft status)
   */
  updateOrder = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;
      const dto: UpdateOrderDTO = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const order = await this.orderService.updateOrder(
        parseInt(orderId, 10),
        organizerId,
        dto
      );

      res.json({
        success: true,
        data: order.toJSON(),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * DELETE /api/organizer/promotions/orders/:orderId
   * Cancel/delete an order
   */
  cancelOrder = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const order = await this.orderService.cancelOrder(parseInt(orderId, 10), organizerId);

      res.json({
        success: true,
        data: order.toJSON(),
        message: 'Order cancelled successfully',
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/orders/:orderId/items
   * Get items for an order
   */
  getOrderItems = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId } = req.params;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      // First verify the order belongs to this organizer
      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      const items = await this.orderService.getOrderItems(parseInt(orderId, 10));

      res.json({
        success: true,
        data: items.map(item => item.toJSON()),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/statistics
   * Get promotion statistics for the organizer
   */
  getStatistics = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const stats = await this.orderService.getOrderStatistics(organizerId);

      res.json({
        success: true,
        data: stats,
      });
    } catch (error) {
      next(error);
    }
  };

  // ========================================
  // EMAIL CAMPAIGN CONFIGURATION
  // ========================================

  /**
   * POST /api/organizer/promotions/orders/:orderId/items/:itemId/email-campaign
   * Configure email campaign for an order item
   */
  configureEmailCampaign = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId, itemId } = req.params;
      const config: EmailCampaignConfigDTO = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      // Verify order belongs to organizer
      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      // Create email campaign
      const campaign = await this.emailMarketingService.createCampaign(
        parseInt(itemId, 10),
        organizerId,
        order.eventId,
        {
          audienceType: config.audienceType,
          audienceFilters: config.audienceFilters,
          subject: config.subject,
          previewText: config.previewText,
          htmlContent: config.htmlContent || '',
          plainTextContent: config.plainTextContent,
          scheduledAt: config.scheduledAt ? new Date(config.scheduledAt) : undefined,
        }
      );

      res.status(201).json({
        success: true,
        data: campaign,
      });
    } catch (error) {
      next(error);
    }
  };

  // ========================================
  // AD TRACKING CONNECTION
  // ========================================

  /**
   * GET /api/organizer/promotions/tracking/connect/:platform
   * Get OAuth URL for connecting ad platform
   */
  getAdTrackingOAuthUrl = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const { platform } = req.params;
      const { redirectUri } = req.query;

      if (!Object.values(AdPlatform).includes(platform as AdPlatform)) {
        res.status(400).json({
          success: false,
          error: 'Invalid platform',
        });
        return;
      }

      if (!redirectUri) {
        res.status(400).json({
          success: false,
          error: 'redirectUri is required',
        });
        return;
      }

      // Generate state for CSRF protection
      const state = Math.random().toString(36).substring(2, 15);

      const oauthUrl = this.adTrackingService.getOAuthUrl(
        platform as AdPlatform,
        redirectUri as string,
        state
      );

      res.json({
        success: true,
        data: {
          oauthUrl,
          state,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/tracking/connect
   * Complete OAuth connection for ad platform
   */
  connectAdPlatform = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { platform, authCode } = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      if (!platform || !authCode) {
        res.status(400).json({
          success: false,
          error: 'platform and authCode are required',
        });
        return;
      }

      const connection = await this.adTrackingService.connectPlatform(
        organizerId,
        platform as AdPlatform,
        authCode
      );

      res.json({
        success: true,
        data: {
          id: connection.id,
          platform: connection.platform,
          accountId: connection.accountId,
          accountName: connection.accountName,
          connectedAt: connection.connectedAt,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/tracking/connections
   * Get connected ad platforms
   */
  getConnectedPlatforms = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const connections = await this.adTrackingService.getConnectedPlatforms(organizerId);

      res.json({
        success: true,
        data: connections.map(c => ({
          id: c.id,
          platform: c.platform,
          accountId: c.accountId,
          accountName: c.accountName,
          connectedAt: c.connectedAt,
          lastSyncedAt: c.lastSyncedAt,
        })),
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/tracking/campaigns
   * Get tracked campaigns
   */
  getTrackedCampaigns = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { platform, status, limit, offset } = req.query;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const result = await this.adTrackingService.getTrackedCampaigns(organizerId, {
        platform: platform as AdPlatform | undefined,
        status: status as string | undefined,
        limit: limit ? parseInt(limit as string, 10) : 20,
        offset: offset ? parseInt(offset as string, 10) : 0,
      });

      res.json({
        success: true,
        data: result.campaigns,
        pagination: {
          total: result.total,
          limit: limit ? parseInt(limit as string, 10) : 20,
          offset: offset ? parseInt(offset as string, 10) : 0,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/tracking/analytics
   * Get aggregated ad tracking analytics
   */
  getTrackingAnalytics = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { platform } = req.query;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const analytics = await this.adTrackingService.getAggregatedAnalytics(
        organizerId,
        platform as AdPlatform | undefined
      );

      res.json({
        success: true,
        data: analytics,
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * POST /api/organizer/promotions/tracking/sync
   * Sync campaigns from connected platforms
   */
  syncTrackedCampaigns = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { platform } = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const syncedCount = await this.adTrackingService.syncCampaigns(
        organizerId,
        platform as AdPlatform | undefined
      );

      res.json({
        success: true,
        data: {
          syncedCampaigns: syncedCount,
        },
        message: `Successfully synced ${syncedCount} campaigns`,
      });
    } catch (error) {
      next(error);
    }
  };

  // ========================================
  // AD CAMPAIGN CREATION
  // ========================================

  /**
   * POST /api/organizer/promotions/orders/:orderId/items/:itemId/ad-campaign-request
   * Create ad campaign request for an order item
   */
  createAdCampaignRequest = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { orderId, itemId } = req.params;
      const dto: AdCampaignRequestDTO = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      // Verify order belongs to organizer
      const order = await this.orderService.getOrderById(parseInt(orderId, 10), organizerId);

      if (!order) {
        res.status(404).json({
          success: false,
          error: 'Order not found',
        });
        return;
      }

      const request = await this.adCampaignCreationService.createCampaignRequest(
        parseInt(itemId, 10),
        organizerId,
        order.eventId,
        dto
      );

      res.status(201).json({
        success: true,
        data: request,
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/ad-campaigns
   * Get ad campaign requests for organizer
   */
  getAdCampaignRequests = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { status, limit, offset } = req.query;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const result = await this.adCampaignCreationService.getRequestsByOrganizer(organizerId, {
        status: status as string | undefined,
        limit: limit ? parseInt(limit as string, 10) : 20,
        offset: offset ? parseInt(offset as string, 10) : 0,
      });

      res.json({
        success: true,
        data: result.requests,
        pagination: {
          total: result.total,
          limit: limit ? parseInt(limit as string, 10) : 20,
          offset: offset ? parseInt(offset as string, 10) : 0,
        },
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * GET /api/organizer/promotions/ad-campaigns/:requestId
   * Get specific ad campaign request
   */
  getAdCampaignRequest = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { requestId } = req.params;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const request = await this.adCampaignCreationService.getRequestById(
        parseInt(requestId, 10),
        organizerId
      );

      if (!request) {
        res.status(404).json({
          success: false,
          error: 'Ad campaign request not found',
        });
        return;
      }

      res.json({
        success: true,
        data: request,
      });
    } catch (error) {
      next(error);
    }
  };

  /**
   * PATCH /api/organizer/promotions/ad-campaigns/:requestId
   * Update ad campaign request
   */
  updateAdCampaignRequest = async (req: Request, res: Response, next: NextFunction): Promise<void> => {
    try {
      const organizerId = (req as any).user?.organizerId;
      const { requestId } = req.params;
      const dto: Partial<AdCampaignRequestDTO> = req.body;

      if (!organizerId) {
        res.status(401).json({
          success: false,
          error: 'Organizer not authenticated',
        });
        return;
      }

      const request = await this.adCampaignCreationService.updateRequest(
        parseInt(requestId, 10),
        organizerId,
        dto
      );

      res.json({
        success: true,
        data: request,
      });
    } catch (error) {
      next(error);
    }
  };
}

export default PromotionOrderController;
