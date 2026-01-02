import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';
import * as SecureStore from 'expo-secure-store';
import { useAuthStore } from '../stores/authStore';

// Configuration - Update this with your Tixello backend URL
const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL || 'https://api.tixello.com';

class ApiClient {
  private client: AxiosInstance;
  private tenantId: string | null = null;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors() {
    // Request interceptor - add auth token and tenant ID
    this.client.interceptors.request.use(
      async (config: InternalAxiosRequestConfig) => {
        try {
          const token = await SecureStore.getItemAsync('auth_token');
          if (token) {
            config.headers.Authorization = `Bearer ${token}`;
          }
          if (this.tenantId) {
            config.headers['X-Tenant-ID'] = this.tenantId;
          }
        } catch (error) {
          console.error('Error getting auth token:', error);
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor - handle errors
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Token expired - trigger logout
          console.log('Token expired, logging out...');
          const { logout } = useAuthStore.getState();
          await logout();
        }

        // Network error handling
        if (!error.response) {
          console.error('Network error:', error.message);
        }

        return Promise.reject(error);
      }
    );
  }

  setTenantId(id: string | number) {
    this.tenantId = String(id);
  }

  clearTenantId() {
    this.tenantId = null;
  }

  get instance() {
    return this.client;
  }

  // Convenience methods
  async get<T>(url: string, config?: object) {
    const response = await this.client.get<T>(url, config);
    return response.data;
  }

  async post<T>(url: string, data?: object, config?: object) {
    const response = await this.client.post<T>(url, data, config);
    return response.data;
  }

  async put<T>(url: string, data?: object, config?: object) {
    const response = await this.client.put<T>(url, data, config);
    return response.data;
  }

  async delete<T>(url: string, config?: object) {
    const response = await this.client.delete<T>(url, config);
    return response.data;
  }

  async patch<T>(url: string, data?: object, config?: object) {
    const response = await this.client.patch<T>(url, data, config);
    return response.data;
  }
}

export const apiClient = new ApiClient();
export default apiClient;
