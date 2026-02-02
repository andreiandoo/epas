/**
 * AdCampaignCreationService
 * Handles ad campaign creation requests and management
 */

import {
  AdPlatform,
  AdCampaignRequest,
  AdCampaignRequestDTO,
  TargetAudience,
  CreativeAsset,
} from '../types/promotion.types';

// Simulated database interface
interface DatabaseConnection {
  query(sql: string, params?: any[]): Promise<any[]>;
  transaction<T>(callback: (client: DatabaseConnection) => Promise<T>): Promise<T>;
}

// Notification interface for alerting team
interface NotificationService {
  notifyTeam(message: string, data: any): Promise<void>;
  notifyOrganizer(organizerId: number, subject: string, message: string): Promise<void>;
}

export class AdCampaignCreationService {
  private db: DatabaseConnection;
  private notificationService?: NotificationService;

  constructor(db: DatabaseConnection, notificationService?: NotificationService) {
    this.db = db;
    this.notificationService = notificationService;
  }

  /**
   * Create a new ad campaign request
   */
  async createCampaignRequest(
    orderItemId: number,
    organizerId: number,
    eventId: number | null,
    dto: AdCampaignRequestDTO
  ): Promise<AdCampaignRequest> {
    // Validate platforms
    if (!dto.platforms || dto.platforms.length === 0) {
      throw new Error('At least one platform must be selected');
    }

    // Validate budget
    if (!dto.budget || dto.budget <= 0) {
      throw new Error('Budget must be greater than 0');
    }

    // Calculate dates
    let startDate = dto.startDate ? new Date(dto.startDate) : null;
    let endDate = dto.endDate ? new Date(dto.endDate) : null;

    if (startDate && dto.durationDays && !endDate) {
      endDate = new Date(startDate);
      endDate.setDate(endDate.getDate() + dto.durationDays - 1);
    }

    const query = `
      INSERT INTO ad_campaign_requests (
        order_item_id, organizer_id, event_id, platforms,
        campaign_name, campaign_objective, target_audience,
        budget, budget_type, duration_days, start_date, end_date,
        ad_copy, landing_url, notes, status
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16)
      RETURNING *
    `;

    const params = [
      orderItemId,
      organizerId,
      eventId,
      dto.platforms,
      dto.campaignName || null,
      dto.campaignObjective || null,
      JSON.stringify(dto.targetAudience || {}),
      dto.budget,
      dto.budgetType || 'total',
      dto.durationDays || null,
      startDate,
      endDate,
      dto.adCopy || null,
      dto.landingUrl || null,
      dto.notes || null,
      'pending_review',
    ];

    const rows = await this.db.query(query, params);
    const request = this.mapRequestFromRow(rows[0]);

    // Notify team about new campaign request
    if (this.notificationService) {
      await this.notificationService.notifyTeam(
        'New Ad Campaign Request',
        {
          requestId: request.id,
          organizerId,
          eventId,
          platforms: dto.platforms,
          budget: dto.budget,
        }
      );
    }

    return request;
  }

  /**
   * Get campaign request by ID
   */
  async getRequestById(requestId: number, organizerId?: number): Promise<AdCampaignRequest | null> {
    let query = 'SELECT * FROM ad_campaign_requests WHERE id = $1';
    const params: any[] = [requestId];

    if (organizerId !== undefined) {
      query += ' AND organizer_id = $2';
      params.push(organizerId);
    }

    const rows = await this.db.query(query, params);
    if (rows.length === 0) return null;

    return this.mapRequestFromRow(rows[0]);
  }

