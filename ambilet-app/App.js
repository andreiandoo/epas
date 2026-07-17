import React, { useEffect, useState, useRef } from 'react';
import { View, Text, StyleSheet, StatusBar, Platform, Modal, TouchableOpacity, Linking, AppState } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useKeepAwake } from 'expo-keep-awake';
import Svg, { Rect, Path, Circle } from 'react-native-svg';

// AmBilet Scan v2 — light red/white theme rebrand of the Tixello Staff app.
// Version bumped to 2.0.0 so update-check surfaces the redesign to older
// installs and the marketplace-side latest_version poll can differentiate
// legacy dark UI from the new brand.
const APP_VERSION = '2.0.8';

import ErrorBoundary from './src/components/ErrorBoundary';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import { EventProvider, useEvent } from './src/context/EventContext';
import { AppProvider, useApp } from './src/context/AppContext';

import SplashScreen from './src/screens/SplashScreen';
import LoginScreen from './src/screens/LoginScreen';
// ALL post-splash screens are lazy-loaded. Beyond the ~100-200ms boot-time
// win, this ALSO makes the persisted theme apply to their StyleSheets on
// the first cold start after a theme change — StyleSheet.create captures
// palette values at call time, and lazy defers those calls until AFTER
// loadPersistedTheme() has finished mutating the colors object.
const DashboardScreen = React.lazy(() => import('./src/screens/DashboardScreen'));
const CheckInScreen = React.lazy(() => import('./src/screens/CheckInScreen'));
const SalesScreen = React.lazy(() => import('./src/screens/SalesScreen'));
const ReportsScreen = React.lazy(() => import('./src/screens/ReportsScreen'));
const SettingsScreen = React.lazy(() => import('./src/screens/SettingsScreen'));
const VenueEventsScreen = React.lazy(() => import('./src/screens/VenueEventsScreen'));
const VenueEventDetailScreen = React.lazy(() => import('./src/screens/VenueEventDetailScreen'));
const VenueTicketDetailScreen = React.lazy(() => import('./src/screens/VenueTicketDetailScreen'));

import Header from './src/components/Header';
import EventSelector from './src/components/EventSelector';
import ShiftBar from './src/components/ShiftBar';

import EventsModal from './src/components/modals/EventsModal';
import NotificationsPanel from './src/components/modals/NotificationsPanel';
import EmergencyModal from './src/components/modals/EmergencyModal';
import StaffModal from './src/components/modals/StaffModal';
import GuestListModal from './src/components/modals/GuestListModal';
import GateManagerModal from './src/components/modals/GateManagerModal';
import StaffAssignmentModal from './src/components/modals/StaffAssignmentModal';

import { colors } from './src/theme/colors';
import { loadPersistedTheme } from './src/theme/bootstrapTheme';

// Immersive mode: hide the Android system nav bar so the app's own tab bar
// isn't overlapped by the phone's back/home/recents buttons. Sticky-immersive
// lets a swipe from the edge temporarily bring the nav back if the operator
// needs it. Optional dep — silent no-op if the module isn't linked yet.
let NavigationBar = null;
try {
  NavigationBar = require('expo-navigation-bar');
} catch (e) {
  NavigationBar = null;
}
if (NavigationBar && Platform.OS === 'android') {
  try {
    NavigationBar.setVisibilityAsync('hidden');
    NavigationBar.setBehaviorAsync('overlay-swipe');
  } catch (e) {}
}

// Global error handler — prevents native crash on unhandled JS errors
const originalHandler = ErrorUtils.getGlobalHandler();
ErrorUtils.setGlobalHandler((error, isFatal) => {
  console.error('Global JS error:', isFatal ? 'FATAL' : 'non-fatal', error);
  // Call original handler but don't let it crash the app
  if (originalHandler && !isFatal) {
    originalHandler(error, isFatal);
  }
});

const Tab = createBottomTabNavigator();
const Stack = createNativeStackNavigator();

function LazyScreenFallback() {
  return (
    <View style={{ flex: 1, backgroundColor: colors.background, alignItems: 'center', justifyContent: 'center' }}>
      <Text style={{ color: colors.textTertiary, fontSize: 13 }}>Se încarcă…</Text>
    </View>
  );
}

