/**
 * PromotionOrderService
 * Handles promotion order management
 */

import { PromotionOrder, PromotionOrderItem } from '../models';
import { PromotionService } from './PromotionService';
import { PromotionPricingService } from './PromotionPricingService';
import {
  OrderStatus,
  OrderItemStatus,
  CreateOrderDTO,
  CreateOrderItemDTO,
  UpdateOrderDTO,
  CostBreakdown,
} from '../types/promotion.types';

// Simulated database interface
interface DatabaseConnection {
  query(sql: string, params?: any[]): Promise<any[]>;
  transaction<T>(callback: (client: DatabaseConnection) => Promise<T>): Promise<T>;
}

export class PromotionOrderService {
  private db: DatabaseConnection;
  private promotionService: PromotionService;
  private pricingService: PromotionPricingService;

  constructor(
    db: DatabaseConnection,
    promotionService: PromotionService,
    pricingService: PromotionPricingService
  ) {
    this.db = db;
    this.promotionService = promotionService;
    this.pricingService = pricingService;
  }

  /**
   * Create a new promotion order
   */
  async createOrder(organizerId: number, dto: CreateOrderDTO): Promise<PromotionOrder> {
    // Validate items
    await this.validateOrderItems(dto.items);

    // Calculate costs
    const costBreakdown = await this.pricingService.calculateOrderCost(dto.items);

    // Apply discount if provided
    let finalBreakdown = costBreakdown;
    if (dto.discountCode) {
      finalBreakdown = await this.pricingService.applyDiscountCode(costBreakdown, dto.discountCode);
    }

    // Generate order number
    const orderNumber = PromotionOrder.generateOrderNumber();

    // Set expiration (24 hours from now)
    const expiresAt = new Date();
    expiresAt.setHours(expiresAt.getHours() + 24);

    return this.db.transaction(async (client) => {
      // Insert order
      const orderQuery = `
        INSERT INTO promotion_orders (
          order_number, organizer_id, event_id, status, currency,
          subtotal, discount_amount, discount_code, tax_rate, tax_amount,
          total_amount, expires_at, notes, metadata
        ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)
        RETURNING *
      `;

      const orderParams = [
        orderNumber,
        organizerId,
        dto.eventId || null,
        OrderStatus.DRAFT,
        finalBreakdown.currency,
        finalBreakdown.subtotal,
        finalBreakdown.discountAmount,
        dto.discountCode || null,
        finalBreakdown.taxRate,
        finalBreakdown.taxAmount,
        finalBreakdown.total,
        expiresAt,
        dto.notes || null,
        JSON.stringify({}),
      ];

      const orderRows = await client.query(orderQuery, orderParams);
      const order = PromotionOrder.fromDatabase(orderRows[0]);

      // Insert order items
      const items: PromotionOrderItem[] = [];

      for (let i = 0; i < dto.items.length; i++) {
        const itemDto = dto.items[i];
        const itemCost = finalBreakdown.items[i];

        const itemQuery = `
          INSERT INTO promotion_order_items (
            order_id, promotion_type_id, promotion_option_id,
            quantity, unit_price, total_price,
            start_date, end_date, duration_days,
            status, configuration, metadata
          ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
          RETURNING *
        `;

        // Calculate dates
        let startDate = itemDto.startDate ? new Date(itemDto.startDate) : null;
        let endDate = itemDto.endDate ? new Date(itemDto.endDate) : null;
        const durationDays = itemDto.durationDays || null;

        if (startDate && durationDays && !endDate) {
          endDate = new Date(startDate);
          endDate.setDate(endDate.getDate() + durationDays - 1);
        }

        const itemParams = [
          order.id,
          itemDto.promotionTypeId,
          itemDto.promotionOptionId,
          itemCost.quantity,
          itemCost.unitPrice,
          itemCost.totalPrice,
          startDate,
          endDate,
          durationDays,
          OrderItemStatus.PENDING,
          JSON.stringify(itemDto.configuration || {}),
          JSON.stringify({}),
        ];

        const itemRows = await client.query(itemQuery, itemParams);
        items.push(PromotionOrderItem.fromDatabase(itemRows[0]));
      }

      order.items = items;
      return order;
    });
  }

