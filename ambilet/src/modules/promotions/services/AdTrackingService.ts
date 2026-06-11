/**
 * AdTrackingService
 * Handles ad campaign tracking and analytics
 */

import {
  AdPlatform,
  AdTrackingConnection,
  AdCampaignTracking,
  AdTrackingAnalytics,
} from '../types/promotion.types';

// Simulated database interface
interface DatabaseConnection {
  query(sql: string, params?: any[]): Promise<any[]>;
  transaction<T>(callback: (client: DatabaseConnection) => Promise<T>): Promise<T>;
}

// Ad platform API interfaces
interface FacebookAdsAPI {
  getAccessToken(authCode: string): Promise<{ accessToken: string; refreshToken: string; expiresIn: number }>;
  getAccountInfo(accessToken: string): Promise<{ accountId: string; accountName: string }>;
  getCampaigns(accessToken: string, accountId: string): Promise<any[]>;
  getCampaignMetrics(accessToken: string, campaignId: string): Promise<any>;
}

interface GoogleAdsAPI {
  getAccessToken(authCode: string): Promise<{ accessToken: string; refreshToken: string; expiresIn: number }>;
  getAccountInfo(accessToken: string): Promise<{ accountId: string; accountName: string }>;
  getCampaigns(accessToken: string, accountId: string): Promise<any[]>;
  getCampaignMetrics(accessToken: string, campaignId: string): Promise<any>;
}

interface TikTokAdsAPI {
  getAccessToken(authCode: string): Promise<{ accessToken: string; refreshToken: string; expiresIn: number }>;
  getAccountInfo(accessToken: string): Promise<{ accountId: string; accountName: string }>;
  getCampaigns(accessToken: string, accountId: string): Promise<any[]>;
  getCampaignMetrics(accessToken: string, campaignId: string): Promise<any>;
}

export class AdTrackingService {
  private db: DatabaseConnection;
  private facebookAPI?: FacebookAdsAPI;
  private googleAPI?: GoogleAdsAPI;
  private tiktokAPI?: TikTokAdsAPI;

  constructor(
    db: DatabaseConnection,
    apis?: {
      facebook?: FacebookAdsAPI;
      google?: GoogleAdsAPI;
      tiktok?: TikTokAdsAPI;
    }
  ) {
    this.db = db;
    this.facebookAPI = apis?.facebook;
    this.googleAPI = apis?.google;
    this.tiktokAPI = apis?.tiktok;
  }

  /**
   * Get OAuth URL for connecting an ad platform
   */
  getOAuthUrl(platform: AdPlatform, redirectUri: string, state: string): string {
    const oauthConfigs: Record<AdPlatform, { baseUrl: string; clientId: string; scopes: string[] }> = {
      [AdPlatform.FACEBOOK]: {
        baseUrl: 'https://www.facebook.com/v18.0/dialog/oauth',
        clientId: process.env.FACEBOOK_APP_ID || '',
        scopes: ['ads_read', 'ads_management', 'business_management'],
      },
      [AdPlatform.GOOGLE]: {
        baseUrl: 'https://accounts.google.com/o/oauth2/v2/auth',
        clientId: process.env.GOOGLE_CLIENT_ID || '',
        scopes: ['https://www.googleapis.com/auth/adwords'],
      },
      [AdPlatform.TIKTOK]: {
        baseUrl: 'https://ads.tiktok.com/marketing_api/auth',
        clientId: process.env.TIKTOK_APP_ID || '',
        scopes: ['ad_account_info', 'campaign_read'],
      },
    };

    const config = oauthConfigs[platform];
    const params = new URLSearchParams({
      client_id: config.clientId,
      redirect_uri: redirectUri,
      state: state,
      scope: config.scopes.join(' '),
      response_type: 'code',
    });

    return `${config.baseUrl}?${params.toString()}`;
  }

