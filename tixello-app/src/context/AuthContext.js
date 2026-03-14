import React, { createContext, useContext, useState, useCallback } from 'react';
import { login as apiLogin, logout as apiLogout, getMe } from '../api/auth';
import { setToken, initApiClient, getToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // Determine role from team_member info or default to 'owner'
  const userRole = user?.team_member?.role || 'owner';
  const userPermissions = user?.team_member?.permissions || ['events', 'orders', 'reports', 'team', 'checkin'];
  const isTeamMember = !!user?.team_member;

  const login = useCallback(async (email, password) => {
    const data = await apiLogin(email, password);
    if (data.success && data.data) {
      setUser(data.data.organizer);
      setIsAuthenticated(true);
    }
    return data;
  }, []);

  const logout = useCallback(async () => {
    await apiLogout();
    setUser(null);
    setIsAuthenticated(false);
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
      login,
      logout,
      checkAuth,
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
