/**
 * PromotionOrder Model
 * Represents an organizer's promotion order
 */

import {
  PromotionOrder as IPromotionOrder,
  OrderStatus,
} from '../types/promotion.types';
import { PromotionOrderItem } from './PromotionOrderItem';

export class PromotionOrder implements IPromotionOrder {
  id: number;
  orderNumber: string;
  organizerId: number;
  eventId: number | null;
  status: OrderStatus;
  currency: string;
  subtotal: number;
  discountAmount: number;
  discountCode: string | null;
  taxRate: number;
  taxAmount: number;
  totalAmount: number;
  paymentMethod: string | null;
  paymentId: string | null;
  paymentProvider: string | null;
  paidAt: Date | null;
  expiresAt: Date | null;
  notes: string | null;
  metadata: Record<string, any>;
  createdAt: Date;
  updatedAt: Date;
  items?: PromotionOrderItem[];

  constructor(data: Partial<IPromotionOrder>) {
    this.id = data.id || 0;
    this.orderNumber = data.orderNumber || '';
    this.organizerId = data.organizerId || 0;
    this.eventId = data.eventId ?? null;
    this.status = data.status || OrderStatus.DRAFT;
    this.currency = data.currency || 'RON';
    this.subtotal = data.subtotal || 0;
    this.discountAmount = data.discountAmount || 0;
    this.discountCode = data.discountCode ?? null;
    this.taxRate = data.taxRate ?? 19.0;
    this.taxAmount = data.taxAmount || 0;
    this.totalAmount = data.totalAmount || 0;
    this.paymentMethod = data.paymentMethod ?? null;
    this.paymentId = data.paymentId ?? null;
    this.paymentProvider = data.paymentProvider ?? null;
    this.paidAt = data.paidAt ? new Date(data.paidAt) : null;
    this.expiresAt = data.expiresAt ? new Date(data.expiresAt) : null;
    this.notes = data.notes ?? null;
    this.metadata = data.metadata || {};
    this.createdAt = data.createdAt ? new Date(data.createdAt) : new Date();
    this.updatedAt = data.updatedAt ? new Date(data.updatedAt) : new Date();
    this.items = data.items;
  }

  /**
   * Generate a unique order number
   */
  static generateOrderNumber(): string {
    const timestamp = Date.now().toString(36).toUpperCase();
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `PRO-${timestamp}-${random}`;
  }

  /**
   * Check if order can be modified
   */
  canBeModified(): boolean {
    return this.status === OrderStatus.DRAFT;
  }

  /**
   * Check if order can be cancelled
   */
  canBeCancelled(): boolean {
    return [OrderStatus.DRAFT, OrderStatus.PENDING_PAYMENT].includes(this.status);
  }

  /**
   * Check if order has been paid
   */
  isPaid(): boolean {
    return [
      OrderStatus.PAID,
      OrderStatus.PROCESSING,
      OrderStatus.ACTIVE,
      OrderStatus.COMPLETED,
    ].includes(this.status);
  }

  /**
   * Check if order is active
   */
  isActive(): boolean {
    return this.status === OrderStatus.ACTIVE;
  }

  /**
   * Check if order has expired
   */
  isExpired(): boolean {
    if (!this.expiresAt) return false;
    return new Date() > this.expiresAt && this.status === OrderStatus.PENDING_PAYMENT;
  }

  /**
   * Calculate totals from items
   */
  calculateTotals(): void {
    if (!this.items || this.items.length === 0) {
      this.subtotal = 0;
      this.taxAmount = 0;
      this.totalAmount = 0;
      return;
    }

    this.subtotal = this.items.reduce((sum, item) => sum + item.totalPrice, 0);
    const taxableAmount = this.subtotal - this.discountAmount;
    this.taxAmount = Math.round((taxableAmount * this.taxRate / 100) * 100) / 100;
    this.totalAmount = Math.round((taxableAmount + this.taxAmount) * 100) / 100;
  }

  /**
   * Apply discount code
   */
  applyDiscount(discountCode: string, discountAmount: number): void {
    this.discountCode = discountCode;
    this.discountAmount = discountAmount;
    this.calculateTotals();
  }

  /**
   * Get status display name
   */
  getStatusDisplayName(): string {
    const displayNames: Record<OrderStatus, string> = {
      [OrderStatus.DRAFT]: 'Draft',
      [OrderStatus.PENDING_PAYMENT]: 'Pending Payment',
      [OrderStatus.PAID]: 'Paid',
      [OrderStatus.PROCESSING]: 'Processing',
      [OrderStatus.ACTIVE]: 'Active',
      [OrderStatus.COMPLETED]: 'Completed',
      [OrderStatus.CANCELLED]: 'Cancelled',
      [OrderStatus.REFUNDED]: 'Refunded',
    };
    return displayNames[this.status];
  }

  /**
   * Get status color for UI
   */
  getStatusColor(): string {
    const colors: Record<OrderStatus, string> = {
      [OrderStatus.DRAFT]: 'gray',
      [OrderStatus.PENDING_PAYMENT]: 'yellow',
      [OrderStatus.PAID]: 'blue',
      [OrderStatus.PROCESSING]: 'blue',
      [OrderStatus.ACTIVE]: 'green',
      [OrderStatus.COMPLETED]: 'green',
      [OrderStatus.CANCELLED]: 'red',
      [OrderStatus.REFUNDED]: 'orange',
    };
    return colors[this.status];
  }

  /**
   * Convert database row to model
   */
  static fromDatabase(row: any): PromotionOrder {
    return new PromotionOrder({
      id: row.id,
      orderNumber: row.order_number,
      organizerId: row.organizer_id,
      eventId: row.event_id,
      status: row.status as OrderStatus,
      currency: row.currency,
      subtotal: parseFloat(row.subtotal),
      discountAmount: parseFloat(row.discount_amount || 0),
      discountCode: row.discount_code,
      taxRate: parseFloat(row.tax_rate),
      taxAmount: parseFloat(row.tax_amount),
      totalAmount: parseFloat(row.total_amount),
      paymentMethod: row.payment_method,
      paymentId: row.payment_id,
      paymentProvider: row.payment_provider,
      paidAt: row.paid_at,
      expiresAt: row.expires_at,
      notes: row.notes,
      metadata: row.metadata || {},
      createdAt: row.created_at,
      updatedAt: row.updated_at,
    });
  }

  /**
   * Convert to JSON response
   */
  toJSON(): Record<string, any> {
    return {
      id: this.id,
      orderNumber: this.orderNumber,
      organizerId: this.organizerId,
      eventId: this.eventId,
      status: this.status,
      statusDisplayName: this.getStatusDisplayName(),
      statusColor: this.getStatusColor(),
      currency: this.currency,
      subtotal: this.subtotal,
      discountAmount: this.discountAmount,
      discountCode: this.discountCode,
      taxRate: this.taxRate,
      taxAmount: this.taxAmount,
      totalAmount: this.totalAmount,
      paymentMethod: this.paymentMethod,
      paymentId: this.paymentId,
      paymentProvider: this.paymentProvider,
      paidAt: this.paidAt?.toISOString() || null,
      expiresAt: this.expiresAt?.toISOString() || null,
      notes: this.notes,
      metadata: this.metadata,
      items: this.items?.map(item => item.toJSON()),
      canBeModified: this.canBeModified(),
      canBeCancelled: this.canBeCancelled(),
      isPaid: this.isPaid(),
      isExpired: this.isExpired(),
      createdAt: this.createdAt.toISOString(),
      updatedAt: this.updatedAt.toISOString(),
    };
  }
}

export default PromotionOrder;
