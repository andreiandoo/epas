/** Root navigation: auth stack when logged out, tab navigator when in. */
import React from 'react';
import { Text } from 'react-native';
import { NavigationContainer, DefaultTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { palette } from '@/theme/colors';
import { useApp } from '@/store/AppContext';
import { navTabsFor } from './tabs';

import LoginScreen from '@/screens/LoginScreen';
import SwitcherScreen from '@/screens/SwitcherScreen';
import NotificationsScreen from '@/screens/NotificationsScreen';
import SeatMapScreen from '@/screens/SeatMapScreen';

export type RootStackParams = {
  Main: undefined;
  Switcher: undefined;
  Notifications: undefined;
  SeatMap: { eventId: number; ticketTypeId: number; ticketName: string };
};

const Stack = createNativeStackNavigator<RootStackParams>();
const Tab = createBottomTabNavigator();

const theme = {
  ...DefaultTheme,
  dark: true,
  colors: {
    ...DefaultTheme.colors,
    background: palette.bg,
    card: palette.bg,
    text: palette.text,
    border: palette.border,
    primary: palette.text,
  },
};

function MainTabs() {
  const { contextKind, accent } = useApp();
  const tabs = navTabsFor(contextKind);
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: accent.base,
        tabBarInactiveTintColor: palette.faint,
        tabBarStyle: {
          backgroundColor: palette.bg,
          borderTopColor: palette.border,
          height: 68,
          paddingTop: 8,
          paddingBottom: 12,
        },
        tabBarLabelStyle: { fontSize: 9, fontWeight: '700' },
      }}
    >
      {tabs.map((t) => (
        <Tab.Screen
          key={t.key}
          name={t.label}
          component={t.component}
          options={{
            tabBarIcon: ({ color }) => (
              <Text style={{ color, fontSize: 18 }}>{t.icon}</Text>
            ),
          }}
        />
      ))}
    </Tab.Navigator>
  );
}

export default function Navigation() {
  const { authed } = useApp();
  return (
    <NavigationContainer theme={theme}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!authed ? (
          <Stack.Screen name="Main" component={LoginScreen} />
        ) : (
          <>
            <Stack.Screen name="Main" component={MainTabs} />
            <Stack.Screen
              name="Switcher"
              component={SwitcherScreen}
              options={{ presentation: 'modal' }}
            />
            <Stack.Screen
              name="Notifications"
              component={NotificationsScreen}
              options={{ presentation: 'modal' }}
            />
            <Stack.Screen
              name="SeatMap"
              component={SeatMapScreen}
              options={{ presentation: 'modal' }}
            />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
