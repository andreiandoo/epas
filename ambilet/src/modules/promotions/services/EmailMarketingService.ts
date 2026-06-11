/**
 * EmailMarketingService
 * Handles email marketing campaign operations
 */

import {
  AudienceType,
  AudienceFilters,
  EmailCampaign,
  EmailCampaignRecipient,
  AudienceCountResponse,
  EmailMarketingAnalytics,
} from '../types/promotion.types';

// Simulated database interface
interface DatabaseConnection {
  query(sql: string, params?: any[]): Promise<any[]>;
  transaction<T>(callback: (client: DatabaseConnection) => Promise<T>): Promise<T>;
}

// Email provider interface
interface EmailProvider {
  sendBulkEmail(
    recipients: { email: string; firstName?: string; lastName?: string }[],
    subject: string,
    htmlContent: string,
    plainTextContent?: string
  ): Promise<{ sent: number; failed: number }>;
}

export class EmailMarketingService {
  private db: DatabaseConnection;
  private emailProvider?: EmailProvider;

  constructor(db: DatabaseConnection, emailProvider?: EmailProvider) {
    this.db = db;
    this.emailProvider = emailProvider;
  }

  /**
   * Get audience count for a given audience type and filters
   */
  async getAudienceCount(
    organizerId: number,
    audienceType: AudienceType,
    filters?: AudienceFilters
  ): Promise<AudienceCountResponse> {
    let count = 0;

    switch (audienceType) {
      case AudienceType.WHOLE_DATABASE:
        count = await this.getWholeDatabaseCount();
        break;

      case AudienceType.FILTERED_DATABASE:
        count = await this.getFilteredDatabaseCount(filters || {});
        break;

      case AudienceType.PAST_CLIENTS:
        count = await this.getPastClientsCount(organizerId, filters?.eventIds);
        break;

      default:
        throw new Error(`Invalid audience type: ${audienceType}`);
    }

    // Get unit price for this audience type
    const unitPrice = this.getUnitPriceForAudienceType(audienceType, count);
    const estimatedCost = Math.round((count * unitPrice) * 100) / 100;

    return {
      audienceType,
      count,
      estimatedCost,
      unitPrice,
      filters,
    };
  }

  /**
   * Get count of whole database (all subscribed users)
   */
  private async getWholeDatabaseCount(): Promise<number> {
    const query = `
      SELECT COUNT(*) as count
      FROM users
      WHERE email_subscribed = true
        AND status = 'active'
        AND email IS NOT NULL
    `;

    const rows = await this.db.query(query);
    return parseInt(rows[0].count, 10);
  }

  /**
   * Get count of filtered database
   */
  private async getFilteredDatabaseCount(filters: AudienceFilters): Promise<number> {
    let query = `
      SELECT COUNT(DISTINCT u.id) as count
      FROM users u
      WHERE u.email_subscribed = true
        AND u.status = 'active'
        AND u.email IS NOT NULL
    `;

    const params: any[] = [];
    let paramIndex = 1;

    // Apply filters
    if (filters.cities && filters.cities.length > 0) {
      const placeholders = filters.cities.map(() => `$${paramIndex++}`).join(', ');
      query += ` AND u.city IN (${placeholders})`;
      params.push(...filters.cities);
    }

    if (filters.countries && filters.countries.length > 0) {
      const placeholders = filters.countries.map(() => `$${paramIndex++}`).join(', ');
      query += ` AND u.country IN (${placeholders})`;
      params.push(...filters.countries);
    }

    if (filters.ageRange) {
      const currentYear = new Date().getFullYear();
      const minBirthYear = currentYear - filters.ageRange.max;
      const maxBirthYear = currentYear - filters.ageRange.min;
      query += ` AND EXTRACT(YEAR FROM u.birth_date) BETWEEN $${paramIndex++} AND $${paramIndex++}`;
      params.push(minBirthYear, maxBirthYear);
    }

    if (filters.gender && filters.gender.length > 0) {
      const placeholders = filters.gender.map(() => `$${paramIndex++}`).join(', ');
      query += ` AND u.gender IN (${placeholders})`;
      params.push(...filters.gender);
    }

    if (filters.interests && filters.interests.length > 0) {
      const placeholders = filters.interests.map(() => `$${paramIndex++}`).join(', ');
      query += ` AND u.interests && ARRAY[${placeholders}]::text[]`;
      params.push(...filters.interests);
    }

    if (filters.eventCategories && filters.eventCategories.length > 0) {
      const placeholders = filters.eventCategories.map(() => `$${paramIndex++}`).join(', ');
      query += `
        AND EXISTS (
          SELECT 1 FROM tickets t
          JOIN events e ON e.id = t.event_id
          WHERE t.user_id = u.id
            AND e.category IN (${placeholders})
        )
      `;
      params.push(...filters.eventCategories);
    }

    if (filters.purchasedInLastDays) {
      query += `
        AND EXISTS (
          SELECT 1 FROM tickets t
          WHERE t.user_id = u.id
            AND t.created_at >= NOW() - INTERVAL '${filters.purchasedInLastDays} days'
        )
      `;
    }

    const rows = await this.db.query(query, params);
    return parseInt(rows[0].count, 10);
  }

