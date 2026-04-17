import React, { createContext, useContext, useState, useCallback } from 'react';
import { login as apiLogin, logout as apiLogout, getMe, switchOrganizer as apiSwitchOrganizer } from '../api/auth';
import { setToken, initApiClient, getToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [availableOrganizers, setAvailableOrganizers] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isSwitching, setIsSwitching] = useState(false);

  // Determine role from team_member info or default to 'owner'
  const userRole = user?.team_member?.role || 'owner';
  const userPermissions = user?.team_member?.permissions || ['events', 'orders', 'reports', 'team', 'checkin'];
  const isTeamMember = !!user?.team_member;
  const hasMultipleOrganizers = availableOrganizers.length > 1;

  const login = useCallback(async (email, password) => {
    const data = await apiLogin(email, password);
    if (data.success && data.data) {
      setUser(data.data.organizer);
      setAvailableOrganizers(data.data.available_organizers || []);
      setIsAuthenticated(true);
    }
    return data;
  }, []);

  const logout = useCallback(async () => {
    await apiLogout();
    setUser(null);
    setAvailableOrganizers([]);
    setIsAuthenticated(false);
  }, []);

  const switchOrganizer = useCallback(async (organizerId) => {
    setIsSwitching(true);
    try {
      const data = await apiSwitchOrganizer(organizerId);
      if (data.success && data.data) {
        setUser(data.data.organizer);
        // Update current flag on list without re-fetching
        setAvailableOrganizers((prev) =>
          prev.map((o) => ({ ...o, is_current: String(o.organizer_id) === String(organizerId) }))
        );
      }
      return data;
    } finally {
      setIsSwitching(false);
    }
  }, []);

  const checkAuth = useCallback(async () => {
    try {
      await initApiClient();
      const token = getToken();
      if (!token) {
        setIsLoading(false);
        return false;
      }
      const data = await getMe();
      if (data.success && data.data) {
        setUser(data.data.organizer || data.data);
        setAvailableOrganizers(data.data.available_organizers || []);
        setIsAuthenticated(true);
        setIsLoading(false);
        return true;
      }
    } catch (e) {
      // Token expired or invalid
    }
    setIsLoading(false);
    setIsAuthenticated(false);
    return false;
  }, []);

  return (
    <AuthContext.Provider value={{
      user,
      userRole,
      userPermissions,
      isTeamMember,
      isLoading,
      isAuthenticated,
      availableOrganizers,
      hasMultipleOrganizers,
      isSwitching,
      login,
      logout,
      checkAuth,
      switchOrganizer,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within AuthProvider');
  return context;
}