  /**
   * Get order by ID
   */
  async getOrderById(orderId: number, organizerId?: number): Promise<PromotionOrder | null> {
    let query = `
      SELECT * FROM promotion_orders
      WHERE id = $1
    `;
    const params: any[] = [orderId];

    if (organizerId !== undefined) {
      query += ' AND organizer_id = $2';
      params.push(organizerId);
    }

    const orderRows = await this.db.query(query, params);
    if (orderRows.length === 0) return null;

    const order = PromotionOrder.fromDatabase(orderRows[0]);

    // Load items
    order.items = await this.getOrderItems(orderId);

    return order;
  }

  /**
   * Get order by order number
   */
  async getOrderByNumber(orderNumber: string, organizerId?: number): Promise<PromotionOrder | null> {
    let query = `
      SELECT id FROM promotion_orders
      WHERE order_number = $1
    `;
    const params: any[] = [orderNumber];

    if (organizerId !== undefined) {
      query += ' AND organizer_id = $2';
      params.push(organizerId);
    }

    const rows = await this.db.query(query, params);
    if (rows.length === 0) return null;

    return this.getOrderById(rows[0].id, organizerId);
  }

  /**
   * Get order items
   */
  async getOrderItems(orderId: number): Promise<PromotionOrderItem[]> {
    const query = `
      SELECT poi.*,
             pt.name as promotion_type_name,
             pt.slug as promotion_type_slug,
             pt.category as promotion_type_category,
             pt.icon as promotion_type_icon,
             po.name as promotion_option_name,
             po.code as promotion_option_code,
             po.description as promotion_option_description
      FROM promotion_order_items poi
      JOIN promotion_types pt ON pt.id = poi.promotion_type_id
      JOIN promotion_options po ON po.id = poi.promotion_option_id
      WHERE poi.order_id = $1
      ORDER BY poi.id ASC
    `;

    const rows = await this.db.query(query, [orderId]);
    return rows.map(row => PromotionOrderItem.fromDatabase(row));
  }

  /**
   * Get orders for an organizer
   */
  async getOrdersByOrganizer(
    organizerId: number,
    options?: {
      status?: OrderStatus | OrderStatus[];
      limit?: number;
      offset?: number;
    }
  ): Promise<{ orders: PromotionOrder[]; total: number }> {
    let query = `
      SELECT * FROM promotion_orders
      WHERE organizer_id = $1
    `;
    let countQuery = `
      SELECT COUNT(*) as total FROM promotion_orders
      WHERE organizer_id = $1
    `;

    const params: any[] = [organizerId];
    let paramIndex = 2;

    if (options?.status) {
      const statuses = Array.isArray(options.status) ? options.status : [options.status];
      const placeholders = statuses.map((_, i) => `$${paramIndex + i}`).join(', ');
      query += ` AND status IN (${placeholders})`;
      countQuery += ` AND status IN (${placeholders})`;
      params.push(...statuses);
      paramIndex += statuses.length;
    }

    query += ' ORDER BY created_at DESC';

    if (options?.limit) {
      query += ` LIMIT $${paramIndex}`;
      params.push(options.limit);
      paramIndex++;
    }

    if (options?.offset) {
      query += ` OFFSET $${paramIndex}`;
      params.push(options.offset);
    }

    const [orderRows, countRows] = await Promise.all([
      this.db.query(query, params.slice(0, paramIndex - (options?.offset ? 0 : 1))),
      this.db.query(countQuery, params.slice(0, options?.status ? (Array.isArray(options.status) ? options.status.length + 1 : 2) : 1)),
    ]);

    const orders = orderRows.map(row => PromotionOrder.fromDatabase(row));

    // Load items for each order
    for (const order of orders) {
      order.items = await this.getOrderItems(order.id);
    }

    return {
      orders,
      total: parseInt(countRows[0].total, 10),
    };
  }