  /**
   * Get count of past clients for an organizer
   */
  private async getPastClientsCount(organizerId: number, eventIds?: number[]): Promise<number> {
    let query = `
      SELECT COUNT(DISTINCT u.id) as count
      FROM users u
      JOIN tickets t ON t.user_id = u.id
      JOIN events e ON e.id = t.event_id
      WHERE e.organizer_id = $1
        AND u.email_subscribed = true
        AND u.status = 'active'
        AND u.email IS NOT NULL
    `;

    const params: any[] = [organizerId];

    if (eventIds && eventIds.length > 0) {
      const placeholders = eventIds.map((_, i) => `$${i + 2}`).join(', ');
      query += ` AND e.id IN (${placeholders})`;
      params.push(...eventIds);
    }

    const rows = await this.db.query(query, params);
    return parseInt(rows[0].count, 10);
  }

  /**
   * Get unit price for audience type based on volume
   */
  private getUnitPriceForAudienceType(audienceType: AudienceType, count: number): number {
    // Price tiers per audience type
    const pricingTiers: Record<AudienceType, { maxCount: number; price: number }[]> = {
      [AudienceType.WHOLE_DATABASE]: [
        { maxCount: 5000, price: 0.08 },
        { maxCount: 25000, price: 0.06 },
        { maxCount: 100000, price: 0.04 },
        { maxCount: Infinity, price: 0.03 },
      ],
      [AudienceType.FILTERED_DATABASE]: [
        { maxCount: 1000, price: 0.10 },
        { maxCount: 10000, price: 0.08 },
        { maxCount: 50000, price: 0.06 },
        { maxCount: Infinity, price: 0.05 },
      ],
      [AudienceType.PAST_CLIENTS]: [
        { maxCount: 500, price: 0.05 },
        { maxCount: 2000, price: 0.04 },
        { maxCount: 10000, price: 0.03 },
        { maxCount: Infinity, price: 0.02 },
      ],
    };

    const tiers = pricingTiers[audienceType];
    const tier = tiers.find(t => count <= t.maxCount) || tiers[tiers.length - 1];
    return tier.price;
  }

  /**
   * Create an email campaign
   */
  async createCampaign(
    orderItemId: number,
    organizerId: number,
    eventId: number | null,
    config: {
      audienceType: AudienceType;
      audienceFilters?: AudienceFilters;
      subject: string;
      previewText?: string;
      htmlContent: string;
      plainTextContent?: string;
      scheduledAt?: Date;
    }
  ): Promise<EmailCampaign> {
    // Get recipient count
    const audienceCount = await this.getAudienceCount(
      organizerId,
      config.audienceType,
      config.audienceFilters
    );

    const query = `
      INSERT INTO email_campaigns (
        order_item_id, organizer_id, event_id, audience_type, audience_filters,
        subject, preview_text, html_content, plain_text_content,
        total_recipients, scheduled_at, status
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
      RETURNING *
    `;

    const params = [
      orderItemId,
      organizerId,
      eventId,
      config.audienceType,
      JSON.stringify(config.audienceFilters || {}),
      config.subject,
      config.previewText || null,
      config.htmlContent,
      config.plainTextContent || null,
      audienceCount.count,
      config.scheduledAt || null,
      config.scheduledAt ? 'scheduled' : 'draft',
    ];

    const rows = await this.db.query(query, params);
    return this.mapCampaignFromRow(rows[0]);
  }

