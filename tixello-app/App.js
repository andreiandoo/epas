import React, { useEffect, useState } from 'react';
import { View, StyleSheet, StatusBar, Platform } from 'react-native';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import Svg, { Rect, Path, Circle } from 'react-native-svg';

import ErrorBoundary from './src/components/ErrorBoundary';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import { EventProvider, useEvent } from './src/context/EventContext';
import { AppProvider, useApp } from './src/context/AppContext';

import SplashScreen from './src/screens/SplashScreen';
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import CheckInScreen from './src/screens/CheckInScreen';
import SalesScreen from './src/screens/SalesScreen';
import ReportsScreen from './src/screens/ReportsScreen';
import SettingsScreen from './src/screens/SettingsScreen';

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

function TabIcon({ name, focused }) {
  const color = focused ? colors.purple : 'rgba(255,255,255,0.4)';
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
    default:
      return null;
  }
}

function MainTabs() {
  const { userRole } = useAuth();
  const insets = useSafeAreaInsets();
  const { groupedEvents, selectEvent, fetchEvents } = useEvent();
  const { notifications, markAllRead, shiftStartTime } = useApp();

  const [showEventsModal, setShowEventsModal] = useState(false);
  const [showNotifications, setShowNotifications] = useState(false);
  const [showEmergency, setShowEmergency] = useState(false);
  const [showStaff, setShowStaff] = useState(false);
  const [showGuestList, setShowGuestList] = useState(false);
  const [showGateManager, setShowGateManager] = useState(false);
  const [showStaffAssignment, setShowStaffAssignment] = useState(false);

  useEffect(() => {
    fetchEvents();
  }, []);

  const isAdmin = userRole === 'admin' || userRole === 'owner';

  return (
    <View style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor={colors.background} />
      <Header onNotificationPress={() => setShowNotifications(true)} />
      <EventSelector onPress={() => setShowEventsModal(true)} />
      {shiftStartTime && (
        <ShiftBar onEmergencyPress={() => setShowEmergency(true)} />
      )}

      <Tab.Navigator
        screenOptions={({ route }) => ({
          headerShown: false,
          tabBarIcon: ({ focused }) => <TabIcon name={route.name} focused={focused} />,
          tabBarActiveTintColor: colors.purple,
          tabBarInactiveTintColor: 'rgba(255,255,255,0.4)',
          tabBarStyle: {
            backgroundColor: colors.background,
            borderTopColor: 'rgba(255,255,255,0.05)',
            borderTopWidth: 1,
            paddingBottom: Math.max(insets.bottom, 8),
            paddingTop: 8,
            height: 56 + Math.max(insets.bottom, 8),
          },
          tabBarLabelStyle: {
            fontSize: 11,
            fontWeight: '500',
          },
        })}
      >
        <Tab.Screen name="Dashboard" options={{ tabBarLabel: 'Dashboard' }}>
          {(props) => (
            <DashboardScreen
              {...props}
              onShowStaff={() => setShowStaff(true)}
              onShowGuestList={() => setShowGuestList(true)}
            />
          )}
        </Tab.Screen>
        <Tab.Screen name="CheckIn" component={CheckInScreen} options={{ tabBarLabel: 'Scan' }} />
        <Tab.Screen name="Sales" component={SalesScreen} options={{ tabBarLabel: 'Sell' }} />
        {isAdmin && (
          <Tab.Screen name="Reports" component={ReportsScreen} options={{ tabBarLabel: 'Reports' }} />
        )}
        <Tab.Screen name="Settings" options={{ tabBarLabel: 'Settings' }}>
          {(props) => (
            <SettingsScreen
              {...props}
              onShowGateManager={() => setShowGateManager(true)}
              onShowStaffAssignment={() => setShowStaffAssignment(true)}
            />
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
        <StaffModal visible={showStaff} onClose={() => setShowStaff(false)} staffMembers={[]} />
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
    </View>
  );
}

function AuthNavigator() {
  const { isAuthenticated, checkAuth } = useAuth();
  const [showSplash, setShowSplash] = useState(true);

  useEffect(() => {
    checkAuth();
  }, []);

  if (showSplash) {
    return <SplashScreen onFinish={() => setShowSplash(false)} />;
  }

  if (!isAuthenticated) {
    return <LoginScreen onLoginSuccess={() => {}} />;
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
});