  /**
   * Connect an ad platform account
   */
  async connectPlatform(
    organizerId: number,
    platform: AdPlatform,
    authCode: string
  ): Promise<AdTrackingConnection> {
    let accessToken: string;
    let refreshToken: string;
    let expiresAt: Date;
    let accountId: string;
    let accountName: string;

    // Get tokens from the appropriate API
    switch (platform) {
      case AdPlatform.FACEBOOK:
        if (!this.facebookAPI) throw new Error('Facebook API not configured');
        const fbTokens = await this.facebookAPI.getAccessToken(authCode);
        accessToken = fbTokens.accessToken;
        refreshToken = fbTokens.refreshToken;
        expiresAt = new Date(Date.now() + fbTokens.expiresIn * 1000);
        const fbAccount = await this.facebookAPI.getAccountInfo(accessToken);
        accountId = fbAccount.accountId;
        accountName = fbAccount.accountName;
        break;

      case AdPlatform.GOOGLE:
        if (!this.googleAPI) throw new Error('Google API not configured');
        const googleTokens = await this.googleAPI.getAccessToken(authCode);
        accessToken = googleTokens.accessToken;
        refreshToken = googleTokens.refreshToken;
        expiresAt = new Date(Date.now() + googleTokens.expiresIn * 1000);
        const googleAccount = await this.googleAPI.getAccountInfo(accessToken);
        accountId = googleAccount.accountId;
        accountName = googleAccount.accountName;
        break;

      case AdPlatform.TIKTOK:
        if (!this.tiktokAPI) throw new Error('TikTok API not configured');
        const ttTokens = await this.tiktokAPI.getAccessToken(authCode);
        accessToken = ttTokens.accessToken;
        refreshToken = ttTokens.refreshToken;
        expiresAt = new Date(Date.now() + ttTokens.expiresIn * 1000);
        const ttAccount = await this.tiktokAPI.getAccountInfo(accessToken);
        accountId = ttAccount.accountId;
        accountName = ttAccount.accountName;
        break;

      default:
        throw new Error(`Unsupported platform: ${platform}`);
    }

    // Upsert connection
    const query = `
      INSERT INTO ad_tracking_connections (
        organizer_id, platform, account_id, account_name,
        access_token, refresh_token, token_expires_at, is_active, connected_at
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, true, CURRENT_TIMESTAMP)
      ON CONFLICT (organizer_id, platform) DO UPDATE SET
        account_id = EXCLUDED.account_id,
        account_name = EXCLUDED.account_name,
        access_token = EXCLUDED.access_token,
        refresh_token = EXCLUDED.refresh_token,
        token_expires_at = EXCLUDED.token_expires_at,
        is_active = true,
        connected_at = CURRENT_TIMESTAMP,
        updated_at = CURRENT_TIMESTAMP
      RETURNING *
    `;

    const rows = await this.db.query(query, [
      organizerId,
      platform,
      accountId,
      accountName,
      accessToken,
      refreshToken,
      expiresAt,
    ]);

    return this.mapConnectionFromRow(rows[0]);
  }

  /**
   * Disconnect an ad platform account
   */
  async disconnectPlatform(organizerId: number, platform: AdPlatform): Promise<void> {
    await this.db.query(
      'UPDATE ad_tracking_connections SET is_active = false, updated_at = CURRENT_TIMESTAMP WHERE organizer_id = $1 AND platform = $2',
      [organizerId, platform]
    );
  }

  /**
   * Get connected platforms for an organizer
   */
  async getConnectedPlatforms(organizerId: number): Promise<AdTrackingConnection[]> {
    const query = `
      SELECT * FROM ad_tracking_connections
      WHERE organizer_id = $1 AND is_active = true
      ORDER BY platform
    `;

    const rows = await this.db.query(query, [organizerId]);
    return rows.map(row => this.mapConnectionFromRow(row));
  }

  /**
   * Get connection by platform
   */
  async getConnection(organizerId: number, platform: AdPlatform): Promise<AdTrackingConnection | null> {
    const query = `
      SELECT * FROM ad_tracking_connections
      WHERE organizer_id = $1 AND platform = $2 AND is_active = true
    `;

    const rows = await this.db.query(query, [organizerId, platform]);
    if (rows.length === 0) return null;

    return this.mapConnectionFromRow(rows[0]);
  }

  /**
   * Sync campaigns from connected platforms
   */
  async syncCampaigns(organizerId: number, platform?: AdPlatform): Promise<number> {
    const connections = platform
      ? [await this.getConnection(organizerId, platform)].filter(Boolean) as AdTrackingConnection[]
      : await this.getConnectedPlatforms(organizerId);

    let totalSynced = 0;

    for (const connection of connections) {
      const synced = await this.syncPlatformCampaigns(connection);
      totalSynced += synced;
    }

    return totalSynced;
  }

