import apiClient from './client';
import { Event, TicketType, ApiResponse, PaginatedResponse } from '../types';

export const eventsApi = {
  /**
   * Get all events for the tenant
   */
  getEvents: async (): Promise<ApiResponse<Event[]>> => {
    return apiClient.get('/api/tenant-client/admin/events');
  },

  /**
   * Get a single event by ID
   */
  getEvent: async (eventId: number): Promise<ApiResponse<Event>> => {
    return apiClient.get(`/api/tenant-client/admin/events/${eventId}`);
  },

  /**
   * Get events available for door sales
   */
  getDoorSalesEvents: async (): Promise<ApiResponse<Event[]>> => {
    return apiClient.get('/api/door-sales/events');
  },

  /**
   * Get ticket types for an event (door sales)
   */
  getTicketTypes: async (eventId: number): Promise<ApiResponse<TicketType[]>> => {
    return apiClient.get(`/api/door-sales/events/${eventId}/ticket-types`);
  },

  /**
   * Get event participants (for check-in)
   */
  getParticipants: async (
    eventId: number,
    page: number = 1
  ): Promise<PaginatedResponse<any>> => {
    return apiClient.get(
      `/api/marketplace-client/organizer/events/${eventId}/participants`,
      { params: { page } }
    );
  },

  /**
   * Get marketplace organizer events
   */
  getOrganizerEvents: async (): Promise<ApiResponse<Event[]>> => {
    return apiClient.get('/api/marketplace-client/organizer/events');
  },

  /**
   * Export participants list
   */
  exportParticipants: async (eventId: number): Promise<Blob> => {
    const response = await apiClient.instance.get(
      `/api/marketplace-client/organizer/events/${eventId}/participants/export`,
      { responseType: 'blob' }
    );
    return response.data;
  },
};

export default eventsApi;