  /**
   * Update order
   */
  async updateOrder(
    orderId: number,
    organizerId: number,
    dto: UpdateOrderDTO
  ): Promise<PromotionOrder> {
    const order = await this.getOrderById(orderId, organizerId);

    if (!order) {
      throw new Error('Order not found');
    }

    if (!order.canBeModified()) {
      throw new Error('Order cannot be modified');
    }

    return this.db.transaction(async (client) => {
      // Update items if provided
      if (dto.items) {
        // Delete existing items
        await client.query('DELETE FROM promotion_order_items WHERE order_id = $1', [orderId]);

        // Validate and calculate new items
        await this.validateOrderItems(dto.items);
        const costBreakdown = await this.pricingService.calculateOrderCost(dto.items);

        let finalBreakdown = costBreakdown;
        if (dto.discountCode) {
          finalBreakdown = await this.pricingService.applyDiscountCode(costBreakdown, dto.discountCode);
        }

        // Update order totals
        const updateOrderQuery = `
          UPDATE promotion_orders SET
            subtotal = $1,
            discount_amount = $2,
            discount_code = $3,
            tax_amount = $4,
            total_amount = $5,
            notes = COALESCE($6, notes),
            updated_at = CURRENT_TIMESTAMP
          WHERE id = $7
        `;

        await client.query(updateOrderQuery, [
          finalBreakdown.subtotal,
          finalBreakdown.discountAmount,
          dto.discountCode || order.discountCode,
          finalBreakdown.taxAmount,
          finalBreakdown.total,
          dto.notes,
          orderId,
        ]);

        // Insert new items
        for (let i = 0; i < dto.items.length; i++) {
          const itemDto = dto.items[i];
          const itemCost = finalBreakdown.items[i];

          const itemQuery = `
            INSERT INTO promotion_order_items (
              order_id, promotion_type_id, promotion_option_id,
              quantity, unit_price, total_price,
              start_date, end_date, duration_days,
              status, configuration, metadata
            ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
          `;

          let startDate = itemDto.startDate ? new Date(itemDto.startDate) : null;
          let endDate = itemDto.endDate ? new Date(itemDto.endDate) : null;
          const durationDays = itemDto.durationDays || null;

          if (startDate && durationDays && !endDate) {
            endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + durationDays - 1);
          }

          await client.query(itemQuery, [
            orderId,
            itemDto.promotionTypeId,
            itemDto.promotionOptionId,
            itemCost.quantity,
            itemCost.unitPrice,
            itemCost.totalPrice,
            startDate,
            endDate,
            durationDays,
            OrderItemStatus.PENDING,
            JSON.stringify(itemDto.configuration || {}),
            JSON.stringify({}),
          ]);
        }
      } else if (dto.notes !== undefined) {
        // Only update notes
        await client.query(
          'UPDATE promotion_orders SET notes = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
          [dto.notes, orderId]
        );
      }

      return this.getOrderById(orderId, organizerId) as Promise<PromotionOrder>;
    });
  }

  /**
   * Cancel order
   */
  async cancelOrder(orderId: number, organizerId: number): Promise<PromotionOrder> {
    const order = await this.getOrderById(orderId, organizerId);

    if (!order) {
      throw new Error('Order not found');
    }

    if (!order.canBeCancelled()) {
      throw new Error('Order cannot be cancelled');
    }

    await this.db.query(
      `UPDATE promotion_orders SET status = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2`,
      [OrderStatus.CANCELLED, orderId]
    );

    // Cancel all items
    await this.db.query(
      `UPDATE promotion_order_items SET status = $1, updated_at = CURRENT_TIMESTAMP WHERE order_id = $2`,
      [OrderItemStatus.CANCELLED, orderId]
    );

    return this.getOrderById(orderId, organizerId) as Promise<PromotionOrder>;
  }

  /**
   * Initiate checkout - move order to pending payment status
   */
  async initiateCheckout(orderId: number, organizerId: number): Promise<PromotionOrder> {
    const order = await this.getOrderById(orderId, organizerId);

    if (!order) {
      throw new Error('Order not found');
    }

    if (order.status !== OrderStatus.DRAFT) {
      throw new Error('Only draft orders can be checked out');
    }

    if (!order.items || order.items.length === 0) {
      throw new Error('Order must have at least one item');
    }

    // Set new expiration (2 hours from now for payment)
    const expiresAt = new Date();
    expiresAt.setHours(expiresAt.getHours() + 2);

    await this.db.query(
      `UPDATE promotion_orders SET status = $1, expires_at = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3`,
      [OrderStatus.PENDING_PAYMENT, expiresAt, orderId]
    );

    return this.getOrderById(orderId, organizerId) as Promise<PromotionOrder>;
  }

  /**
   * Mark order as paid
   */
  async markOrderAsPaid(
    orderId: number,
    paymentDetails: {
      paymentId: string;
      paymentProvider: string;
      paymentMethod: string;
    }
  ): Promise<PromotionOrder> {
    const order = await this.getOrderById(orderId);

    if (!order) {
      throw new Error('Order not found');
    }

    if (order.status !== OrderStatus.PENDING_PAYMENT) {
      throw new Error('Order is not pending payment');
    }

    const now = new Date();

    await this.db.transaction(async (client) => {
      // Update order status
      await client.query(
        `UPDATE promotion_orders SET
          status = $1,
          payment_id = $2,
          payment_provider = $3,
          payment_method = $4,
          paid_at = $5,
          updated_at = CURRENT_TIMESTAMP
        WHERE id = $6`,
        [
          OrderStatus.PAID,
          paymentDetails.paymentId,
          paymentDetails.paymentProvider,
          paymentDetails.paymentMethod,
          now,
          orderId,
        ]
      );

      // Update all items to active (or processing if they need review)
      await client.query(
        `UPDATE promotion_order_items SET status = $1, updated_at = CURRENT_TIMESTAMP WHERE order_id = $2`,
        [OrderItemStatus.ACTIVE, orderId]
      );
    });

    return this.getOrderById(orderId) as Promise<PromotionOrder>;
  }

  /**
   * Validate order items
   */
  private async validateOrderItems(items: CreateOrderItemDTO[]): Promise<void> {
    if (!items || items.length === 0) {
      throw new Error('Order must have at least one item');
    }

    for (const item of items) {
      // Check promotion type exists
      const promotionType = await this.promotionService.getPromotionTypeById(item.promotionTypeId);
      if (!promotionType) {
        throw new Error(`Invalid promotion type: ${item.promotionTypeId}`);
      }

      // Check option exists and belongs to type
      const isValid = await this.promotionService.validateOptionBelongsToType(
        item.promotionOptionId,
        item.promotionTypeId
      );
      if (!isValid) {
        throw new Error(`Invalid option ${item.promotionOptionId} for type ${item.promotionTypeId}`);
      }

      // Validate quantity if provided
      const option = await this.promotionService.getPromotionOptionById(item.promotionOptionId);
      if (option && item.quantity) {
        if (!option.isQuantityValid(item.quantity)) {
          throw new Error(
            `Quantity ${item.quantity} is not valid for option ${item.promotionOptionId}. ` +
            `Min: ${option.minQuantity}, Max: ${option.maxQuantity}`
          );
        }
      }

      // Validate duration if provided
      if (option && item.durationDays) {
        if (!option.isDurationValid(item.durationDays)) {
          throw new Error(
            `Duration ${item.durationDays} is not valid for option ${item.promotionOptionId}. ` +
            `Min: ${option.minDurationDays}, Max: ${option.maxDurationDays}`
          );
        }
      }
    }
  }

  /**
   * Get order statistics for an organizer
   */
  async getOrderStatistics(organizerId: number): Promise<{
    totalOrders: number;
    totalSpent: number;
    activePromotions: number;
    completedPromotions: number;
  }> {
    const query = `
      SELECT
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN status IN ('paid', 'processing', 'active', 'completed') THEN total_amount ELSE 0 END), 0) as total_spent,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_promotions,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_promotions
      FROM promotion_orders
      WHERE organizer_id = $1
    `;

    const rows = await this.db.query(query, [organizerId]);
    const row = rows[0];

    return {
      totalOrders: parseInt(row.total_orders, 10),
      totalSpent: parseFloat(row.total_spent),
      activePromotions: parseInt(row.active_promotions, 10),
      completedPromotions: parseInt(row.completed_promotions, 10),
    };
  }
}

export default PromotionOrderService;
