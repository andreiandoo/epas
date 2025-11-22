import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { Ionicons } from '@expo/vector-icons';

import { useAuthStore } from '../store/authStore';

// Auth Screens
import { LoginScreen } from '../screens/auth/LoginScreen';
import { AdminLoginScreen } from '../screens/auth/AdminLoginScreen';

// Customer Screens
import { CustomerTicketsScreen } from '../screens/customer/TicketsScreen';
import { CustomerOrdersScreen } from '../screens/customer/OrdersScreen';
import { CustomerEventsScreen } from '../screens/customer/EventsScreen';
import { CustomerProfileScreen } from '../screens/customer/ProfileScreen';
import { TicketDetailScreen } from '../screens/customer/TicketDetailScreen';

// Admin Screens
import { AdminEventsScreen } from '../screens/admin/EventsScreen';
import { AdminOrdersScreen } from '../screens/admin/OrdersScreen';
import { AdminScannerScreen } from '../screens/admin/ScannerScreen';
import { AdminReportsScreen } from '../screens/admin/ReportsScreen';
import { AdminProfileScreen } from '../screens/admin/ProfileScreen';

const Stack = createNativeStackNavigator();
const Tab = createBottomTabNavigator();

// Customer Tab Navigator
function CustomerTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName: keyof typeof Ionicons.glyphMap = 'ticket';

          if (route.name === 'Tickets') {
            iconName = focused ? 'ticket' : 'ticket-outline';
          } else if (route.name === 'Orders') {
            iconName = focused ? 'receipt' : 'receipt-outline';
          } else if (route.name === 'Events') {
            iconName = focused ? 'calendar' : 'calendar-outline';
          } else if (route.name === 'Profile') {
            iconName = focused ? 'person' : 'person-outline';
          }

          return <Ionicons name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: '#6366f1',
        tabBarInactiveTintColor: 'gray',
      })}
    >
      <Tab.Screen name="Tickets" component={CustomerTicketsScreen} />
      <Tab.Screen name="Orders" component={CustomerOrdersScreen} />
      <Tab.Screen name="Events" component={CustomerEventsScreen} />
      <Tab.Screen name="Profile" component={CustomerProfileScreen} />
    </Tab.Navigator>
  );
}

// Admin Tab Navigator
function AdminTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        tabBarIcon: ({ focused, color, size }) => {
          let iconName: keyof typeof Ionicons.glyphMap = 'home';

          if (route.name === 'Events') {
            iconName = focused ? 'calendar' : 'calendar-outline';
          } else if (route.name === 'Scanner') {
            iconName = focused ? 'qr-code' : 'qr-code-outline';
          } else if (route.name === 'Orders') {
            iconName = focused ? 'receipt' : 'receipt-outline';
          } else if (route.name === 'Reports') {
            iconName = focused ? 'stats-chart' : 'stats-chart-outline';
          } else if (route.name === 'Profile') {
            iconName = focused ? 'person' : 'person-outline';
          }

          return <Ionicons name={iconName} size={size} color={color} />;
        },
        tabBarActiveTintColor: '#6366f1',
        tabBarInactiveTintColor: 'gray',
      })}
    >
      <Tab.Screen name="Events" component={AdminEventsScreen} />
      <Tab.Screen name="Scanner" component={AdminScannerScreen} />
      <Tab.Screen name="Orders" component={AdminOrdersScreen} />
      <Tab.Screen name="Reports" component={AdminReportsScreen} />
      <Tab.Screen name="Profile" component={AdminProfileScreen} />
    </Tab.Navigator>
  );
}

export function Navigation() {
  const auth = useAuthStore((state) => state.auth);

  return (
    <NavigationContainer>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        {!auth ? (
          // Auth Stack
          <>
            <Stack.Screen name="Login" component={LoginScreen} />
            <Stack.Screen name="AdminLogin" component={AdminLoginScreen} />
          </>
        ) : auth.type === 'customer' ? (
          // Customer Stack
          <>
            <Stack.Screen name="CustomerMain" component={CustomerTabs} />
            <Stack.Screen
              name="TicketDetail"
              component={TicketDetailScreen}
              options={{ headerShown: true, title: 'Ticket Details' }}
            />
          </>
        ) : (
          // Admin Stack
          <>
            <Stack.Screen name="AdminMain" component={AdminTabs} />
          </>
        )}
      </Stack.Navigator>
    </NavigationContainer>
  );
}
