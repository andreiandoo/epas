// Tixello Sf. Ana — entry point.
// Navigation simplificat: Login → Hub (rol-based) → ecrane operator.
import React, { useEffect, useState } from 'react';
import { View, Text, ActivityIndicator, StatusBar } from 'react-native';
import { NavigationContainer, DarkTheme } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { SafeAreaProvider } from 'react-native-safe-area-context';

import { AuthProvider, useAuth } from './src/context/AuthContext';
import { ShiftProvider } from './src/context/ShiftContext';

import SplashScreen from './src/screens/SplashScreen';
import LoginScreen from './src/screens/LoginScreen';
import HubScreen from './src/screens/HubScreen';
import ScannerScreen from './src/screens/ScannerScreen';
import BoatsOperatorScreen from './src/screens/BoatsOperatorScreen';
import PontoonOperatorScreen from './src/screens/PontoonOperatorScreen';
import POSScreen from './src/screens/POSScreen';
import CheckinOperatorScreen from './src/screens/CheckinOperatorScreen';
import FieldOperatorScreen from './src/screens/FieldOperatorScreen';
import KioskScreen from './src/screens/KioskScreen';

import { colors } from './src/theme/colors';

const Stack = createNativeStackNavigator();

function AppNavigator() {
  const auth = useAuth();
  const user = auth.user || auth.venueOwner;
  const loading = auth.isLoading;

  useEffect(() => {
    auth.checkAuth();
  }, []);

  if (loading) {
    return (
      <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.background }}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  // Kiosk mode: user cu team_member.leisure_role === 'kiosk_selfcheckin' vede
  // DOAR ecranul de self-checkin (fara nav, fara logout accesibil). Se
  // seteaza la crearea contului in /organizator/echipa.
  const isKiosk = user?.team_member?.leisure_role === 'kiosk_selfcheckin';

  return (
    <Stack.Navigator
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.background },
        animation: 'fade',
      }}
    >
      {!user ? (
        <Stack.Screen name="Login" component={LoginScreen} />
      ) : isKiosk ? (
        // Kiosk mode: SINGUR ecran, fara alte rute. Blocheaza back button.
        <Stack.Screen name="Kiosk" component={KioskScreen} options={{ gestureEnabled: false }} />
      ) : (
        <>
          <Stack.Screen name="Hub" component={HubScreen} />
          <Stack.Screen name="BoatsOperator" component={BoatsOperatorScreen} />
          <Stack.Screen name="PontoonOperator" component={PontoonOperatorScreen} />
          <Stack.Screen name="POS" component={POSScreen} />
          <Stack.Screen name="Checkin" component={CheckinOperatorScreen} />
          <Stack.Screen name="FieldOperator" component={FieldOperatorScreen} />
          <Stack.Screen name="Scanner" component={ScannerScreen} options={{ presentation: 'modal' }} />
        </>
      )}
    </Stack.Navigator>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <StatusBar barStyle="light-content" backgroundColor={colors.background} />
      <AuthProvider>
        <ShiftProvider>
          <NavigationContainer
            theme={{
              ...DarkTheme,
              dark: true,
              colors: {
                ...DarkTheme.colors,
                primary: colors.primary,
                background: colors.background,
                card: colors.surface,
                text: colors.textPrimary,
                border: colors.border,
                notification: colors.accent,
              },
            }}
          >
            <AppNavigator />
          </NavigationContainer>
        </ShiftProvider>
      </AuthProvider>
    </SafeAreaProvider>
  );
}