  /**
   * Sync campaigns for a specific platform connection
   */
  private async syncPlatformCampaigns(connection: AdTrackingConnection): Promise<number> {
    if (!connection.accessToken || !connection.accountId) {
      return 0;
    }

    let campaigns: any[] = [];

    try {
      switch (connection.platform) {
        case AdPlatform.FACEBOOK:
          if (!this.facebookAPI) return 0;
          campaigns = await this.facebookAPI.getCampaigns(connection.accessToken, connection.accountId);
          break;

        case AdPlatform.GOOGLE:
          if (!this.googleAPI) return 0;
          campaigns = await this.googleAPI.getCampaigns(connection.accessToken, connection.accountId);
          break;

        case AdPlatform.TIKTOK:
          if (!this.tiktokAPI) return 0;
          campaigns = await this.tiktokAPI.getCampaigns(connection.accessToken, connection.accountId);
          break;
      }
    } catch (error) {
      console.error(`Error syncing ${connection.platform} campaigns:`, error);
      return 0;
    }

    // Upsert campaigns
    for (const campaign of campaigns) {
      await this.upsertCampaign(connection, campaign);
    }

    // Update last synced timestamp
    await this.db.query(
      'UPDATE ad_tracking_connections SET last_synced_at = CURRENT_TIMESTAMP WHERE id = $1',
      [connection.id]
    );

    return campaigns.length;
  }

  /**
   * Upsert a campaign record
   */
  private async upsertCampaign(connection: AdTrackingConnection, campaignData: any): Promise<void> {
    const query = `
      INSERT INTO ad_campaign_tracking (
        connection_id, organizer_id, platform, external_campaign_id,
        campaign_name, campaign_status, objective, budget, budget_type,
        start_date, end_date, impressions, reach, clicks, conversions,
        spend, cpc, cpm, ctr, conversion_rate, roas, last_synced_at, tracking_data
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21, CURRENT_TIMESTAMP, $22)
      ON CONFLICT (platform, external_campaign_id) DO UPDATE SET
        campaign_name = EXCLUDED.campaign_name,
        campaign_status = EXCLUDED.campaign_status,
        impressions = EXCLUDED.impressions,
        reach = EXCLUDED.reach,
        clicks = EXCLUDED.clicks,
        conversions = EXCLUDED.conversions,
        spend = EXCLUDED.spend,
        cpc = EXCLUDED.cpc,
        cpm = EXCLUDED.cpm,
        ctr = EXCLUDED.ctr,
        conversion_rate = EXCLUDED.conversion_rate,
        roas = EXCLUDED.roas,
        last_synced_at = CURRENT_TIMESTAMP,
        tracking_data = EXCLUDED.tracking_data,
        updated_at = CURRENT_TIMESTAMP
    `;

    // Calculate metrics
    const impressions = campaignData.impressions || 0;
    const clicks = campaignData.clicks || 0;
    const conversions = campaignData.conversions || 0;
    const spend = campaignData.spend || 0;

    const cpc = clicks > 0 ? spend / clicks : null;
    const cpm = impressions > 0 ? (spend / impressions) * 1000 : null;
    const ctr = impressions > 0 ? (clicks / impressions) * 100 : null;
    const conversionRate = clicks > 0 ? (conversions / clicks) * 100 : null;
    const roas = spend > 0 ? (campaignData.revenue || 0) / spend : null;

    await this.db.query(query, [
      connection.id,
      connection.organizerId,
      connection.platform,
      campaignData.id,
      campaignData.name,
      campaignData.status,
      campaignData.objective,
      campaignData.budget,
      campaignData.budgetType || 'daily',
      campaignData.startDate,
      campaignData.endDate,
      impressions,
      campaignData.reach || 0,
      clicks,
      conversions,
      spend,
      cpc,
      cpm,
      ctr,
      conversionRate,
      roas,
      JSON.stringify(campaignData),
    ]);
  }