function TabIcon({ name, focused, disabled }) {
  const color = disabled ? colors.textQuaternary : (focused ? colors.purple : colors.textTertiary);
  const size = 22;

  switch (name) {
    case 'Dashboard':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Rect x="3" y="3" width="7" height="7" />
          <Rect x="14" y="3" width="7" height="7" />
          <Rect x="14" y="14" width="7" height="7" />
          <Rect x="3" y="14" width="7" height="7" />
        </Svg>
      );
    case 'CheckIn':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
          <Circle cx="12" cy="13" r="4" />
        </Svg>
      );
    case 'Sales':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Circle cx="9" cy="21" r="1" />
          <Circle cx="20" cy="21" r="1" />
          <Path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
        </Svg>
      );
    case 'Reports':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Path d="M18 20V10M12 20V4M6 20v-6" />
        </Svg>
      );
    case 'Settings':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Circle cx="12" cy="12" r="3" />
          <Path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </Svg>
      );
    case 'VenueEvents':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <Path d="M16 2v4M8 2v4M3 10h18" />
        </Svg>
      );
    case 'VenueScan':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10" strokeLinecap="round" strokeLinejoin="round" />
        </Svg>
      );
    case 'VenueSales':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2}>
          <Circle cx="9" cy="21" r="1" />
          <Circle cx="20" cy="21" r="1" />
          <Path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
        </Svg>
      );
    default:
      return null;
  }
}

