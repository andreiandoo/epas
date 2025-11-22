import React, { useEffect, useState, useCallback } from 'react';
import { View, Text, StyleSheet, Platform } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import * as SplashScreen from 'expo-splash-screen';
import { Ionicons } from '@expo/vector-icons';
import { Navigation } from './src/navigation';
import { useAuthStore } from './src/store/authStore';
import { offlineDb } from './src/services/offlineDb';

// Keep the splash screen visible while we fetch resources
SplashScreen.preventAutoHideAsync().catch(() => {
  // Ignore error if splash screen is not available
});

export default function App() {
  const [appIsReady, setAppIsReady] = useState(false);
  const loadStoredAuth = useAuthStore((state) => state.loadStoredAuth);

  useEffect(() => {
    async function prepare() {
      try {
        // Initialize offline database
        if (Platform.OS !== 'web') {
          await offlineDb.init();
        }

        // Load stored authentication
        await loadStoredAuth();

        // Add any other initialization here
      } catch (e) {
        console.warn('App initialization error:', e);
      } finally {
        setAppIsReady(true);
      }
    }

    prepare();
  }, []);

  const onLayoutRootView = useCallback(async () => {
    if (appIsReady) {
      await SplashScreen.hideAsync().catch(() => {
        // Ignore error
      });
    }
  }, [appIsReady]);

  if (!appIsReady) {
    return (
      <View style={styles.loadingContainer}>
        <Ionicons name="ticket" size={64} color="#6366f1" />
        <Text style={styles.loadingText}>EPAS</Text>
        <Text style={styles.loadingSubtext}>Loading...</Text>
      </View>
    );
  }

  return (
    <SafeAreaProvider onLayout={onLayoutRootView}>
      <Navigation />
      <StatusBar style="auto" />
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f9fafb',
  },
  loadingText: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#6366f1',
    marginTop: 16,
  },
  loadingSubtext: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 8,
  },
});
