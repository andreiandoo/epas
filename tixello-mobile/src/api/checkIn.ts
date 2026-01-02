import apiClient from './client';
import { CheckInResult, ApiResponse } from '../types';

export const checkInApi = {
  /**
   * Check in a ticket by barcode/QR code
   */
  checkIn: async (eventId: number, barcode: string): Promise<ApiResponse<CheckInResult>> => {
    return apiClient.post(
      `/api/marketplace-client/organizer/events/${eventId}/check-in/${barcode}`
    );
  },

  /**
   * Undo a check-in (mark ticket as not used)
   */
  undoCheckIn: async (eventId: number, barcode: string): Promise<ApiResponse<void>> => {
    return apiClient.delete(
      `/api/marketplace-client/organizer/events/${eventId}/check-in/${barcode}`
    );
  },

  /**
   * Validate a ticket without checking in
   */
  validateTicket: async (
    eventId: number,
    barcode: string
  ): Promise<ApiResponse<CheckInResult>> => {
    return apiClient.get(
      `/api/marketplace-client/organizer/events/${eventId}/validate/${barcode}`
    );
  },

  /**
   * Batch check-in multiple tickets
   */
  batchCheckIn: async (
    eventId: number,
    barcodes: string[]
  ): Promise<ApiResponse<CheckInResult[]>> => {
    return apiClient.post(
      `/api/marketplace-client/organizer/events/${eventId}/check-in/batch`,
      { barcodes }
    );
  },

  /**
   * Get check-in statistics for an event
   */
  getCheckInStats: async (eventId: number): Promise<
    ApiResponse<{
      total: number;
      checked_in: number;
      pending: number;
      cancelled: number;
    }>
  > => {
    return apiClient.get(
      `/api/marketplace-client/organizer/events/${eventId}/check-in/stats`
    );
  },
};

export default checkInApi;