function MainTabs() {
  useKeepAwake(); // Prevent screen from sleeping
  const { userRole, userPermissions, user } = useAuth();
  const insets = useSafeAreaInsets();
  const { groupedEvents, selectEvent, fetchEvents, isReportsOnlyMode } = useEvent();
  const { notifications, markAllRead, shiftStartTime } = useApp();

  const [showEventsModal, setShowEventsModal] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [showEmergency, setShowEmergency] = useState(false);
  const [showStaff, setShowStaff] = useState(false);
  const [showGuestList, setShowGuestList] = useState(false);
  const [showGateManager, setShowGateManager] = useState(false);
  const [showStaffAssignment, setShowStaffAssignment] = useState(false);
  const [updateAvailable, setUpdateAvailable] = useState(null);
  const [activeTab, setActiveTab] = useState('Dashboard');

  // Re-fetch events whenever the active organizer changes (including switches)
  useEffect(() => {
    if (user?.id) {
      fetchEvents();
    }
  }, [user?.id]);

  // Refetch events when the app comes back to the foreground — covers the
  // case where the organizer published/updated an event in the web admin
  // while the app was in background. Otherwise the picker stays stale until
  // the next app cold-start.
  const appStateRef = useRef(AppState.currentState);
  useEffect(() => {
    const sub = AppState.addEventListener('change', (next) => {
      if (appStateRef.current.match(/inactive|background/) && next === 'active') {
        if (user?.id) fetchEvents();
        // Re-hide the system nav bar — Android can restore it on some
        // interactions (dialogs, permission prompts) and we want it back
        // out of the way once the operator is back in the app.
        if (NavigationBar && Platform.OS === 'android') {
          try { NavigationBar.setVisibilityAsync('hidden'); } catch (e) {}
        }
      }
      appStateRef.current = next;
    });
    return () => sub.remove();
  }, [user?.id]);

  useEffect(() => {
    // Check for app updates
    (async () => {
      try {
        const res = await fetch('https://core.tixello.com/api/app-version');
        const data = await res.json();
        if (data.latest_version && data.latest_version !== APP_VERSION) {
          // Compare versions: only show if server version is newer
          const current = APP_VERSION.split('.').map(Number);
          const latest = data.latest_version.split('.').map(Number);
          for (let i = 0; i < 3; i++) {
            if ((latest[i] || 0) > (current[i] || 0)) {
              setUpdateAvailable({ version: data.latest_version, url: data.download_url });
              break;
            }
            if ((latest[i] || 0) < (current[i] || 0)) break;
          }
        }
      } catch (e) { /* silently ignore */ }
    })();
  }, []);

  const isAdmin = userRole === 'admin' || userRole === 'owner';
  const isStaff = userRole === 'staff';
  const hasPermission = (perm) => userPermissions.includes(perm);

  return (
    <View style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />
      <Header
        onNotificationPress={() => setShowNotifications(true)}
        pageTitle={
          activeTab === 'Reports' ? 'Rapoarte' :
          activeTab === 'Settings' ? 'Setări' :
          activeTab === 'CheckIn' ? 'Scanare' :
          activeTab === 'Sales' ? 'Vânzare' :
          null
        }
      />
      {activeTab === 'Dashboard' && (
        <EventSelector onPress={() => setShowEventsModal(true)} />
      )}
      {shiftStartTime && (
        <ShiftBar onEmergencyPress={() => setShowEmergency(true)} />
      )}

      <Tab.Navigator
        screenListeners={{
          state: (e) => {
            const routes = e.data?.state?.routes;
            const index = e.data?.state?.index;
            if (routes && typeof index === 'number') {
              setActiveTab(routes[index].name);
            }
          },
        }}
        screenOptions={({ route }) => {
          const isDisabledTab = isReportsOnlyMode && (route.name === 'CheckIn' || route.name === 'Sales');
          return {
            headerShown: false,
            tabBarIcon: ({ focused }) => <TabIcon name={route.name} focused={focused} disabled={isDisabledTab} />,
            tabBarActiveTintColor: colors.purple,
            tabBarInactiveTintColor: colors.textTertiary,
            tabBarStyle: {
              backgroundColor: colors.surface,
              borderTopColor: colors.border,
              borderTopWidth: 1,
              paddingBottom: Math.max(insets.bottom, 8),
              paddingTop: 8,
              height: 56 + Math.max(insets.bottom, 8),
            },
            tabBarLabelStyle: {
              fontSize: 11,
              fontWeight: '500',
              ...(isDisabledTab ? { color: colors.textQuaternary } : {}),
            },
          };
        }}
      >
        {(!isStaff || hasPermission('orders')) && (
          <Tab.Screen name="Dashboard" options={{ tabBarLabel: 'Panou' }}>
            {(props) => (
              <React.Suspense fallback={<LazyScreenFallback />}>
                <DashboardScreen
                  {...props}
                  onShowStaff={() => setShowStaff(true)}
                  onShowGuestList={() => setShowGuestList(true)}
                />
              </React.Suspense>
            )}
          </Tab.Screen>
        )}
        <Tab.Screen name="CheckIn" options={{ tabBarLabel: 'Scanare' }}>
          {(props) => (
            <React.Suspense fallback={<LazyScreenFallback />}>
              <CheckInScreen {...props} />
            </React.Suspense>
          )}
        </Tab.Screen>
        <Tab.Screen name="Sales" options={{ tabBarLabel: 'Vânzare' }}>
          {(props) => (
            <React.Suspense fallback={<LazyScreenFallback />}>
              <SalesScreen {...props} />
            </React.Suspense>
          )}
        </Tab.Screen>
        {isAdmin && hasPermission('reports') && (
          <Tab.Screen name="Reports" options={{ tabBarLabel: 'Rapoarte' }}>
            {(props) => (
              <React.Suspense fallback={<LazyScreenFallback />}>
                <ReportsScreen {...props} />
              </React.Suspense>
            )}
          </Tab.Screen>
        )}
        <Tab.Screen name="Settings" options={{ tabBarLabel: 'Setări' }}>
          {(props) => (
            <React.Suspense fallback={<LazyScreenFallback />}>
              <SettingsScreen
                {...props}
                appVersion={APP_VERSION}
                onShowGateManager={() => setShowGateManager(true)}
                onShowStaffAssignment={isAdmin ? () => setShowStaffAssignment(true) : null}
              />
            </React.Suspense>
          )}
        </Tab.Screen>
      </Tab.Navigator>

      {/* Lazy modals — only mount when visible */}
      {showEventsModal && (
        <EventsModal
          visible={showEventsModal}
          onClose={() => setShowEventsModal(false)}
          events={groupedEvents}
          onSelectEvent={(event) => { selectEvent(event); setShowEventsModal(false); }}
          onRefresh={fetchEvents}
        />
      )}
      {showNotifications && (
        <NotificationsPanel
          visible={showNotifications}
          onClose={() => setShowNotifications(false)}
          notifications={notifications}
          onMarkAllRead={markAllRead}
        />
      )}
      {showEmergency && (
        <EmergencyModal visible={showEmergency} onClose={() => setShowEmergency(false)} />
      )}
      {showStaff && (
        <StaffModal visible={showStaff} onClose={() => setShowStaff(false)} />
      )}
      {showGuestList && (
        <GuestListModal visible={showGuestList} onClose={() => setShowGuestList(false)} />
      )}
      {showGateManager && (
        <GateManagerModal visible={showGateManager} onClose={() => setShowGateManager(false)} />
      )}
      {showStaffAssignment && (
        <StaffAssignmentModal visible={showStaffAssignment} onClose={() => setShowStaffAssignment(false)} />
      )}

      {/* Update available banner */}
      {updateAvailable && (
        <Modal visible transparent animationType="fade" statusBarTranslucent>
          <View style={styles.updateOverlay}>
            <View style={styles.updateCard}>
              <Text style={styles.updateTitle}>Actualizare disponibilă</Text>
              <Text style={styles.updateText}>
                Versiunea {updateAvailable.version} este disponibilă.{'\n'}
                Versiunea ta curentă: {APP_VERSION}
              </Text>
              <TouchableOpacity
                style={styles.updateButton}
                onPress={() => Linking.openURL(updateAvailable.url)}
                activeOpacity={0.8}
              >
                <Text style={styles.updateButtonText}>Descarcă actualizarea</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.updateDismiss}
                onPress={() => setUpdateAvailable(null)}
                activeOpacity={0.7}
              >
                <Text style={styles.updateDismissText}>Mai târziu</Text>
              </TouchableOpacity>
            </View>
          </View>
        </Modal>
      )}
    </View>
  );
}