  /**
   * Get campaign by ID
   */
  async getCampaignById(campaignId: number, organizerId?: number): Promise<EmailCampaign | null> {
    let query = 'SELECT * FROM email_campaigns WHERE id = $1';
    const params: any[] = [campaignId];

    if (organizerId !== undefined) {
      query += ' AND organizer_id = $2';
      params.push(organizerId);
    }

    const rows = await this.db.query(query, params);
    if (rows.length === 0) return null;

    return this.mapCampaignFromRow(rows[0]);
  }

  /**
   * Get campaigns for an organizer
   */
  async getCampaignsByOrganizer(
    organizerId: number,
    limit: number = 20,
    offset: number = 0
  ): Promise<{ campaigns: EmailCampaign[]; total: number }> {
    const query = `
      SELECT * FROM email_campaigns
      WHERE organizer_id = $1
      ORDER BY created_at DESC
      LIMIT $2 OFFSET $3
    `;

    const countQuery = `
      SELECT COUNT(*) as total FROM email_campaigns
      WHERE organizer_id = $1
    `;

    const [rows, countRows] = await Promise.all([
      this.db.query(query, [organizerId, limit, offset]),
      this.db.query(countQuery, [organizerId]),
    ]);

    return {
      campaigns: rows.map(row => this.mapCampaignFromRow(row)),
      total: parseInt(countRows[0].total, 10),
    };
  }

  /**
   * Load recipients for a campaign
   */
  async loadCampaignRecipients(campaignId: number): Promise<number> {
    const campaign = await this.getCampaignById(campaignId);
    if (!campaign) {
      throw new Error('Campaign not found');
    }

    // Get recipients based on audience type
    let recipientQuery: string;
    const params: any[] = [];

    switch (campaign.audienceType) {
      case AudienceType.WHOLE_DATABASE:
        recipientQuery = `
          SELECT id, email, first_name, last_name
          FROM users
          WHERE email_subscribed = true AND status = 'active' AND email IS NOT NULL
        `;
        break;

      case AudienceType.FILTERED_DATABASE:
        recipientQuery = this.buildFilteredRecipientsQuery(campaign.audienceFilters);
        break;

      case AudienceType.PAST_CLIENTS:
        recipientQuery = `
          SELECT DISTINCT u.id, u.email, u.first_name, u.last_name
          FROM users u
          JOIN tickets t ON t.user_id = u.id
          JOIN events e ON e.id = t.event_id
          WHERE e.organizer_id = $1
            AND u.email_subscribed = true
            AND u.status = 'active'
            AND u.email IS NOT NULL
        `;
        params.push(campaign.organizerId);

        if (campaign.audienceFilters?.eventIds?.length) {
          const placeholders = campaign.audienceFilters.eventIds.map((_, i) => `$${i + 2}`).join(', ');
          recipientQuery += ` AND e.id IN (${placeholders})`;
          params.push(...campaign.audienceFilters.eventIds);
        }
        break;

      default:
        throw new Error(`Invalid audience type: ${campaign.audienceType}`);
    }

    // Insert recipients
    const insertQuery = `
      INSERT INTO email_campaign_recipients (campaign_id, user_id, email, first_name, last_name, status)
      SELECT $1, id, email, first_name, last_name, 'pending'
      FROM (${recipientQuery}) as recipients
      ON CONFLICT DO NOTHING
    `;

    await this.db.query(insertQuery, [campaignId, ...params]);

    // Update total recipients count
    const countQuery = `
      SELECT COUNT(*) as count FROM email_campaign_recipients WHERE campaign_id = $1
    `;
    const countRows = await this.db.query(countQuery, [campaignId]);
    const count = parseInt(countRows[0].count, 10);

    await this.db.query(
      'UPDATE email_campaigns SET total_recipients = $1 WHERE id = $2',
      [count, campaignId]
    );

    return count;
  }

  /**
   * Build filtered recipients query
   */
  private buildFilteredRecipientsQuery(filters: AudienceFilters): string {
    // Simplified - in production, build dynamic query based on filters
    return `
      SELECT id, email, first_name, last_name
      FROM users
      WHERE email_subscribed = true AND status = 'active' AND email IS NOT NULL
    `;
  }

