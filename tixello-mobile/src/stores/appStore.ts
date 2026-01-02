import { create } from 'zustand';
import { AppNotification } from '../types';

interface AppState {
  isOnline: boolean;
  isShiftPaused: boolean;
  shiftStartTime: string | null;
  notifications: AppNotification[];
  showNotifications: boolean;
  pendingSyncCount: number;

  // Actions
  setOnline: (online: boolean) => void;
  setShiftPaused: (paused: boolean) => void;
  startShift: () => void;
  endShift: () => void;
  addNotification: (notification: Omit<AppNotification, 'id'>) => void;
  markNotificationRead: (id: number) => void;
  markAllNotificationsRead: () => void;
  clearNotifications: () => void;
  setShowNotifications: (show: boolean) => void;
  setPendingSyncCount: (count: number) => void;
}

export const useAppStore = create<AppState>((set, get) => ({
  isOnline: true,
  isShiftPaused: false,
  shiftStartTime: null,
  notifications: [],
  showNotifications: false,
  pendingSyncCount: 0,

  setOnline: (isOnline) => set({ isOnline }),

  setShiftPaused: (isShiftPaused) => set({ isShiftPaused }),

  startShift: () => {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });
    set({ shiftStartTime: timeStr, isShiftPaused: false });
  },

  endShift: () => {
    set({ shiftStartTime: null, isShiftPaused: false });
  },

  addNotification: (notification) => {
    const { notifications } = get();
    const newNotification: AppNotification = {
      ...notification,
      id: Date.now(),
    };
    set({ notifications: [newNotification, ...notifications].slice(0, 50) });
  },

  markNotificationRead: (id) => {
    const { notifications } = get();
    set({
      notifications: notifications.map(n =>
        n.id === id ? { ...n, unread: false } : n
      ),
    });
  },

  markAllNotificationsRead: () => {
    const { notifications } = get();
    set({
      notifications: notifications.map(n => ({ ...n, unread: false })),
    });
  },

  clearNotifications: () => set({ notifications: [] }),

  setShowNotifications: (showNotifications) => set({ showNotifications }),

  setPendingSyncCount: (pendingSyncCount) => set({ pendingSyncCount }),
}));
