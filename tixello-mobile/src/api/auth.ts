import apiClient from './client';
import { User, Tenant, ApiResponse } from '../types';

interface LoginRequest {
  email: string;
  password: string;
}

interface LoginResponse {
  user: User;
  token: string;
  tenant: Tenant;
}

interface MeResponse {
  user: User;
  tenants: Tenant[];
}

export const authApi = {
  /**
   * Login with email and password
   */
  login: async (credentials: LoginRequest): Promise<ApiResponse<LoginResponse>> => {
    return apiClient.post('/api/tenant-client/auth/login', credentials);
  },

  /**
   * Get current authenticated user
   */
  me: async (): Promise<ApiResponse<MeResponse>> => {
    return apiClient.get('/api/tenant-client/auth/me');
  },

  /**
   * Logout (invalidate token)
   */
  logout: async (): Promise<ApiResponse<void>> => {
    return apiClient.post('/api/tenant-client/auth/logout');
  },

  /**
   * Request password reset
   */
  forgotPassword: async (email: string): Promise<ApiResponse<void>> => {
    return apiClient.post('/api/tenant-client/auth/forgot-password', { email });
  },

  /**
   * Refresh the authentication token
   */
  refreshToken: async (): Promise<ApiResponse<{ token: string }>> => {
    return apiClient.post('/api/tenant-client/auth/refresh');
  },

  /**
   * Marketplace organizer login (alternative endpoint)
   */
  organizerLogin: async (credentials: LoginRequest): Promise<ApiResponse<LoginResponse>> => {
    return apiClient.post('/api/marketplace-client/organizer/login', credentials);
  },

  /**
   * Get organizer profile
   */
  organizerMe: async (): Promise<ApiResponse<MeResponse>> => {
    return apiClient.get('/api/marketplace-client/organizer/me');
  },
};

export default authApi;
