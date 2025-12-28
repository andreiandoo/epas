/**
 * API Client for Ticket Customizer
 *
 * Connects to the Laravel backend REST API
 */

import axios, { AxiosInstance } from 'axios';
import type {
  TicketTemplate,
  TemplateData,
  ValidationResult,
  PreviewResult,
  VariableCategory,
  TemplatePreset,
} from './types';

export class TicketCustomizerAPI {
  private client: AxiosInstance;

  constructor(baseURL: string = '/api/tickets/templates') {
    this.client = axios.create({
      baseURL,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });
  }

  /**
   * Get available variables for templates
   */
  async getVariables(tenantId: string): Promise<{
    tenant_id: string;
    tenant_name: string;
    variables: VariableCategory[];
    sample_data: Record<string, any>;
  }> {
    const response = await this.client.get('/variables', {
      params: { tenant: tenantId },
    });
    return response.data;
  }

  /**
   * Validate template JSON
   */
  async validate(templateData: TemplateData): Promise<ValidationResult> {
    const response = await this.client.post('/validate', {
      template_json: templateData,
    });
    return response.data;
  }

  /**
   * Generate preview image
   */
  async preview(
    templateData: TemplateData,
    sampleData?: Record<string, any>,
    scale: number = 2
  ): Promise<PreviewResult> {
    const response = await this.client.post('/preview', {
      template_json: templateData,
      sample_data: sampleData,
      scale,
    });
    return response.data;
  }

  /**
   * Get preset dimensions
   */
  async getPresets(): Promise<{ presets: TemplatePreset[] }> {
    const response = await this.client.get('/presets');
    return response.data;
  }

  /**
   * List templates for a tenant
   */
  async list(tenantId: string, status?: string): Promise<{
    tenant_id: string;
    templates: TicketTemplate[];
  }> {
    const response = await this.client.get('/', {
      params: {
        tenant: tenantId,
        status,
      },
    });
    return response.data;
  }

  /**
   * Get a specific template
   */
  async get(id: string): Promise<{ template: TicketTemplate }> {
    const response = await this.client.get(`/${id}`);
    return response.data;
  }

  /**
   * Create a new template
   */
  async create(data: {
    tenant_id: string;
    name: string;
    description?: string;
    template_data: TemplateData;
    status?: 'draft' | 'active' | 'archived';
  }): Promise<{
    success: boolean;
    template: TicketTemplate;
    warnings: string[];
  }> {
    const response = await this.client.post('/', data);
    return response.data;
  }

  /**
   * Update a template
   */
  async update(
    id: string,
    data: {
      name?: string;
      description?: string;
      template_data?: TemplateData;
      status?: 'draft' | 'active' | 'archived';
    }
  ): Promise<{
    success: boolean;
    template: TicketTemplate;
  }> {
    const response = await this.client.put(`/${id}`, data);
    return response.data;
  }

  /**
   * Delete a template
   */
  async delete(id: string): Promise<{
    success: boolean;
    message: string;
  }> {
    const response = await this.client.delete(`/${id}`);
    return response.data;
  }

  /**
   * Set template as default
   */
  async setDefault(id: string): Promise<{
    success: boolean;
    message: string;
    template: TicketTemplate;
  }> {
    const response = await this.client.post(`/${id}/set-default`);
    return response.data;
  }

  /**
   * Create a new version of a template
   */
  async createVersion(
    id: string,
    data: {
      template_data: TemplateData;
      name?: string;
    }
  ): Promise<{
    success: boolean;
    template: TicketTemplate;
    warnings: string[];
  }> {
    const response = await this.client.post(`/${id}/create-version`, data);
    return response.data;
  }
}

// Export singleton instance
export const ticketCustomizerAPI = new TicketCustomizerAPI();
