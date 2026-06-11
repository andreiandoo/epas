/**
 * PromotionService
 * Core service for managing promotion types and options
 */

import { PromotionType, PromotionOption } from '../models';
import {
  PromotionCategory,
  PromotionPricing,
} from '../types/promotion.types';

// Simulated database interface - replace with actual DB implementation
interface DatabaseConnection {
  query(sql: string, params?: any[]): Promise<any[]>;
}

export class PromotionService {
  private db: DatabaseConnection;

  constructor(db: DatabaseConnection) {
    this.db = db;
  }

  /**
   * Get all active promotion types with their options
   */
  async getAllPromotionTypes(): Promise<PromotionType[]> {
    const typesQuery = `
      SELECT * FROM promotion_types
      WHERE is_active = true
      ORDER BY sort_order ASC
    `;

    const optionsQuery = `
      SELECT po.*, pp.id as pricing_id, pp.tier_name, pp.min_quantity as pricing_min_qty,
             pp.max_quantity as pricing_max_qty, pp.unit_price, pp.currency,
             pp.effective_from, pp.effective_until
      FROM promotion_options po
      LEFT JOIN promotion_pricing pp ON pp.promotion_option_id = po.id AND pp.is_active = true
      WHERE po.is_active = true
      ORDER BY po.promotion_type_id, po.sort_order ASC
    `;

    const [typesRows, optionsRows] = await Promise.all([
      this.db.query(typesQuery),
      this.db.query(optionsQuery),
    ]);

    // Convert to models
    const types = typesRows.map(row => PromotionType.fromDatabase(row));

    // Group options by type and attach pricing
    const optionsByType = new Map<number, PromotionOption[]>();
    const optionPricing = new Map<number, PromotionPricing[]>();

    for (const row of optionsRows) {
      const typeId = row.promotion_type_id;

      if (!optionsByType.has(typeId)) {
        optionsByType.set(typeId, []);
      }

      // Check if option already added
      let option = optionsByType.get(typeId)!.find(o => o.id === row.id);

      if (!option) {
        option = PromotionOption.fromDatabase(row);
        option.pricing = [];
        optionsByType.get(typeId)!.push(option);
      }

      // Add pricing tier if present
      if (row.pricing_id) {
        option.pricing!.push({
          id: row.pricing_id,
          promotionOptionId: row.id,
          tierName: row.tier_name,
          minQuantity: row.pricing_min_qty || 1,
          maxQuantity: row.pricing_max_qty,
          unitPrice: parseFloat(row.unit_price),
          currency: row.currency || 'RON',
          effectiveFrom: new Date(row.effective_from),
          effectiveUntil: row.effective_until ? new Date(row.effective_until) : null,
          isActive: true,
          createdAt: new Date(),
        });
      }
    }

    // Attach options to types
    for (const type of types) {
      type.options = optionsByType.get(type.id) || [];
    }

    return types;
  }

  /**
   * Get promotion types by category
   */
  async getPromotionTypesByCategory(category: PromotionCategory): Promise<PromotionType[]> {
    const types = await this.getAllPromotionTypes();
    return types.filter(t => t.category === category);
  }

  /**
   * Get a single promotion type by ID
   */
  async getPromotionTypeById(id: number): Promise<PromotionType | null> {
    const query = `
      SELECT * FROM promotion_types
      WHERE id = $1 AND is_active = true
    `;

    const rows = await this.db.query(query, [id]);
    if (rows.length === 0) return null;

    const type = PromotionType.fromDatabase(rows[0]);

    // Get options for this type
    const optionsQuery = `
      SELECT po.*, pp.id as pricing_id, pp.tier_name, pp.min_quantity as pricing_min_qty,
             pp.max_quantity as pricing_max_qty, pp.unit_price, pp.currency,
             pp.effective_from, pp.effective_until
      FROM promotion_options po
      LEFT JOIN promotion_pricing pp ON pp.promotion_option_id = po.id AND pp.is_active = true
      WHERE po.promotion_type_id = $1 AND po.is_active = true
      ORDER BY po.sort_order ASC
    `;

    const optionsRows = await this.db.query(optionsQuery, [id]);

    const options: PromotionOption[] = [];
    const processedIds = new Set<number>();

    for (const row of optionsRows) {
      if (!processedIds.has(row.id)) {
        const option = PromotionOption.fromDatabase(row);
        option.pricing = [];
        options.push(option);
        processedIds.add(row.id);
      }

      const option = options.find(o => o.id === row.id)!;
      if (row.pricing_id) {
        option.pricing!.push({
          id: row.pricing_id,
          promotionOptionId: row.id,
          tierName: row.tier_name,
          minQuantity: row.pricing_min_qty || 1,
          maxQuantity: row.pricing_max_qty,
          unitPrice: parseFloat(row.unit_price),
          currency: row.currency || 'RON',
          effectiveFrom: new Date(row.effective_from),
          effectiveUntil: row.effective_until ? new Date(row.effective_until) : null,
          isActive: true,
          createdAt: new Date(),
        });
      }
    }

    type.options = options;
    return type;
  }