  /**
   * Get tracked campaigns for an organizer
   */
  async getTrackedCampaigns(
    organizerId: number,
    options?: {
      platform?: AdPlatform;
      status?: string;
      limit?: number;
      offset?: number;
    }
  ): Promise<{ campaigns: AdCampaignTracking[]; total: number }> {
    let query = 'SELECT * FROM ad_campaign_tracking WHERE organizer_id = $1';
    let countQuery = 'SELECT COUNT(*) as total FROM ad_campaign_tracking WHERE organizer_id = $1';

    const params: any[] = [organizerId];
    let paramIndex = 2;

    if (options?.platform) {
      query += ` AND platform = $${paramIndex}`;
      countQuery += ` AND platform = $${paramIndex}`;
      params.push(options.platform);
      paramIndex++;
    }

    if (options?.status) {
      query += ` AND campaign_status = $${paramIndex}`;
      countQuery += ` AND campaign_status = $${paramIndex}`;
      params.push(options.status);
      paramIndex++;
    }

    query += ' ORDER BY last_synced_at DESC';

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
      this.db.query(countQuery, params.slice(0, options?.status ? 3 : (options?.platform ? 2 : 1))),
    ]);

    return {
      campaigns: rows.map(row => this.mapCampaignFromRow(row)),
      total: parseInt(countRows[0].total, 10),
    };
  }

  /**
   * Get campaign by ID
   */
  async getCampaignById(campaignId: number, organizerId?: number): Promise<AdCampaignTracking | null> {
    let query = 'SELECT * FROM ad_campaign_tracking WHERE id = $1';
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
   * Get aggregated analytics for an organizer
   */
  async getAggregatedAnalytics(organizerId: number, platform?: AdPlatform): Promise<AdTrackingAnalytics> {
    let query = `
      SELECT
        platform,
        SUM(impressions) as impressions,
        SUM(clicks) as clicks,
        SUM(conversions) as conversions,
        SUM(spend) as spend
      FROM ad_campaign_tracking
      WHERE organizer_id = $1
    `;

    const params: any[] = [organizerId];

    if (platform) {
      query += ' AND platform = $2';
      params.push(platform);
    }

    query += ' GROUP BY platform';

    const rows = await this.db.query(query, params);

    const platforms: AdTrackingAnalytics['platforms'] = rows.map(row => {
      const impressions = parseInt(row.impressions, 10);
      const clicks = parseInt(row.clicks, 10);
      const spend = parseFloat(row.spend);

      return {
        platform: row.platform as AdPlatform,
        impressions,
        clicks,
        conversions: parseInt(row.conversions, 10),
        spend,
        cpc: clicks > 0 ? Math.round((spend / clicks) * 100) / 100 : 0,
        ctr: impressions > 0 ? Math.round((clicks / impressions) * 10000) / 100 : 0,
        roas: 0, // Would need revenue data
      };
    });

    const totals = platforms.reduce(
      (acc, p) => ({
        impressions: acc.impressions + p.impressions,
        clicks: acc.clicks + p.clicks,
        conversions: acc.conversions + p.conversions,
        spend: acc.spend + p.spend,
      }),
      { impressions: 0, clicks: 0, conversions: 0, spend: 0 }
    );

    return {
      platforms,
      totalImpressions: totals.impressions,
      totalClicks: totals.clicks,
      totalConversions: totals.conversions,
      totalSpend: Math.round(totals.spend * 100) / 100,
    };
  }

  /**
   * Map database row to AdTrackingConnection
   */
  private mapConnectionFromRow(row: any): AdTrackingConnection {
    return {
      id: row.id,
      organizerId: row.organizer_id,
      platform: row.platform as AdPlatform,
      accountId: row.account_id,
      accountName: row.account_name,
      accessToken: row.access_token,
      refreshToken: row.refresh_token,
      tokenExpiresAt: row.token_expires_at ? new Date(row.token_expires_at) : null,
      isActive: row.is_active,
      connectedAt: new Date(row.connected_at),
      lastSyncedAt: row.last_synced_at ? new Date(row.last_synced_at) : null,
      metadata: row.metadata || {},
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }

  /**
   * Map database row to AdCampaignTracking
   */
  private mapCampaignFromRow(row: any): AdCampaignTracking {
    return {
      id: row.id,
      orderItemId: row.order_item_id,
      connectionId: row.connection_id,
      organizerId: row.organizer_id,
      platform: row.platform as AdPlatform,
      externalCampaignId: row.external_campaign_id,
      campaignName: row.campaign_name,
      campaignStatus: row.campaign_status,
      objective: row.objective,
      budget: row.budget ? parseFloat(row.budget) : null,
      budgetType: row.budget_type,
      startDate: row.start_date ? new Date(row.start_date) : null,
      endDate: row.end_date ? new Date(row.end_date) : null,
      impressions: parseInt(row.impressions, 10),
      reach: parseInt(row.reach, 10),
      clicks: parseInt(row.clicks, 10),
      conversions: parseInt(row.conversions, 10),
      spend: parseFloat(row.spend),
      cpc: row.cpc ? parseFloat(row.cpc) : null,
      cpm: row.cpm ? parseFloat(row.cpm) : null,
      ctr: row.ctr ? parseFloat(row.ctr) : null,
      conversionRate: row.conversion_rate ? parseFloat(row.conversion_rate) : null,
      roas: row.roas ? parseFloat(row.roas) : null,
      lastSyncedAt: row.last_synced_at ? new Date(row.last_synced_at) : null,
      trackingData: row.tracking_data || {},
      createdAt: new Date(row.created_at),
      updatedAt: new Date(row.updated_at),
    };
  }
}

export default AdTrackingService;