// ── Venue owner navigation ──────────────────────────────────────
// Tab structure: Evenimente (venue-specific stack) / Scanare / Vânzare / Setări.
// Scanare + Vânzare reuse the organizer's CheckInScreen and SalesScreen —
// the path rewriter in api/client.js transparently routes their API calls
// onto the /venue-owner namespace. The event is pre-selected by tapping
// the "Scanare" / "Vânzare" buttons inside VenueEventDetailScreen.
function withSuspense(LazyComp) {
  return (props) => (
    <React.Suspense fallback={<LazyScreenFallback />}>
      <LazyComp {...props} />
    </React.Suspense>
  );
}

function VenueOwnerEventsStack() {
  return (
    <Stack.Navigator screenOptions={{ headerShown: false }}>
      <Stack.Screen name="VenueEventsList" component={withSuspense(VenueEventsScreen)} />
      <Stack.Screen name="VenueEventDetail" component={withSuspense(VenueEventDetailScreen)} />
      <Stack.Screen name="VenueTicketDetail" component={withSuspense(VenueTicketDetailScreen)} />
    </Stack.Navigator>
  );
}

/**
 * Compact header shown above the Scanare and Vânzare tabs for venue owner.
 * Surfaces the event name, date+time and organizer name so the operator
 * always knows what they're scanning / selling for. Reads selectedEvent
 * from EventContext — set either by tapping an event in VenueEventDetail
 * or by tapping the in-screen Scanare/Vânzare action buttons.
 */