  /**
   * Get a single promotion type by slug
   */
  async getPromotionTypeBySlug(slug: string): Promise<PromotionType | null> {
    const query = `
      SELECT id FROM promotion_types
      WHERE slug = $1 AND is_active = true
    `;

    const rows = await this.db.query(query, [slug]);
    if (rows.length === 0) return null;

    return this.getPromotionTypeById(rows[0].id);
  }

  /**
   * Get a promotion option by ID
   */
  async getPromotionOptionById(id: number): Promise<PromotionOption | null> {
    const query = `
      SELECT po.*, pp.id as pricing_id, pp.tier_name, pp.min_quantity as pricing_min_qty,
             pp.max_quantity as pricing_max_qty, pp.unit_price, pp.currency,
             pp.effective_from, pp.effective_until
      FROM promotion_options po
      LEFT JOIN promotion_pricing pp ON pp.promotion_option_id = po.id AND pp.is_active = true
      WHERE po.id = $1 AND po.is_active = true
    `;

    const rows = await this.db.query(query, [id]);
    if (rows.length === 0) return null;

    const option = PromotionOption.fromDatabase(rows[0]);
    option.pricing = [];

    for (const row of rows) {
      if (row.pricing_id) {
        option.pricing.push({
          id: row.pricing_id,
          promotionOptionId: row.id,
          tierName: row.tier_name,
          minQuantity: row.pricing_min_qty || 1,
          maxQuantity: row.pricing_max_qty,
          unitPrice: parseFloat(row.unit_price),
          currency: row.currency || 'RON',
          effectiveFrom: new Date(row.effective_from),
          effectiveUntil: row.effective_until ? new Date(row.effective_until) : null,
          isActive: true,
          createdAt: new Date(),
        });
      }
    }

    return option;
  }

  /**
   * Get promotion option by code
   */
  async getPromotionOptionByCode(code: string): Promise<PromotionOption | null> {
    const query = `
      SELECT id FROM promotion_options
      WHERE code = $1 AND is_active = true
    `;

    const rows = await this.db.query(query, [code]);
    if (rows.length === 0) return null;

    return this.getPromotionOptionById(rows[0].id);
  }

  /**
   * Get all options for a promotion type
   */
  async getOptionsForType(typeId: number): Promise<PromotionOption[]> {
    const type = await this.getPromotionTypeById(typeId);
    return type?.options || [];
  }

  /**
   * Check if an option belongs to a promotion type
   */
  async validateOptionBelongsToType(optionId: number, typeId: number): Promise<boolean> {
    const query = `
      SELECT 1 FROM promotion_options
      WHERE id = $1 AND promotion_type_id = $2 AND is_active = true
    `;

    const rows = await this.db.query(query, [optionId, typeId]);
    return rows.length > 0;
  }

  /**
   * Get featured promotion types (for dashboard display)
   */
  async getFeaturedPromotionTypes(): Promise<PromotionType[]> {
    const types = await this.getAllPromotionTypes();
    // Return first 4 types as featured (can be customized)
    return types.slice(0, 4);
  }

  /**
   * Search promotion types by query
   */
  async searchPromotionTypes(query: string): Promise<PromotionType[]> {
    const searchQuery = `
      SELECT DISTINCT pt.* FROM promotion_types pt
      LEFT JOIN promotion_options po ON po.promotion_type_id = pt.id
      WHERE pt.is_active = true
        AND (
          pt.name ILIKE $1
          OR pt.description ILIKE $1
          OR po.name ILIKE $1
          OR po.description ILIKE $1
        )
      ORDER BY pt.sort_order ASC
    `;

    const searchTerm = `%${query}%`;
    const rows = await this.db.query(searchQuery, [searchTerm]);

    const types = rows.map(row => PromotionType.fromDatabase(row));

    // Load options for each type
    for (const type of types) {
      const fullType = await this.getPromotionTypeById(type.id);
      if (fullType) {
        type.options = fullType.options;
      }
    }

    return types;
  }
}

export default PromotionService;