  /**
   * Get campaign requests for an organizer
   */
  async getRequestsByOrganizer(
    organizerId: number,
    options?: {
      status?: string;
      limit?: number;
      offset?: number;
    }
  ): Promise<{ requests: AdCampaignRequest[]; total: number }> {
    let query = 'SELECT * FROM ad_campaign_requests WHERE organizer_id = $1';
    let countQuery = 'SELECT COUNT(*) as total FROM ad_campaign_requests WHERE organizer_id = $1';

    const params: any[] = [organizerId];
    let paramIndex = 2;

    if (options?.status) {
      query += ` AND status = $${paramIndex}`;
      countQuery += ` AND status = $${paramIndex}`;
      params.push(options.status);
      paramIndex++;
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

    const [rows, countRows] = await Promise.all([
      this.db.query(query, params),
      this.db.query(countQuery, params.slice(0, options?.status ? 2 : 1)),
    ]);

    return {
      requests: rows.map(row => this.mapRequestFromRow(row)),
      total: parseInt(countRows[0].total, 10),
    };
  }

  /**
   * Update campaign request (organizer can update while pending)
   */
  async updateRequest(
    requestId: number,
    organizerId: number,
    dto: Partial<AdCampaignRequestDTO>
  ): Promise<AdCampaignRequest> {
    const request = await this.getRequestById(requestId, organizerId);

    if (!request) {
      throw new Error('Campaign request not found');
    }

    if (request.status !== 'pending_review' && request.status !== 'needs_info') {
      throw new Error('Campaign request cannot be modified');
    }

    const updates: string[] = [];
    const params: any[] = [];
    let paramIndex = 1;

    if (dto.campaignName !== undefined) {
      updates.push(`campaign_name = $${paramIndex++}`);
      params.push(dto.campaignName);
    }

    if (dto.campaignObjective !== undefined) {
      updates.push(`campaign_objective = $${paramIndex++}`);
      params.push(dto.campaignObjective);
    }

    if (dto.targetAudience !== undefined) {
      updates.push(`target_audience = $${paramIndex++}`);
      params.push(JSON.stringify(dto.targetAudience));
    }

    if (dto.budget !== undefined) {
      updates.push(`budget = $${paramIndex++}`);
      params.push(dto.budget);
    }

    if (dto.adCopy !== undefined) {
      updates.push(`ad_copy = $${paramIndex++}`);
      params.push(dto.adCopy);
    }

    if (dto.landingUrl !== undefined) {
      updates.push(`landing_url = $${paramIndex++}`);
      params.push(dto.landingUrl);
    }

    if (dto.notes !== undefined) {
      updates.push(`notes = $${paramIndex++}`);
      params.push(dto.notes);
    }

    if (updates.length === 0) {
      return request;
    }

    updates.push('updated_at = CURRENT_TIMESTAMP');

    const query = `
      UPDATE ad_campaign_requests
      SET ${updates.join(', ')}
      WHERE id = $${paramIndex} AND organizer_id = $${paramIndex + 1}
      RETURNING *
    `;

    params.push(requestId, organizerId);

    const rows = await this.db.query(query, params);
    return this.mapRequestFromRow(rows[0]);
  }

  /**
   * Upload creative assets for a campaign request
   */
  async uploadCreativeAsset(
    requestId: number,
    organizerId: number,
    asset: CreativeAsset
  ): Promise<AdCampaignRequest> {
    const request = await this.getRequestById(requestId, organizerId);

    if (!request) {
      throw new Error('Campaign request not found');
    }

    const currentAssets = request.creativeAssets || [];
    currentAssets.push(asset);

    await this.db.query(
      'UPDATE ad_campaign_requests SET creative_assets = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
      [JSON.stringify(currentAssets), requestId]
    );

    return this.getRequestById(requestId, organizerId) as Promise<AdCampaignRequest>;
  }

  /**
   * Remove creative asset from a campaign request
   */
  async removeCreativeAsset(
    requestId: number,
    organizerId: number,
    assetUrl: string
  ): Promise<AdCampaignRequest> {
    const request = await this.getRequestById(requestId, organizerId);

    if (!request) {
      throw new Error('Campaign request not found');
    }

    const updatedAssets = request.creativeAssets.filter(a => a.url !== assetUrl);

    await this.db.query(
      'UPDATE ad_campaign_requests SET creative_assets = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
      [JSON.stringify(updatedAssets), requestId]
    );

    return this.getRequestById(requestId, organizerId) as Promise<AdCampaignRequest>;
  }

  // ========================================
  // ADMIN/TEAM OPERATIONS
  // ========================================

  /**
   * Get all pending campaign requests (for admin)
   */
  async getPendingRequests(
    limit: number = 20,
    offset: number = 0
  ): Promise<{ requests: AdCampaignRequest[]; total: number }> {
    const query = `
      SELECT * FROM ad_campaign_requests
      WHERE status IN ('pending_review', 'needs_info')
      ORDER BY created_at ASC
      LIMIT $1 OFFSET $2
    `;

    const countQuery = `
      SELECT COUNT(*) as total FROM ad_campaign_requests
      WHERE status IN ('pending_review', 'needs_info')
    `;

    const [rows, countRows] = await Promise.all([
      this.db.query(query, [limit, offset]),
      this.db.query(countQuery),
    ]);

    return {
      requests: rows.map(row => this.mapRequestFromRow(row)),
      total: parseInt(countRows[0].total, 10),
    };
  }

  /**
   * Assign campaign request to team member
   */
  async assignRequest(requestId: number, assignedTo: number): Promise<AdCampaignRequest> {
    await this.db.query(
      'UPDATE ad_campaign_requests SET assigned_to = $1, status = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3',
      [assignedTo, 'in_progress', requestId]
    );

    return this.getRequestById(requestId) as Promise<AdCampaignRequest>;
  }

  /**
   * Approve campaign request
   */
  async approveRequest(
    requestId: number,
    reviewedBy: number
  ): Promise<AdCampaignRequest> {
    const request = await this.getRequestById(requestId);

    if (!request) {
      throw new Error('Campaign request not found');
    }

    await this.db.query(
      `UPDATE ad_campaign_requests SET
        status = 'approved',
        reviewed_at = CURRENT_TIMESTAMP,
        reviewed_by = $1,
        updated_at = CURRENT_TIMESTAMP
      WHERE id = $2`,
      [reviewedBy, requestId]
    );

    // Notify organizer
    if (this.notificationService) {
      await this.notificationService.notifyOrganizer(
        request.organizerId,
        'Ad Campaign Request Approved',
        `Your ad campaign request for ${request.platforms.join(', ')} has been approved. We will begin creating your campaigns shortly.`
      );
    }

    return this.getRequestById(requestId) as Promise<AdCampaignRequest>;
  }

  /**
   * Reject campaign request
   */
  async rejectRequest(
    requestId: number,
    reviewedBy: number,
    rejectionReason: string
  ): Promise<AdCampaignRequest> {
    const request = await this.getRequestById(requestId);

    if (!request) {
      throw new Error('Campaign request not found');
    }

    await this.db.query(
      `UPDATE ad_campaign_requests SET
        status = 'rejected',
        reviewed_at = CURRENT_TIMESTAMP,
        reviewed_by = $1,
        rejection_reason = $2,
        updated_at = CURRENT_TIMESTAMP
      WHERE id = $3`,
      [reviewedBy, rejectionReason, requestId]
    );

    // Notify organizer
    if (this.notificationService) {
      await this.notificationService.notifyOrganizer(
        request.organizerId,
        'Ad Campaign Request Update',
        `Your ad campaign request requires changes: ${rejectionReason}. Please update your request and resubmit.`
      );
    }

    return this.getRequestById(requestId) as Promise<AdCampaignRequest>;
  }

  /**
   * Request more information from organizer
   */
  async requestMoreInfo(
    requestId: number,
    reviewedBy: number,
    message: string
  ): Promise<AdCampaignRequest> {
    const request = await this.getRequestById(requestId);

    if (!request) {
      throw new Error('Campaign request not found');
    }

    await this.db.query(
      `UPDATE ad_campaign_requests SET
        status = 'needs_info',
        reviewed_by = $1,
        rejection_reason = $2,
        updated_at = CURRENT_TIMESTAMP
      WHERE id = $3`,
      [reviewedBy, message, requestId]
    );

    // Notify organizer
    if (this.notificationService) {
      await this.notificationService.notifyOrganizer(
        request.organizerId,
        'More Information Needed for Ad Campaign',
        `We need more information for your ad campaign request: ${message}`
      );
    }

    return this.getRequestById(requestId) as Promise<AdCampaignRequest>;
  }

  /**
   * Mark campaign as live with external campaign IDs
   */
  async markCampaignLive(
    requestId: number,
    externalCampaignIds: Record<string, string>
  ): Promise<AdCampaignRequest> {
    await this.db.query(
      `UPDATE ad_campaign_requests SET
        status = 'live',
        external_campaign_ids = $1,
        updated_at = CURRENT_TIMESTAMP
      WHERE id = $2`,
      [JSON.stringify(externalCampaignIds), requestId]
    );

    const request = await this.getRequestById(requestId);

    // Notify organizer
    if (this.notificationService && request) {
      await this.notificationService.notifyOrganizer(
        request.organizerId,
        'Your Ad Campaigns Are Now Live!',
        `Your ad campaigns on ${request.platforms.join(', ')} are now live! You can track their performance in your dashboard.`
      );
    }

    return request as AdCampaignRequest;
  }

  /**
   * Complete a campaign
   */
  async completeCampaign(requestId: number): Promise<AdCampaignRequest> {
    await this.db.query(
      'UPDATE ad_campaign_requests SET status = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2',
      ['completed', requestId]
    );

    return this.getRequestById(requestId) as Promise<AdCampaignRequest>;
  }

  /**
   * Get campaign request statistics
   */
  async getRequestStatistics(): Promise<{
    pending: number;
    inProgress: number;
    approved: number;
    live: number;
    completed: number;
    rejected: number;
  }> {
    const query = `
      SELECT
        COUNT(CASE WHEN status = 'pending_review' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'live' THEN 1 END) as live,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
      FROM ad_campaign_requests
    `;

    const rows = await this.db.query(query);
    const row = rows[0];

    return {
      pending: parseInt(row.pending, 10),
      inProgress: parseInt(row.in_progress, 10),
      approved: parseInt(row.approved, 10),
      live: parseInt(row.live, 10),
      completed: parseInt(row.completed, 10),
      rejected: parseInt(row.rejected, 10),
    };
  }

  /**
   * Map database row to AdCampaignRequest
   */
  private mapRequestFromRow(row: any): AdCampaignRequest {
    return {
      id: row.id,
      orderItemId: row.order_item_id,
      organizerId: row.organizer_id,
      eventId: row.event_id,
      platforms: row.platforms as AdPlatform[],
      campaignName: row.campaign_name,
      campaignObjective: row.campaign_objective,
      targetAudience: row.target_audience || {},
      budget: parseFloat(row.budget),
      budgetType: row.budget_type,
      durationDays: row.duration_days,
      startDate: row.start_date ? new Date(row.start_date) : null,
      endDate: row.end_date ? new Date(row.end_date) : null,
      creativeAssets: row.creative_assets || [],
      adCopy: row.ad_copy,
      landingUrl: row.landing_url,
      notes: row.notes,
      status: row.status,
      assignedTo: row.assigned_to,
      reviewedAt: row.reviewed_at ? new Date(row.reviewed_at) : null,
      reviewedBy: row.reviewed_by,
      rejectionReason: row.rejection_reason,
      externalCampaignIds: row.external_campaign_ids || {},
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }
}

export default AdCampaignCreationService;
