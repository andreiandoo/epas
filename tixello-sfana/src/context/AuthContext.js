import React, { createContext, useContext, useState, useCallback } from 'react';
import { login as apiLogin, logout as apiLogout, getMe, switchOrganizer as apiSwitchOrganizer } from '../api/auth';
import { setToken, initApiClient, getToken } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  // Common
  const [userType, setUserType] = useState(null); // 'organizer' | 'venue_owner'
  const [isLoading, setIsLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isSwitching, setIsSwitching] = useState(false);

  // Organizer / team member
  const [user, setUser] = useState(null);
  const [availableOrganizers, setAvailableOrganizers] = useState([]);

  // Venue owner
  const [venueOwner, setVenueOwner] = useState(null);

  // Derived — organizer flow
  const userRole = user?.team_member?.role || 'owner';
  const userPermissions = user?.team_member?.permissions || ['events', 'orders', 'reports', 'team', 'checkin'];
  const isTeamMember = !!user?.team_member;
  const hasMultipleOrganizers = availableOrganizers.length > 1;

  const isVenueOwner = userType === 'venue_owner';
  const isOrganizer = userType === 'organizer';

  const applyLoginPayload = useCallback((payload) => {
    const type = payload?.user_type || 'organizer';
    setUserType(type);
    if (type === 'venue_owner') {
      setVenueOwner(payload.venue_owner || null);
      setUser(null);
      setAvailableOrganizers([]);
    } else {
      setUser(payload.organizer || null);
      setAvailableOrganizers(payload.available_organizers || []);
      setVenueOwner(null);
    }
    setIsAuthenticated(true);
  }, []);

  const login = useCallback(async (email, password) => {
    const response = await apiLogin(email, password);
    if (response?.success && response.data) {
      applyLoginPayload(response.data);
    }
    return response;
  }, [applyLoginPayload]);

  const logout = useCallback(async () => {
    await apiLogout();
    setUser(null);
    setVenueOwner(null);
    setAvailableOrganizers([]);
    setUserType(null);
    setIsAuthenticated(false);
  }, []);

  const switchOrganizer = useCallback(async (organizerId) => {
    setIsSwitching(true);
    try {
      const data = await apiSwitchOrganizer(organizerId);
      if (data.success && data.data) {
        setUser(data.data.organizer);
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
      if (data?.success && data.data) {
        applyLoginPayload(data.data);
        setIsLoading(false);
        return true;
      }
    } catch (e) {
      // Token expired or invalid
    }
    setIsLoading(false);
    setIsAuthenticated(false);
    setUserType(null);
    return false;
  }, [applyLoginPayload]);

  return (
    <AuthContext.Provider value={{
      // Common
      userType,
      isVenueOwner,
      isOrganizer,
      isLoading,
      isAuthenticated,
      isSwitching,
      login,
      logout,
      checkAuth,

      // Organizer flow
      user,
      userRole,
      userPermissions,
      isTeamMember,
      availableOrganizers,
      hasMultipleOrganizers,
      switchOrganizer,

      // Venue owner
      venueOwner,
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
