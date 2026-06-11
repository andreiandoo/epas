/**
 * PromotionOrderItem Model
 * Represents an individual promotion item within an order
 */

import {
  PromotionOrderItem as IPromotionOrderItem,
  OrderItemStatus,
} from '../types/promotion.types';
import { PromotionType } from './PromotionType';
import { PromotionOption } from './PromotionOption';

export class PromotionOrderItem implements IPromotionOrderItem {
  id: number;
  orderId: number;
  promotionTypeId: number;
  promotionOptionId: number;
  quantity: number;
  unitPrice: number;
  totalPrice: number;
  startDate: Date | null;
  endDate: Date | null;
  durationDays: number | null;
  status: OrderItemStatus;
  configuration: Record<string, any>;
  metadata: Record<string, any>;
  activatedAt: Date | null;
  completedAt: Date | null;
  createdAt: Date;
  updatedAt: Date;
  promotionType?: PromotionType;
  promotionOption?: PromotionOption;

  constructor(data: Partial<IPromotionOrderItem>) {
    this.id = data.id || 0;
    this.orderId = data.orderId || 0;
    this.promotionTypeId = data.promotionTypeId || 0;
    this.promotionOptionId = data.promotionOptionId || 0;
    this.quantity = data.quantity || 1;
    this.unitPrice = data.unitPrice || 0;
    this.totalPrice = data.totalPrice || 0;
    this.startDate = data.startDate ? new Date(data.startDate) : null;
    this.endDate = data.endDate ? new Date(data.endDate) : null;
    this.durationDays = data.durationDays ?? null;
    this.status = data.status || OrderItemStatus.PENDING;
    this.configuration = data.configuration || {};
    this.metadata = data.metadata || {};
    this.activatedAt = data.activatedAt ? new Date(data.activatedAt) : null;
    this.completedAt = data.completedAt ? new Date(data.completedAt) : null;
    this.createdAt = data.createdAt ? new Date(data.createdAt) : new Date();
    this.updatedAt = data.updatedAt ? new Date(data.updatedAt) : new Date();
    this.promotionType = data.promotionType as PromotionType | undefined;
    this.promotionOption = data.promotionOption as PromotionOption | undefined;
  }

  /**
   * Calculate the total price based on unit price and quantity/duration
   */
  calculateTotalPrice(): void {
    if (this.durationDays) {
      this.totalPrice = Math.round((this.unitPrice * this.durationDays) * 100) / 100;
    } else {
      this.totalPrice = Math.round((this.unitPrice * this.quantity) * 100) / 100;
    }
  }

  /**
   * Calculate end date based on start date and duration
   */
  calculateEndDate(): void {
    if (this.startDate && this.durationDays) {
      const endDate = new Date(this.startDate);
      endDate.setDate(endDate.getDate() + this.durationDays - 1);
      this.endDate = endDate;
    }
  }

  /**
   * Check if item is active
   */
  isActive(): boolean {
    return this.status === OrderItemStatus.ACTIVE;
  }

  /**
   * Check if item can be activated
   */
  canBeActivated(): boolean {
    return this.status === OrderItemStatus.PENDING;
  }

  /**
   * Check if item is within its active period
   */
  isWithinActivePeriod(): boolean {
    if (!this.startDate || !this.endDate) return true;

    const now = new Date();
    now.setHours(0, 0, 0, 0);

    const start = new Date(this.startDate);
    start.setHours(0, 0, 0, 0);

    const end = new Date(this.endDate);
    end.setHours(23, 59, 59, 999);

    return now >= start && now <= end;
  }

  /**
   * Get remaining days for active promotion
   */
  getRemainingDays(): number | null {
    if (!this.endDate || this.status !== OrderItemStatus.ACTIVE) {
      return null;
    }

    const now = new Date();
    const end = new Date(this.endDate);
    const diffTime = end.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    return Math.max(0, diffDays);
  }

