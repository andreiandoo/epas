import { Redirect, Stack } from 'expo-router';
import { useAuthStore } from '../../src/stores/authStore';
import { colors } from '../../src/utils/theme';

export default function MainLayout() {
  const { isAuthenticated, isLoading } = useAuthStore();

  // If not authenticated, redirect to login
  if (!isLoading && !isAuthenticated) {
    return <Redirect href="/(auth)/login" />;
  }

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.background },
      }}
    >
      <Stack.Screen name="(tabs)" />
    </Stack>
  );
}