function VenueOwnerEventHeader() {
  const insets = useSafeAreaInsets();
  const { selectedEvent } = useEvent();

  if (!selectedEvent) {
    return (
      <View style={[venueHeaderStyles.wrap, { paddingTop: insets.top + 8 }]}>
        <Text style={venueHeaderStyles.empty}>Selectează un eveniment din tab-ul Evenimente</Text>
      </View>
    );
  }

  const name = selectedEvent.name || selectedEvent.title || 'Eveniment';
  const organizerName =
    selectedEvent.marketplace_organizer?.name
    || selectedEvent.organizer?.name
    || null;

  // Format date + time from either `starts_at` (ISO) or
  // start_date + start_time (legacy venue-owner shape).
  let dateLabel = '';
  if (selectedEvent.starts_at) {
    try {
      const d = new Date(selectedEvent.starts_at);
      if (!isNaN(d.getTime())) {
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        dateLabel = `${day}.${month}.${year} · ${hh}:${mm}`;
      }
    } catch { /* fall through */ }
  }
  if (!dateLabel && selectedEvent.start_date) {
    try {
      const d = new Date(selectedEvent.start_date);
      const day = String(d.getDate()).padStart(2, '0');
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const year = d.getFullYear();
      dateLabel = `${day}.${month}.${year}`;
      if (selectedEvent.start_time) {
        dateLabel += ` · ${String(selectedEvent.start_time).slice(0, 5)}`;
      }
    } catch { /* fall through */ }
  }

  return (
    <View style={[venueHeaderStyles.wrap, { paddingTop: insets.top + 8 }]}>
      <Text style={venueHeaderStyles.eventName} numberOfLines={1}>{name}</Text>
      <View style={venueHeaderStyles.metaRow}>
        {dateLabel ? (
          <Text style={venueHeaderStyles.metaText} numberOfLines={1}>{dateLabel}</Text>
        ) : null}
        {organizerName ? (
          <Text style={venueHeaderStyles.organizerText} numberOfLines={1}>{organizerName}</Text>
        ) : null}
      </View>
    </View>
  );
}

function VenueOwnerCheckInTab(props) {
  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <VenueOwnerEventHeader />
      <React.Suspense fallback={<LazyScreenFallback />}>
        <CheckInScreen {...props} />
      </React.Suspense>
    </View>
  );
}

function VenueOwnerSalesTab(props) {
  return (
    <View style={{ flex: 1, backgroundColor: colors.background }}>
      <VenueOwnerEventHeader />
      <React.Suspense fallback={<LazyScreenFallback />}>
        <SalesScreen {...props} />
      </React.Suspense>
    </View>
  );
}

const venueHeaderStyles = StyleSheet.create({
  wrap: {
    backgroundColor: colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    paddingHorizontal: 16,
    paddingBottom: 10,
  },
  eventName: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flexWrap: 'wrap',
    gap: 8,
    marginTop: 4,
  },
  metaText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.purple,
    letterSpacing: 0.3,
  },
  organizerText: {
    fontSize: 12,
    color: colors.textSecondary,
  },
  empty: {
    fontSize: 13,
    color: colors.textTertiary,
    textAlign: 'center',
  },
});

