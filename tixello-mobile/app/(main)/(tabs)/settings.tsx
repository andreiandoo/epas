import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Alert } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Card, Toggle, Button } from '../../../src/components/ui';
import { useAuthStore } from '../../../src/stores/authStore';
import { useCheckInStore } from '../../../src/stores/checkInStore';
import { useAppStore } from '../../../src/stores/appStore';
import { colors, spacing, typography, borderRadius } from '../../../src/utils/theme';

export default function SettingsScreen() {
  const { user, logout } = useAuthStore();
  const { settings, updateSettings } = useCheckInStore();
  const { isOnline, setOnline, pendingSyncCount } = useAppStore();

  const handleLogout = () => {
    Alert.alert(
      'End Shift & Logout',
      'Are you sure you want to end your shift and logout?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Logout',
          style: 'destructive',
          onPress: async () => {
            await logout();
            router.replace('/(auth)/login');
          },
        },
      ]
    );
  };

  const getRoleName = (role: string | undefined) => {
    switch (role) {
      case 'admin':
        return 'Administrator';
      case 'scanner':
        return 'Gate Staff + POS';
      case 'pos':
        return 'POS Sales';
      case 'supervisor':
        return 'Supervisor';
      default:
        return 'Staff Member';
    }
  };

  return (
    <View style={styles.container}>
      <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          <Ionicons name="settings" size={20} color={colors.textPrimary} />
          <Text style={styles.title}>Settings</Text>
        </View>

        {/* Account Section */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Account</Text>
          <Card style={styles.settingCard}>
            <View style={styles.settingItem}>
              <View style={styles.settingInfo}>
                <Text style={styles.settingLabel}>Staff Member</Text>
                <Text style={styles.settingValue}>
                  {user?.first_name} {user?.last_name}
                </Text>
              </View>
            </View>
            <View style={styles.settingDivider} />
            <View style={styles.settingItem}>
              <View style={styles.settingInfo}>
                <Text style={styles.settingLabel}>Role</Text>
                <Text style={styles.settingValue}>{getRoleName(user?.role)}</Text>
              </View>
            </View>
            <View style={styles.settingDivider} />
            <View style={styles.settingItem}>
              <View style={styles.settingInfo}>
                <Text style={styles.settingLabel}>Email</Text>
                <Text style={styles.settingValue}>{user?.email}</Text>
              </View>
            </View>
          </Card>
        </View>

        {/* Scanner Section */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Scanner</Text>
          <Card style={styles.settingCard}>
            <View style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="phone-portrait" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Vibration Feedback</Text>
                  <Text style={styles.settingDesc}>Vibrate on successful scan</Text>
                </View>
              </View>
              <Toggle
                value={settings.vibration_feedback}
                onValueChange={(value) =>
                  updateSettings({ vibration_feedback: value })
                }
              />
            </View>
            <View style={styles.settingDivider} />
            <View style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="volume-high" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Sound Effects</Text>
                  <Text style={styles.settingDesc}>Play sound on scan</Text>
                </View>
              </View>
              <Toggle
                value={settings.sound_effects}
                onValueChange={(value) => updateSettings({ sound_effects: value })}
              />
            </View>
            <View style={styles.settingDivider} />
            <View style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="flash" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Auto-confirm Valid</Text>
                  <Text style={styles.settingDesc}>
                    Skip confirmation for valid tickets
                  </Text>
                </View>
              </View>
              <Toggle
                value={settings.auto_confirm_valid}
                onValueChange={(value) =>
                  updateSettings({ auto_confirm_valid: value })
                }
              />
            </View>
          </Card>
        </View>

        {/* Offline Mode Section */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Offline Mode</Text>
          <Card style={styles.settingCard}>
            <View style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="wifi" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Enable Offline Mode</Text>
                  <Text style={styles.settingDesc}>
                    Continue scanning without internet
                  </Text>
                </View>
              </View>
              <Toggle
                value={!isOnline}
                onValueChange={(value) => setOnline(!value)}
              />
            </View>
          </Card>
          <View style={styles.offlineInfo}>
            <Ionicons name="server" size={18} color={colors.info} />
            <Text style={styles.offlineText}>
              {pendingSyncCount > 0
                ? `${pendingSyncCount} items pending sync`
                : '12,456 tickets cached for offline scanning'}
            </Text>
          </View>
        </View>

        {/* Payment Section */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Payments</Text>
          <Card style={styles.settingCard}>
            <TouchableOpacity style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="card" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Card Reader</Text>
                  <Text style={[styles.settingValue, { color: colors.success }]}>
                    Connected (Tap to Pay)
                  </Text>
                </View>
              </View>
              <Ionicons name="chevron-forward" size={20} color={colors.textMuted} />
            </TouchableOpacity>
          </Card>
        </View>

        {/* Support Section */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Support</Text>
          <Card style={styles.settingCard}>
            <TouchableOpacity style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="help-circle" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Help & FAQ</Text>
                </View>
              </View>
              <Ionicons name="chevron-forward" size={20} color={colors.textMuted} />
            </TouchableOpacity>
            <View style={styles.settingDivider} />
            <TouchableOpacity style={styles.settingItem}>
              <View style={styles.settingRow}>
                <View style={styles.settingIconBox}>
                  <Ionicons name="chatbubble" size={18} color={colors.textSecondary} />
                </View>
                <View style={styles.settingInfo}>
                  <Text style={styles.settingLabel}>Contact Support</Text>
                </View>
              </View>
              <Ionicons name="chevron-forward" size={20} color={colors.textMuted} />
            </TouchableOpacity>
          </Card>
        </View>

        {/* App Info */}
        <View style={styles.appInfo}>
          <Text style={styles.appVersion}>Tixello v1.0.0</Text>
          <Text style={styles.appBuild}>Build 1</Text>
        </View>

        {/* Logout Button */}
        <Button
          title="End Shift & Logout"
          onPress={handleLogout}
          variant="danger"
          icon={<Ionicons name="log-out" size={18} color={colors.error} />}
          style={styles.logoutButton}
        />

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    paddingTop: 60,
  },
  content: {
    flex: 1,
    padding: spacing.xl,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginBottom: spacing.xxl,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  section: {
    marginBottom: spacing.xxl,
  },
  sectionTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: '600',
    color: colors.textMuted,
    textTransform: 'uppercase',
    letterSpacing: 1,
    marginBottom: spacing.md,
  },
  settingCard: {
    padding: 0,
    overflow: 'hidden',
  },
  settingItem: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: spacing.lg,
  },
  settingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  settingIconBox: {
    width: 36,
    height: 36,
    borderRadius: borderRadius.md,
    backgroundColor: colors.backgroundCard,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: spacing.md,
  },
  settingInfo: {
    flex: 1,
  },
  settingLabel: {
    fontSize: typography.fontSize.md,
    fontWeight: '500',
    color: colors.textPrimary,
  },
  settingValue: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginTop: 2,
  },
  settingDesc: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginTop: 2,
  },
  settingDivider: {
    height: 1,
    backgroundColor: colors.borderLight,
    marginLeft: spacing.lg,
  },
  offlineInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    padding: spacing.md,
    backgroundColor: colors.infoLight,
    borderRadius: borderRadius.lg,
    borderWidth: 1,
    borderColor: 'rgba(6, 182, 212, 0.2)',
    marginTop: spacing.md,
  },
  offlineText: {
    fontSize: typography.fontSize.sm,
    color: colors.textSecondary,
    flex: 1,
  },
  appInfo: {
    alignItems: 'center',
    marginVertical: spacing.xl,
  },
  appVersion: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  appBuild: {
    fontSize: typography.fontSize.xs,
    color: colors.textDisabled,
    marginTop: spacing.xs,
  },
  logoutButton: {
    marginTop: spacing.md,
  },
  bottomPadding: {
    height: 100,
  },
});
