import React, { useRef, useEffect, useCallback, useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Animated,
  TextInput,
  Alert,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { persistThemeMode } from '../theme/bootstrapTheme';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useAuth } from '../context/AuthContext';
import { useEvent } from '../context/EventContext';
import { useApp } from '../context/AppContext';
import { getVenueGates } from '../api/gates';
import { apiPut } from '../api/client';

function Toggle({ value, onPress }) {
  const translateX = useRef(new Animated.Value(value ? 22 : 2)).current;

  useEffect(() => {
    Animated.spring(translateX, {
      toValue: value ? 22 : 2,
      friction: 8,
      tension: 60,
      useNativeDriver: true,
    }).start();
  }, [value]);

  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      style={[
        styles.toggleTrack,
        value ? styles.toggleTrackOn : styles.toggleTrackOff,
      ]}
    >
      <Animated.View
        style={[
          styles.toggleThumb,
          { transform: [{ translateX }] },
        ]}
      />
    </TouchableOpacity>
  );
}

function SettingRow({ label, description, right }) {
  return (
    <View style={styles.settingRow}>
      <View style={styles.settingRowLeft}>
        <Text style={styles.settingLabel}>{label}</Text>
        {description ? (
          <Text style={styles.settingDescription}>{description}</Text>
        ) : null}
      </View>
      {right}
    </View>
  );
}

function SectionHeader({ title }) {
  return <Text style={styles.sectionTitle}>{title}</Text>;
}

function ThemePicker() {
  const [mode, setMode] = useState(null);
  useEffect(() => {
    (async () => {
      try {
        const raw = await AsyncStorage.getItem('theme_mode');
        setMode(raw && ['light', 'dark', 'lowLight'].includes(raw) ? raw : 'light');
      } catch { setMode('light'); }
    })();
  }, []);

  const options = [
    { key: 'light', label: 'Standard', description: 'Alb + roșu AmBilet — ideal ziua' },
    { key: 'lowLight', label: 'Contrast Mărit', description: 'Text și borduri mai închise — lumină slabă' },
    { key: 'dark', label: 'Noapte', description: 'Fundal întunecat — festivaluri de seară' },
  ];

  const handleSelect = async (key) => {
    if (key === mode) return;
    setMode(key);
    await persistThemeMode(key);
    Alert.alert(
      'Aspect salvat',
      'Repornește complet aplicația pentru a aplica noua temă.',
    );
  };

  if (mode === null) return null;

  return (
    <View style={{ gap: 8 }}>
      {options.map((opt, idx) => (
        <TouchableOpacity
          key={opt.key}
          activeOpacity={0.7}
          onPress={() => handleSelect(opt.key)}
          style={[
            styles.themeOption,
            mode === opt.key && styles.themeOptionActive,
            idx === options.length - 1 && { marginBottom: 0 },
          ]}
        >
          <View style={styles.themeRadio}>
            {mode === opt.key ? <View style={styles.themeRadioDot} /> : null}
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.themeOptionLabel}>{opt.label}</Text>
            <Text style={styles.themeOptionDescription}>{opt.description}</Text>
          </View>
        </TouchableOpacity>
      ))}
    </View>
  );
}

function EmergencyContactRow({ label, placeholder, value, onChangeText, accentColor }) {
  return (
    <View style={styles.emergencyRow}>
      <View style={styles.emergencyRowLabelWrap}>
        <View style={[styles.emergencyDot, { backgroundColor: accentColor }]} />
        <Text style={styles.emergencyRowLabel}>{label}</Text>
      </View>
      <TextInput
        style={styles.emergencyInput}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor={colors.textTertiary}
        keyboardType="phone-pad"
        autoCorrect={false}
        maxLength={20}
      />
    </View>
  );
}