function VenueOwnerTabs() {
  useKeepAwake();
  const insets = useSafeAreaInsets();
  const [updateAvailable, setUpdateAvailable] = useState(null);

  useEffect(() => {
    (async () => {
      try {
        const res = await fetch('https://core.tixello.com/api/app-version');
        const data = await res.json();
        if (data.latest_version && data.latest_version !== APP_VERSION) {
          const current = APP_VERSION.split('.').map(Number);
          const latest = data.latest_version.split('.').map(Number);
          for (let i = 0; i < 3; i++) {
            if ((latest[i] || 0) > (current[i] || 0)) {
              setUpdateAvailable({ version: data.latest_version, url: data.download_url });
              break;
            }
            if ((latest[i] || 0) < (current[i] || 0)) break;
          }
        }
      } catch (e) { /* silently ignore */ }
    })();
  }, []);

  return (
    <View style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor={colors.background} />

      <Tab.Navigator
        screenOptions={({ route }) => ({
          headerShown: false,
          tabBarIcon: ({ focused }) => <TabIcon name={route.name} focused={focused} />,
          tabBarActiveTintColor: colors.purple,
          tabBarInactiveTintColor: colors.textTertiary,
          tabBarStyle: {
            backgroundColor: colors.surface,
            borderTopColor: colors.border,
            borderTopWidth: 1,
            paddingBottom: Math.max(insets.bottom, 8),
            paddingTop: 8,
            height: 56 + Math.max(insets.bottom, 8),
          },
          tabBarLabelStyle: { fontSize: 11, fontWeight: '500' },
        })}
      >
        <Tab.Screen
          name="VenueEvents"
          component={VenueOwnerEventsStack}
          options={{ tabBarLabel: 'Evenimente' }}
        />
        <Tab.Screen name="CheckIn" component={VenueOwnerCheckInTab} options={{ tabBarLabel: 'Scanare' }} />
        <Tab.Screen name="Sales" component={VenueOwnerSalesTab} options={{ tabBarLabel: 'Vânzare' }} />
        <Tab.Screen name="Settings" options={{ tabBarLabel: 'Setări' }}>
          {(props) => <SettingsScreen {...props} appVersion={APP_VERSION} />}
        </Tab.Screen>
      </Tab.Navigator>

      {updateAvailable && (
        <Modal visible transparent animationType="fade" statusBarTranslucent>
          <View style={styles.updateOverlay}>
            <View style={styles.updateCard}>
              <Text style={styles.updateTitle}>Actualizare disponibilă</Text>
              <Text style={styles.updateText}>
                Versiunea {updateAvailable.version} este disponibilă.{'\n'}
                Versiunea ta curentă: {APP_VERSION}
              </Text>
              <TouchableOpacity
                style={styles.updateButton}
                onPress={() => Linking.openURL(updateAvailable.url)}
                activeOpacity={0.8}
              >
                <Text style={styles.updateButtonText}>Descarcă actualizarea</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.updateDismiss}
                onPress={() => setUpdateAvailable(null)}
                activeOpacity={0.7}
              >
                <Text style={styles.updateDismissText}>Mai târziu</Text>
              </TouchableOpacity>
            </View>
          </View>
        </Modal>
      )}
    </View>
  );
}

function AuthNavigator() {
  const { isAuthenticated, userType, checkAuth } = useAuth();
  const [showSplash, setShowSplash] = useState(true);

  useEffect(() => {
    // Load the persisted theme on boot. Runs BEFORE we hide the splash so
    // there's some chance the palette mutation lands before ScreenA mounts
    // (though StyleSheet.create has already frozen for statically-imported
    // screens — theme changes still need a cold restart to fully apply).
    loadPersistedTheme().catch(() => {});
    checkAuth();
  }, []);

  if (showSplash) {
    return <SplashScreen onFinish={() => setShowSplash(false)} />;
  }

  if (!isAuthenticated) {
    return <LoginScreen onLoginSuccess={() => {}} />;
  }

  if (userType === 'venue_owner') {
    return <VenueOwnerTabs />;
  }

  return <MainTabs />;
}

export default function App() {
  return (
    <SafeAreaProvider>
      <ErrorBoundary>
        <AuthProvider>
          <AppProvider>
            <EventProvider>
              <NavigationContainer
                theme={{
                  dark: true,
                  colors: {
                    primary: colors.purple,
                    background: colors.background,
                    card: colors.background,
                    text: colors.textPrimary,
                    border: colors.border,
                    notification: colors.red,
                  },
                  fonts: {
                    regular: { fontFamily: 'System', fontWeight: '400' },
                    medium: { fontFamily: 'System', fontWeight: '500' },
                    bold: { fontFamily: 'System', fontWeight: '700' },
                    heavy: { fontFamily: 'System', fontWeight: '900' },
                  },
                }}
              >
                <AuthNavigator />
              </NavigationContainer>
            </EventProvider>
          </AppProvider>
        </AuthProvider>
      </ErrorBoundary>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  updateOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  updateCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 24,
    width: '100%',
    maxWidth: 340,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: colors.border,
  },
  updateTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  updateText: {
    fontSize: 14,
    color: colors.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 20,
  },
  updateButton: {
    backgroundColor: colors.purple,
    paddingHorizontal: 28,
    paddingVertical: 14,
    borderRadius: 12,
    width: '100%',
    alignItems: 'center',
    marginBottom: 12,
  },
  updateButtonText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#fff',
  },
  updateDismiss: {
    paddingVertical: 8,
  },
  updateDismissText: {
    fontSize: 14,
    color: colors.textTertiary,
  },
});
