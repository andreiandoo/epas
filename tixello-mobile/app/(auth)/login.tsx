import { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Alert,
} from 'react-native';
import { router } from 'expo-router';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { Input, Button } from '../../src/components/ui';
import { useAuthStore } from '../../src/stores/authStore';
import { authApi, apiClient } from '../../src/api';
import { colors, spacing, typography, borderRadius } from '../../src/utils/theme';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const { login } = useAuthStore();

  const handleLogin = async () => {
    if (!email || !password) {
      setError('Please enter email and password');
      return;
    }

    setIsLoading(true);
    setError('');

    try {
      const response = await authApi.login({ email, password });

      if (response.success && response.data) {
        const { user, token, tenant } = response.data;

        // Set tenant ID for API calls
        apiClient.setTenantId(tenant.id);

        // Save to store
        await login(user, token, tenant);

        // Navigate to main app
        router.replace('/(main)/(tabs)/dashboard');
      } else {
        setError(response.message || 'Login failed');
      }
    } catch (err: any) {
      console.error('Login error:', err);
      setError(err.response?.data?.message || 'Invalid credentials');
    } finally {
      setIsLoading(false);
    }
  };

  const fillDemoCredentials = (type: 'admin' | 'scanner') => {
    if (type === 'admin') {
      setEmail('admin@tixello.com');
      setPassword('admin');
    } else {
      setEmail('scanner@tixello.com');
      setPassword('scanner');
    }
    setError('');
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
      >
        {/* Header */}
        <View style={styles.header}>
          <View style={styles.logoRow}>
            <LinearGradient
              colors={[colors.primary, colors.primaryDark]}
              style={styles.logoBackground}
            >
              <View style={styles.logoLines}>
                <View style={[styles.logoLine, { width: '100%' }]} />
                <View style={[styles.logoLine, { width: '75%' }]} />
                <View style={[styles.logoLine, { width: '50%' }]} />
              </View>
            </LinearGradient>
            <Text style={styles.brandName}>Tixello</Text>
          </View>
          <Text style={styles.title}>Welcome back</Text>
          <Text style={styles.subtitle}>Sign in to manage your event</Text>
        </View>

        {/* Form */}
        <View style={styles.form}>
          <Input
            label="Email"
            value={email}
            onChangeText={setEmail}
            placeholder="you@example.com"
            keyboardType="email-address"
            autoComplete="email"
            autoCapitalize="none"
          />

          <Input
            label="Password"
            value={password}
            onChangeText={setPassword}
            placeholder="Enter your password"
            secureTextEntry
            autoComplete="password"
          />

          {error ? <Text style={styles.error}>{error}</Text> : null}

          <Button
            title="Sign In →"
            onPress={handleLogin}
            loading={isLoading}
            size="lg"
            style={styles.loginButton}
          />
        </View>

        {/* Demo Accounts */}
        <View style={styles.demoSection}>
          <Text style={styles.demoLabel}>Demo accounts:</Text>
          <View style={styles.demoButtons}>
            <TouchableOpacity
              style={styles.demoButton}
              onPress={() => fillDemoCredentials('admin')}
            >
              <View style={styles.demoButtonContent}>
                <Ionicons name="person" size={14} color={colors.textSecondary} />
                <Text style={styles.demoRole}>Admin</Text>
              </View>
              <Text style={styles.demoEmail}>admin@tixello.com</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.demoButton}
              onPress={() => fillDemoCredentials('scanner')}
            >
              <View style={styles.demoButtonContent}>
                <Ionicons name="phone-portrait" size={14} color={colors.textSecondary} />
                <Text style={styles.demoRole}>Scanner</Text>
              </View>
              <Text style={styles.demoEmail}>scanner@tixello.com</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  scrollContent: {
    flexGrow: 1,
    padding: spacing.xxl,
    paddingTop: 60,
  },
  header: {
    alignItems: 'center',
    marginBottom: 40,
  },
  logoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginBottom: spacing.xxxl,
  },
  logoBackground: {
    width: 48,
    height: 48,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  logoLines: {
    width: 28,
    height: 18,
    justifyContent: 'space-between',
  },
  logoLine: {
    height: 3,
    backgroundColor: '#fff',
    borderRadius: 1.5,
  },
  brandName: {
    fontSize: typography.fontSize.xxl,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: spacing.sm,
  },
  subtitle: {
    fontSize: typography.fontSize.md,
    color: colors.textMuted,
  },
  form: {
    marginBottom: spacing.xxl,
  },
  error: {
    color: colors.error,
    fontSize: typography.fontSize.sm,
    marginBottom: spacing.md,
    textAlign: 'center',
  },
  loginButton: {
    marginTop: spacing.md,
  },
  demoSection: {
    alignItems: 'center',
  },
  demoLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginBottom: spacing.md,
  },
  demoButtons: {
    flexDirection: 'row',
    gap: spacing.md,
  },
  demoButton: {
    flex: 1,
    padding: spacing.md,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
  },
  demoButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    marginBottom: spacing.xs,
  },
  demoRole: {
    fontSize: typography.fontSize.sm,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  demoEmail: {
    fontSize: typography.fontSize.xs,
    color: colors.textMuted,
  },
});