function InfoRow({ label, value }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

function StatusBadge({ label, connected }) {
  return (
    <View style={styles.settingRow}>
      <Text style={styles.settingLabel}>{label}</Text>
      <View
        style={[
          styles.statusBadge,
          connected ? styles.statusBadgeConnected : styles.statusBadgeDisconnected,
        ]}
      >
        <View
          style={[
            styles.statusDot,
            { backgroundColor: connected ? colors.green : colors.textTertiary },
          ]}
        />
        <Text
          style={[
            styles.statusBadgeText,
            { color: connected ? colors.green : colors.textTertiary },
          ]}
        >
          {connected ? 'Conectat' : 'Neconectat'}
        </Text>
      </View>
    </View>
  );
}

function AdminRow({ label, badgeCount, onPress }) {
  return (
    <TouchableOpacity
      style={styles.adminRow}
      activeOpacity={0.7}
      onPress={onPress}
    >
      <Text style={styles.settingLabel}>{label}</Text>
      <View style={styles.adminRowRight}>
        {badgeCount != null && (
          <View style={styles.countBadge}>
            <Text style={styles.countBadgeText}>{badgeCount}</Text>
          </View>
        )}
        <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
          <Path
            d="M9 18l6-6-6-6"
            stroke={colors.textTertiary}
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </Svg>
      </View>
    </TouchableOpacity>
  );
}

export default function SettingsScreen({ onShowGateManager, onShowStaffAssignment, appVersion }) {
  const { user, userRole, isTeamMember, isVenueOwner, venueOwner, logout } = useAuth();
  const { selectedEvent } = useEvent();
  const {
    vibrationFeedback,
    soundEffects,
    autoConfirmValid,
    offlineMode,
    toggleVibration,
    toggleSound,
    toggleAutoConfirm,
    toggleOfflineMode,
    cachedTickets,
    isDownloadingOffline,
    ensureOfflineData,
    emergencyContacts,
    updateEmergencyContact,
  } = useApp();

  // Auto-download offline data when event is selected and offline mode is already on
  useEffect(() => {
    if (selectedEvent?.id && offlineMode) {
      ensureOfflineData(selectedEvent.id);
    }
  }, [selectedEvent?.id, offlineMode]);

  // Live gate count for the "Administrare Porți" badge
  const [gateCount, setGateCount] = useState(null);
  useEffect(() => {
    let cancelled = false;
    const venueId = selectedEvent?.venue_id;
    if (!venueId) {
      setGateCount(null);
      return;
    }
    (async () => {
      try {
        const response = await getVenueGates(venueId);
        const gates = response?.data?.gates || response?.gates || [];
        if (!cancelled) setGateCount(Array.isArray(gates) ? gates.length : 0);
      } catch (e) {
        if (!cancelled) setGateCount(null);
      }
    })();
    return () => { cancelled = true; };
  }, [selectedEvent?.venue_id]);

  // POS Card prin NFC — local mirror so the toggle UI is responsive.
  // Backend lives at PUT /organizer/mobile-settings; the value is read
  // from user.mobile_settings.card_nfc_enabled which /me populates.
  const [nfcEnabled, setNfcEnabled] = useState(
    !!user?.mobile_settings?.card_nfc_enabled
  );
  const [nfcSaving, setNfcSaving] = useState(false);

  useEffect(() => {
    setNfcEnabled(!!user?.mobile_settings?.card_nfc_enabled);
  }, [user?.mobile_settings?.card_nfc_enabled]);

  const handleToggleNfc = useCallback(async () => {
    if (nfcSaving) return;
    const next = !nfcEnabled;
    setNfcEnabled(next);
    setNfcSaving(true);
    try {
      await apiPut('/organizer/mobile-settings', { card_nfc_enabled: next });
      // SalesScreen reads from user.mobile_settings directly — for the
      // change to propagate immediately the AuthContext would need a
      // refetch hook. Operator can also just reopen the Sales tab.
    } catch (e) {
      setNfcEnabled(!next); // rollback
    }
    setNfcSaving(false);
  }, [nfcEnabled, nfcSaving]);

  const staffName = isVenueOwner
    ? (venueOwner?.name || venueOwner?.tenant?.public_name || 'Venue owner')
    : (isTeamMember ? (user?.team_member?.name || 'Membru Echipă') : (user?.name || user?.public_name || 'Organizator'));
  const staffRole = isVenueOwner
    ? 'venue owner'
    : (isTeamMember ? (user?.team_member?.role || 'staff') : 'owner');
  const isAdmin = !isVenueOwner && (userRole === 'admin' || userRole === 'owner');
  const assignedGate = user?.assigned_gate || '--';
  const venueName = venueOwner?.venues?.[0]?.name || venueOwner?.tenant?.public_name || '—';

  const handleEndShift = async () => {
    await logout();
  };

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.contentContainer}
      showsVerticalScrollIndicator={false}
    >
      {/* Header title moved into the shared app header. Small top gap
          keeps the first section card visually separated from the header. */}
      <View style={styles.topSpacer} />

      {/* Account Section */}
      <SectionHeader title="Cont" />
      <View style={styles.sectionCard}>
        <InfoRow label="Nume" value={staffName} />
        <View style={styles.divider} />
        <InfoRow label="Rol" value={staffRole.charAt(0).toUpperCase() + staffRole.slice(1)} />
        <View style={styles.divider} />
        {isVenueOwner ? (
          <InfoRow label="Nume Venue" value={venueName} />
        ) : (
          <InfoRow label="Poartă Asignată" value={assignedGate} />
        )}
      </View>

      {/* Scanner Section */}
      <SectionHeader title="Scanner" />
      <View style={styles.sectionCard}>
        <SettingRow
          label="Vibrație"
          right={<Toggle value={vibrationFeedback} onPress={toggleVibration} />}
        />
        <View style={styles.divider} />
        <SettingRow
          label="Efecte Sonore"
          right={<Toggle value={soundEffects} onPress={toggleSound} />}
        />
        {!isVenueOwner && (
          <>
            <View style={styles.divider} />
            <SettingRow
              label="Auto-confirmare Valide"
              right={<Toggle value={autoConfirmValid} onPress={toggleAutoConfirm} />}
            />
          </>
        )}
      </View>

      {/* Vânzare POS — admin / owner only (the operator who decides
          whether the legacy Stripe Tap button shows up in Sales). */}
      {isAdmin && (
        <>
          <SectionHeader title="Vânzare POS" />
          <View style={styles.sectionCard}>
            <SettingRow
              label="Card prin NFC (Stripe Tap)"
              right={<Toggle value={nfcEnabled} onPress={handleToggleNfc} />}
            />
            <View style={styles.divider} />
            <View style={styles.offlineInfoBox}>
              <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                <Path
                  d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4M12 8h.01"
                  stroke={colors.cyan}
                  strokeWidth={2}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </Svg>
              <Text style={styles.offlineInfoText}>
                Adaugă în ecranul Vânzare un buton „Card prin NFC" (plată Stripe Tap). Dezactivat = doar Numerar și Card POS.
              </Text>
            </View>
          </View>
        </>
      )}

      {/* Offline Mode Section (not relevant for venue owners — no check-in flow) */}
      {!isVenueOwner && (
        <>
          <SectionHeader title="Mod Offline" />
          <View style={styles.sectionCard}>
            <SettingRow
              label="Activează Modul Offline"
              right={<Toggle value={offlineMode} onPress={() => toggleOfflineMode(selectedEvent?.id)} />}
            />
            <View style={styles.divider} />
            <View style={styles.offlineInfoBox}>
              <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                <Path
                  d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4M12 8h.01"
                  stroke={colors.cyan}
                  strokeWidth={2}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </Svg>
              <Text style={styles.offlineInfoText}>
                {isDownloadingOffline ? 'Se descarcă biletele...' : `${cachedTickets} bilete salvate pentru scanare offline`}
              </Text>
            </View>
          </View>
        </>
      )}

      {/* Hardware Section (scanner-only personnel) */}
      {!isVenueOwner && (
        <>
          <SectionHeader title="Hardware" />
          <View style={styles.sectionCard}>
            <StatusBadge label="Cititor Card" connected={false} />
            <View style={styles.divider} />
            <StatusBadge label="Imprimantă Bon" connected={false} />
          </View>
        </>
      )}

      {/* Aspect — theme picker. Currently persists preference and applies on
          next cold restart (StyleSheets are frozen at module load time).
          Alert nudges the operator to reopen the app to see the change. */}
      <SectionHeader title="Aspect" />
      <View style={styles.sectionCard}>
        <ThemePicker />
      </View>

      {/* Contact Urgențe — numere apelate de butoanele din panoul de Notificări. */}
      <SectionHeader title="Contact Urgențe" />
      <View style={styles.sectionCard}>
        <EmergencyContactRow
          label="Urgență Medicală"
          placeholder="ex. 0722 000 000"
          value={emergencyContacts?.medical || ''}
          onChangeText={(v) => updateEmergencyContact('medical', v)}
          accentColor={colors.danger}
        />
        <View style={styles.divider} />
        <EmergencyContactRow
          label="Problemă Tehnică"
          placeholder="ex. 0733 000 000"
          value={emergencyContacts?.tehnica || ''}
          onChangeText={(v) => updateEmergencyContact('tehnica', v)}
          accentColor={colors.amber}
        />
        <View style={styles.divider} />
        <EmergencyContactRow
          label="Alertă Pază"
          placeholder="ex. 0744 000 000"
          value={emergencyContacts?.paza || ''}
          onChangeText={(v) => updateEmergencyContact('paza', v)}
          accentColor={colors.cyan}
        />
      </View>

      {/* Admin Controls (only for admin/owner organizer role — never venue owner) */}
      {isAdmin && (
        <>
          <SectionHeader title="Comenzi Admin" />
          <View style={styles.sectionCard}>
            {/* Admin badge */}
            <View style={styles.adminBadge}>
              <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                <Path
                  d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                  stroke={colors.purple}
                  strokeWidth={2}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </Svg>
              <Text style={styles.adminBadgeText}>Acces Administrator</Text>
            </View>
            <View style={styles.divider} />
            <AdminRow
              label="Administrare Porți"
              badgeCount={gateCount}
              onPress={() => onShowGateManager?.()}
            />
            <View style={styles.divider} />
            <AdminRow
              label="Asignare Personal"
              onPress={() => onShowStaffAssignment?.()}
            />
          </View>
        </>
      )}

      {/* Logout */}
      <TouchableOpacity
        style={styles.logoutButton}
        activeOpacity={0.7}
        onPress={handleEndShift}
      >
        <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
          <Path
            d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"
            stroke={colors.red}
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </Svg>
        <Text style={styles.logoutButtonText}>
          {isVenueOwner ? 'Deconectare' : 'Încheie Tura & Deconectare'}
        </Text>
      </TouchableOpacity>

      {/* App Version */}
      <Text style={styles.versionText}>AmBilet Scan v{appVersion || '2.0.7'}</Text>

      <View style={styles.bottomSpacer} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  contentContainer: {
    paddingHorizontal: 16,
    paddingBottom: 100,
  },

  // Header
  header: {
    paddingTop: 16,
    paddingBottom: 20,
  },
  topSpacer: {
    height: 12,
  },
  headerTitle: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.3,
  },

  // Sections
  sectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginTop: 24,
    marginBottom: 10,
    marginLeft: 4,
  },
  sectionCard: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 16,
    padding: 16,
  },
  divider: {
    height: 1,
    backgroundColor: colors.border,
    marginVertical: 12,
  },

  // Info Row (Account)
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  infoLabel: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  infoValue: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },

  // Setting Row
  settingRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  settingRowLeft: {
    flex: 1,
    marginRight: 12,
  },
  settingLabel: {
    fontSize: 15,
    fontWeight: '500',
    color: colors.textPrimary,
  },
  settingDescription: {
    fontSize: 12,
    color: colors.textTertiary,
    marginTop: 2,
  },

  // Theme picker
  themeOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  themeOptionActive: {
    borderColor: colors.purple,
    backgroundColor: colors.purpleBg,
  },
  themeRadio: {
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 2,
    borderColor: colors.purple,
    alignItems: 'center',
    justifyContent: 'center',
  },
  themeRadioDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.purple,
  },
  themeOptionLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  themeOptionDescription: {
    fontSize: 11,
    color: colors.textTertiary,
    marginTop: 2,
  },

  // Emergency contact row
  emergencyRow: {
    gap: 8,
  },
  emergencyRowLabelWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  emergencyDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  emergencyRowLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  emergencyInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    color: colors.textPrimary,
    backgroundColor: colors.surface,
  },

  // Toggle
  toggleTrack: {
    width: 48,
    height: 28,
    borderRadius: 14,
    justifyContent: 'center',
  },
  toggleTrackOn: {
    backgroundColor: colors.purple,
  },
  toggleTrackOff: {
    backgroundColor: 'rgba(20,10,10,0.15)',
  },
  toggleThumb: {
    width: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: colors.white,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 3,
    elevation: 3,
  },

  // Offline Info Box
  offlineInfoBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: colors.cyanBg,
    borderWidth: 1,
    borderColor: colors.cyanBorder,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  offlineInfoText: {
    flex: 1,
    fontSize: 13,
    color: colors.cyan,
    fontWeight: '500',
  },

  // Hardware Status Badge
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
  },
  statusBadgeConnected: {
    backgroundColor: colors.greenLight,
    borderWidth: 1,
    borderColor: colors.greenBorder,
  },
  statusBadgeDisconnected: {
    backgroundColor: 'rgba(20,10,10,0.05)',
    borderWidth: 1,
    borderColor: colors.border,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  statusBadgeText: {
    fontSize: 12,
    fontWeight: '600',
  },

  // Admin Controls
  adminBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  adminBadgeText: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.purple,
  },
  adminRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  adminRowRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  countBadge: {
    backgroundColor: colors.purpleLight,
    borderRadius: 10,
    minWidth: 24,
    height: 24,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 8,
  },
  countBadgeText: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.purple,
  },

  // Logout Button
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    borderWidth: 1.5,
    borderColor: colors.redBorder,
    backgroundColor: colors.redBg,
    borderRadius: 14,
    paddingVertical: 16,
    marginTop: 32,
  },
  logoutButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.red,
  },

  versionText: {
    fontSize: 12,
    color: colors.textTertiary,
    textAlign: 'center',
    marginTop: 24,
  },

  bottomSpacer: {
    height: 20,
  },
});
