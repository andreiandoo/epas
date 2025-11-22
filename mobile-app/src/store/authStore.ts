import { create } from 'zustand';
import { AuthState, LoginCredentials, AdminLoginCredentials } from '../types/auth';
import { authService } from '../services/api';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

interface AuthStore {
  auth: AuthState;
  isLoading: boolean;
  error: string | null;

  // Actions
  customerLogin: (credentials: LoginCredentials) => Promise<void>;
  adminLogin: (credentials: AdminLoginCredentials) => Promise<void>;
  logout: () => Promise<void>;
  loadStoredAuth: () => Promise<void>;
  clearError: () => void;
}

// Storage helpers for web compatibility
const storage = {
  async getItem(key: string): Promise<string | null> {
    if (Platform.OS === 'web') {
      return localStorage.getItem(key);
    }
    return SecureStore.getItemAsync(key);
  },
  async setItem(key: string, value: string): Promise<void> {
    if (Platform.OS === 'web') {
      localStorage.setItem(key, value);
      return;
    }
    return SecureStore.setItemAsync(key, value);
  },
  async removeItem(key: string): Promise<void> {
    if (Platform.OS === 'web') {
      localStorage.removeItem(key);
      return;
    }
    return SecureStore.deleteItemAsync(key);
  },
};

export const useAuthStore = create<AuthStore>((set, get) => ({
  auth: null,
  isLoading: false,
  error: null,

  customerLogin: async (credentials) => {
    set({ isLoading: true, error: null });
    try {
      const response = await authService.customerLogin(credentials);
      const authState = {
        type: 'customer' as const,
        user: response.user,
        token: response.token,
        tenants: response.tenants || [],
      };

      await storage.setItem('auth', JSON.stringify(authState));
      set({ auth: authState, isLoading: false });
    } catch (error: any) {
      set({
        error: error.message || 'Login failed',
        isLoading: false
      });
      throw error;
    }
  },

  adminLogin: async (credentials) => {
    set({ isLoading: true, error: null });
    try {
      const response = await authService.adminLogin(credentials);
      const authState = {
        type: 'admin' as const,
        user: response.user,
        token: response.token,
        tenant: response.tenant,
        permissions: response.permissions || [],
      };

      await storage.setItem('auth', JSON.stringify(authState));
      set({ auth: authState, isLoading: false });
    } catch (error: any) {
      set({
        error: error.message || 'Login failed',
        isLoading: false
      });
      throw error;
    }
  },

  logout: async () => {
    const { auth } = get();
    if (auth) {
      try {
        await authService.logout(auth.token);
      } catch (e) {
        // Ignore logout errors
      }
    }
    await storage.removeItem('auth');
    set({ auth: null, error: null });
  },

  loadStoredAuth: async () => {
    try {
      const stored = await storage.getItem('auth');
      if (stored) {
        const authState = JSON.parse(stored);
        set({ auth: authState });
      }
    } catch (e) {
      await storage.removeItem('auth');
    }
  },

  clearError: () => set({ error: null }),
}));