  /**
   * Send campaign
   */
  async sendCampaign(campaignId: number): Promise<void> {
    const campaign = await this.getCampaignById(campaignId);
    if (!campaign) {
      throw new Error('Campaign not found');
    }

    if (campaign.status !== 'draft' && campaign.status !== 'scheduled') {
      throw new Error('Campaign cannot be sent');
    }

    // Update status to sending
    await this.db.query(
      'UPDATE email_campaigns SET status = $1, started_at = CURRENT_TIMESTAMP WHERE id = $2',
      ['sending', campaignId]
    );

    // Get recipients
    const recipientsQuery = `
      SELECT id, email, first_name, last_name
      FROM email_campaign_recipients
      WHERE campaign_id = $1 AND status = 'pending'
    `;
    const recipients = await this.db.query(recipientsQuery, [campaignId]);

    if (this.emailProvider) {
      // Send in batches
      const batchSize = 100;
      let sentCount = 0;

      for (let i = 0; i < recipients.length; i += batchSize) {
        const batch = recipients.slice(i, i + batchSize);

        const result = await this.emailProvider.sendBulkEmail(
          batch.map(r => ({
            email: r.email,
            firstName: r.first_name,
            lastName: r.last_name,
          })),
          campaign.subject,
          campaign.htmlContent || '',
          campaign.plainTextContent || undefined
        );

        sentCount += result.sent;

        // Update recipient statuses
        const sentIds = batch.slice(0, result.sent).map(r => r.id);
        if (sentIds.length > 0) {
          await this.db.query(
            `UPDATE email_campaign_recipients SET status = 'sent', sent_at = CURRENT_TIMESTAMP WHERE id = ANY($1)`,
            [sentIds]
          );
        }
      }

      // Update campaign
      await this.db.query(
        `UPDATE email_campaigns SET
          status = 'completed',
          sent_count = $1,
          completed_at = CURRENT_TIMESTAMP
        WHERE id = $2`,
        [sentCount, campaignId]
      );
    }
  }

  /**
   * Get campaign analytics
   */
  async getCampaignAnalytics(campaignId: number): Promise<EmailMarketingAnalytics> {
    const campaign = await this.getCampaignById(campaignId);
    if (!campaign) {
      throw new Error('Campaign not found');
    }

    const openRate = campaign.sentCount > 0 ? (campaign.openedCount / campaign.sentCount) * 100 : 0;
    const clickRate = campaign.openedCount > 0 ? (campaign.clickedCount / campaign.openedCount) * 100 : 0;
    const bounceRate = campaign.sentCount > 0 ? (campaign.bouncedCount / campaign.sentCount) * 100 : 0;

    return {
      totalSent: campaign.sentCount,
      delivered: campaign.deliveredCount,
      opened: campaign.openedCount,
      clicked: campaign.clickedCount,
      bounced: campaign.bouncedCount,
      unsubscribed: campaign.unsubscribedCount,
      openRate: Math.round(openRate * 100) / 100,
      clickRate: Math.round(clickRate * 100) / 100,
      bounceRate: Math.round(bounceRate * 100) / 100,
    };
  }

  /**
   * Map database row to EmailCampaign
   */
  private mapCampaignFromRow(row: any): EmailCampaign {
    return {
      id: row.id,
      orderItemId: row.order_item_id,
      organizerId: row.organizer_id,
      eventId: row.event_id,
      audienceType: row.audience_type as AudienceType,
      audienceFilters: row.audience_filters || {},
      subject: row.subject,
      previewText: row.preview_text,
      templateId: row.template_id,
      htmlContent: row.html_content,
      plainTextContent: row.plain_text_content,
      totalRecipients: row.total_recipients,
      sentCount: row.sent_count,
      deliveredCount: row.delivered_count,
      openedCount: row.opened_count,
      clickedCount: row.clicked_count,
      bouncedCount: row.bounced_count,
      unsubscribedCount: row.unsubscribed_count,
      scheduledAt: row.scheduled_at ? new Date(row.scheduled_at) : null,
      startedAt: row.started_at ? new Date(row.started_at) : null,
      completedAt: row.completed_at ? new Date(row.completed_at) : null,
      status: row.status,
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }
}

export default EmailMarketingService;