  /**
   * Get configuration value
   */
  getConfigValue<T>(key: string, defaultValue: T): T {
    return (this.configuration[key] as T) ?? defaultValue;
  }

  /**
   * Set configuration value
   */
  setConfigValue(key: string, value: any): void {
    this.configuration[key] = value;
  }

  /**
   * Get status display name
   */
  getStatusDisplayName(): string {
    const displayNames: Record<OrderItemStatus, string> = {
      [OrderItemStatus.PENDING]: 'Pending',
      [OrderItemStatus.ACTIVE]: 'Active',
      [OrderItemStatus.COMPLETED]: 'Completed',
      [OrderItemStatus.CANCELLED]: 'Cancelled',
    };
    return displayNames[this.status];
  }

  /**
   * Get status color for UI
   */
  getStatusColor(): string {
    const colors: Record<OrderItemStatus, string> = {
      [OrderItemStatus.PENDING]: 'yellow',
      [OrderItemStatus.ACTIVE]: 'green',
      [OrderItemStatus.COMPLETED]: 'blue',
      [OrderItemStatus.CANCELLED]: 'red',
    };
    return colors[this.status];
  }

  /**
   * Get display name for the item
   */
  getDisplayName(): string {
    if (this.promotionOption && this.promotionType) {
      return `${this.promotionType.name} - ${this.promotionOption.name}`;
    }
    return `Promotion Item #${this.id}`;
  }

  /**
   * Convert database row to model
   */
  static fromDatabase(row: any): PromotionOrderItem {
    const item = new PromotionOrderItem({
      id: row.id,
      orderId: row.order_id,
      promotionTypeId: row.promotion_type_id,
      promotionOptionId: row.promotion_option_id,
      quantity: row.quantity,
      unitPrice: parseFloat(row.unit_price),
      totalPrice: parseFloat(row.total_price),
      startDate: row.start_date,
      endDate: row.end_date,
      durationDays: row.duration_days,
      status: row.status as OrderItemStatus,
      configuration: row.configuration || {},
      metadata: row.metadata || {},
      activatedAt: row.activated_at,
      completedAt: row.completed_at,
      createdAt: row.created_at,
      updatedAt: row.updated_at,
    });

    // Attach related models if present in row
    if (row.promotion_type_name) {
      item.promotionType = PromotionType.fromDatabase({
        id: row.promotion_type_id,
        name: row.promotion_type_name,
        slug: row.promotion_type_slug,
        category: row.promotion_type_category,
        icon: row.promotion_type_icon,
      });
    }

    if (row.promotion_option_name) {
      item.promotionOption = PromotionOption.fromDatabase({
        id: row.promotion_option_id,
        name: row.promotion_option_name,
        code: row.promotion_option_code,
        description: row.promotion_option_description,
      });
    }

    return item;
  }

  /**
   * Convert to JSON response
   */
  toJSON(): Record<string, any> {
    return {
      id: this.id,
      orderId: this.orderId,
      promotionTypeId: this.promotionTypeId,
      promotionOptionId: this.promotionOptionId,
      quantity: this.quantity,
      unitPrice: this.unitPrice,
      totalPrice: this.totalPrice,
      startDate: this.startDate?.toISOString().split('T')[0] || null,
      endDate: this.endDate?.toISOString().split('T')[0] || null,
      durationDays: this.durationDays,
      status: this.status,
      statusDisplayName: this.getStatusDisplayName(),
      statusColor: this.getStatusColor(),
      configuration: this.configuration,
      metadata: this.metadata,
      activatedAt: this.activatedAt?.toISOString() || null,
      completedAt: this.completedAt?.toISOString() || null,
      remainingDays: this.getRemainingDays(),
      displayName: this.getDisplayName(),
      promotionType: this.promotionType?.toJSON(),
      promotionOption: this.promotionOption?.toJSON(),
      createdAt: this.createdAt.toISOString(),
      updatedAt: this.updatedAt.toISOString(),
    };
  }
}

export default PromotionOrderItem;
