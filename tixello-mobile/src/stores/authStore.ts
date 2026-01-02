import { create } from 'zustand';
import * as SecureStore from 'expo-secure-store';
import { User, Tenant, ROLE_PERMISSIONS, UserRole } from '../types';

interface AuthState {
  user: User | null;
  token: string | null;
  tenant: Tenant | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;

  // Role permission helpers
  canAccessSales: () => boolean;
  canAccessReports: () => boolean;
  canViewRevenue: () => boolean;
  canManageStaff: () => boolean;
  isAdmin: () => boolean;
  isStaff: () => boolean;

  // Actions
  setUser: (user: User | null) => void;
  setToken: (token: string | null) => void;
  setTenant: (tenant: Tenant | null) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
  login: (user: User, token: string, tenant: Tenant) => Promise<void>;
  logout: () => Promise<void>;
  loadStoredAuth: () => Promise<void>;
}

// Helper function to get permissions for a role
const getPermissions = (role: UserRole | undefined) => {
  if (!role) return ROLE_PERMISSIONS.scanner; // Default to most restricted
  return ROLE_PERMISSIONS[role] || ROLE_PERMISSIONS.scanner;
};

export const useAuthStore = create<AuthState>((set, get) => ({
  user: null,
  token: null,
  tenant: null,
  isAuthenticated: false,
  isLoading: true,
  error: null,

  // Role permission helpers
  canAccessSales: () => getPermissions(get().user?.role).canAccessSales,
  canAccessReports: () => getPermissions(get().user?.role).canAccessReports,
  canViewRevenue: () => getPermissions(get().user?.role).canViewRevenue,
  canManageStaff: () => getPermissions(get().user?.role).canManageStaff,
  isAdmin: () => get().user?.role === 'admin',
  isStaff: () => {
    const role = get().user?.role;
    return role === 'scanner' || role === 'pos';
  },

  setUser: (user) => set({ user }),
  setToken: (token) => set({ token }),
  setTenant: (tenant) => set({ tenant }),
  setLoading: (isLoading) => set({ isLoading }),
  setError: (error) => set({ error }),

  login: async (user, token, tenant) => {
    try {
      await SecureStore.setItemAsync('auth_token', token);
      await SecureStore.setItemAsync('user', JSON.stringify(user));
      await SecureStore.setItemAsync('tenant', JSON.stringify(tenant));

      set({
        user,
        token,
        tenant,
        isAuthenticated: true,
        isLoading: false,
        error: null,
      });
    } catch (error) {
      console.error('Error storing auth data:', error);
      set({ error: 'Failed to save login data' });
    }
  },

  logout: async () => {
    try {
      await SecureStore.deleteItemAsync('auth_token');
      await SecureStore.deleteItemAsync('user');
      await SecureStore.deleteItemAsync('tenant');

      set({
        user: null,
        token: null,
        tenant: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
      });
    } catch (error) {
      console.error('Error clearing auth data:', error);
    }
  },

  loadStoredAuth: async () => {
    try {
      set({ isLoading: true });

      const token = await SecureStore.getItemAsync('auth_token');
      const userStr = await SecureStore.getItemAsync('user');
      const tenantStr = await SecureStore.getItemAsync('tenant');

      if (token && userStr && tenantStr) {
        const user = JSON.parse(userStr) as User;
        const tenant = JSON.parse(tenantStr) as Tenant;

        set({
          user,
          token,
          tenant,
          isAuthenticated: true,
          isLoading: false,
        });
      } else {
        set({ isLoading: false });
      }
    } catch (error) {
      console.error('Error loading stored auth:', error);
      set({ isLoading: false, error: 'Failed to load saved session' });
    }
  },
}));
