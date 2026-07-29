import React, { useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  Dimensions,
  Linking,
  Alert,
  Platform,
  PermissionsAndroid,
  Image,
  Pressable,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../../theme/colors';
import { useApp } from '../../context/AppContext';
import EmergencyReporter from '../EmergencyReporter';

// expo-av lazy — for the mini audio player attached to voice-note
// notifications. Silent no-op if module isn't linked.
let AudioLib = null;
try { AudioLib = require('expo-av').Audio; } catch {}

// Direct-dial helper — bypasses the Android intent chooser so an emergency
// button places the call in one tap. iOS falls through to Linking(tel:)
// because Apple always confirms the call with the user (no bypass).
let IntentLauncher = null;
try {
  IntentLauncher = require('expo-intent-launcher');
} catch (e) {
  IntentLauncher = null;
}

const { width: SCREEN_WIDTH } = Dimensions.get('window');

function NotificationIcon({ type }) {
  switch (type) {
    case 'alert':
      return (
        <View style={[styles.notifIcon, styles.notifIconAlert]}>
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path
              d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"
              stroke={colors.red}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
        </View>
      );
    case 'success':
      return (
        <View style={[styles.notifIcon, styles.notifIconSuccess]}>
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              stroke={colors.green}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
        </View>
      );
    case 'info':
    default:
      return (
        <View style={[styles.notifIcon, styles.notifIconInfo]}>
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path
              d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4M12 8h.01"
              stroke={colors.cyan}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
        </View>
      );
  }
}

// Mini audio player — play/pause a local m4a URI (or remote URL).
// Managed state (loading, playing) so the button label reflects reality.
//
// Loop-prevention: expo-av can re-fire play if you setPositionAsync while
// the sound is still in a "just finished" state. We explicitly pause +
// unload after each play so the next tap re-creates the Sound fresh.
function MiniAudioPlayer({ uri, duration }) {
  const [state, setState] = useState('idle'); // 'idle' | 'loading' | 'playing'
  const soundRef = useRef(null);

  const teardown = async () => {
    const s = soundRef.current;
    soundRef.current = null;
    if (!s) return;
    try { await s.stopAsync(); } catch {}
    try { await s.unloadAsync(); } catch {}
  };

  useEffect(() => () => { teardown(); }, []);

  const toggle = async () => {
    if (!AudioLib) {
      Alert.alert('Modul audio lipsă', 'Rebuild aplicația cu ultimele dependințe.');
      return;
    }
    if (state === 'playing') {
      // Explicit user pause — stop + unload so a subsequent tap starts fresh.
      await teardown();
      setState('idle');
      return;
    }
    setState('loading');
    try {
      await teardown();
      const { sound } = await AudioLib.Sound.createAsync(
        { uri },
        { shouldPlay: false, isLooping: false }
      );
      sound.setOnPlaybackStatusUpdate((status) => {
        if (status?.didJustFinish) {
          // Fully unload on finish — prevents expo-av's replay-after-seek
          // race that made the note loop forever until the modal closed.
          teardown();
          setState('idle');
        }
      });
      soundRef.current = sound;
      await sound.playAsync();
      setState('playing');
    } catch {
      await teardown();
      setState('idle');
      Alert.alert('Eroare', 'Nu am putut porni redarea.');
    }
  };

  return (
    <TouchableOpacity style={styles.audioPlayer} onPress={toggle} activeOpacity={0.7}>
      <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
        {state === 'playing' ? (
          <Path d="M6 4h4v16H6zM14 4h4v16h-4z" fill={colors.purple} />
        ) : (
          <Path d="M8 5v14l11-7-11-7z" fill={colors.purple} />
        )}
      </Svg>
      <Text style={styles.audioPlayerText}>
        {state === 'playing' ? 'Pauză' : state === 'loading' ? 'Se încarcă…' : 'Redă'}
        {duration ? ` · ${duration}s` : ''}
      </Text>
    </TouchableOpacity>
  );
}

function NotificationItem({ notification, onOpenPhoto, onMarkOneRead }) {
  const isUnread = notification.unread;
  const hasPhoto = !!(notification.photo_uri || notification.photo_url);
  const hasAudio = !!(notification.audio_uri || notification.audio_url);
  const photoUri = notification.photo_uri || notification.photo_url;

  const handleRowPress = () => {
    if (isUnread && notification.id != null) onMarkOneRead?.(notification.id);
  };

  return (
    <TouchableOpacity
      style={[styles.notifItem, isUnread && styles.notifItemUnread]}
      onPress={handleRowPress}
      activeOpacity={0.7}
    >
      <NotificationIcon type={notification.type} />
      <View style={styles.notifContent}>
        <Text style={[styles.notifMessage, isUnread && styles.notifMessageUnread]} numberOfLines={2}>
          {notification.message}
        </Text>
        {(hasPhoto || hasAudio) && (
          <View style={styles.notifMediaRow}>
            {hasPhoto && (
              <TouchableOpacity
                onPress={() => onOpenPhoto?.(photoUri)}
                activeOpacity={0.7}
              >
                <Image
                  source={{ uri: photoUri }}
                  style={styles.notifThumb}
                  resizeMode="cover"
                />
                <View style={styles.notifThumbHint}>
                  <Svg width={12} height={12} viewBox="0 0 24 24" fill="none">
                    <Path
                      d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"
                      stroke={colors.white}
                      strokeWidth={2}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </Svg>
                </View>
              </TouchableOpacity>
            )}
            {hasAudio && (
              <MiniAudioPlayer
                uri={notification.audio_uri || notification.audio_url}
                duration={notification.audio_duration}
              />
            )}
          </View>
        )}
        <Text style={styles.notifTime}>{notification.time}</Text>
      </View>
      {isUnread && <View style={styles.unreadDot} />}
    </TouchableOpacity>
  );
}

// Full-screen tap-to-close photo viewer for notification attachments.
// Renders on top of the notifications panel so admins can inspect the
// photo without leaving the modal flow.
function PhotoViewerOverlay({ uri, onClose }) {
  if (!uri) return null;
  return (
    <TouchableOpacity
      style={styles.photoViewerOverlay}
      onPress={onClose}
      activeOpacity={1}
    >
      <Image source={{ uri }} style={styles.photoViewerImage} resizeMode="contain" />
      <TouchableOpacity style={styles.photoViewerClose} onPress={onClose} activeOpacity={0.7}>
        <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
          <Path
            d="M18 6L6 18M6 6l12 12"
            stroke={colors.white}
            strokeWidth={2.4}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </Svg>
      </TouchableOpacity>
    </TouchableOpacity>
  );
}

function EmergencyButton({ label, phone, bg, border, color, iconPath, onPressCall }) {
  const disabled = !phone;
  return (
    <TouchableOpacity
      style={[
        styles.emergencyBtn,
        { backgroundColor: bg, borderColor: border },
        disabled && styles.emergencyBtnDisabled,
      ]}
      onPress={() => onPressCall(label, phone)}
      activeOpacity={0.7}
    >
      <Svg width={22} height={22} viewBox="0 0 24 24" fill="none">
        <Path d={iconPath} stroke={color} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
      </Svg>
      <Text style={[styles.emergencyBtnLabel, { color }]} numberOfLines={2}>{label}</Text>
      <Text style={styles.emergencyBtnPhone} numberOfLines={1}>
        {phone || 'Nesetat'}
      </Text>
    </TouchableOpacity>
  );
}

export default function NotificationsPanel({ visible, onClose, notifications = [], onMarkAllRead, onMarkOneRead }) {
  const unreadCount = notifications.filter(n => n.unread).length;
  const { emergencyContacts } = useApp();
  const insets = useSafeAreaInsets();
  const bottomOffset = Math.max(insets.bottom, 8) + 64 + 12;
  const [viewingPhoto, setViewingPhoto] = useState(null);

  // Reset the photo viewer whenever the whole panel is closed so we don't
  // silently pop the viewer back open next time the operator opens the bell.
  useEffect(() => {
    if (!visible) setViewingPhoto(null);
  }, [visible]);

  const callEmergency = async (label, phone) => {
    if (!phone) {
      Alert.alert(
        'Număr nesetat',
        `Adaugă un număr pentru „${label}" din Setări → Contact Urgențe.`
      );
      return;
    }
    const cleaned = String(phone).replace(/[^0-9+]/g, '');
    if (!cleaned) {
      Alert.alert('Număr invalid', `„${phone}" nu poate fi apelat.`);
      return;
    }

    // Android: request CALL_PHONE and fire ACTION_CALL so the call starts
    // instantly with no app chooser and no dialer confirmation.
    if (Platform.OS === 'android' && IntentLauncher) {
      try {
        const granted = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.CALL_PHONE,
          {
            title: 'Apel de urgență',
            message: `AmBilet trebuie să apeleze direct ${label} în caz de urgență.`,
            buttonPositive: 'Permite',
            buttonNegative: 'Refuză',
          }
        );
        if (granted === PermissionsAndroid.RESULTS.GRANTED) {
          await IntentLauncher.startActivityAsync('android.intent.action.CALL', {
            data: `tel:${cleaned}`,
          });
          return;
        }
      } catch (e) {
        // fall through to Linking dialer
      }
    }

    // Fallback: opens dialer pre-filled (iOS always, Android when perm denied)
    Linking.openURL(`tel:${cleaned}`).catch(() => {
      Alert.alert('Eroare', 'Nu am putut porni apelul telefonic.');
    });
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent
    >
      {/* Backdrop + panel are SIBLINGS (not parent/child) so taps inside
          the panel — including ScrollView drag gestures — never propagate
          to the backdrop's onPress. Previously the backdrop wrapped the
          panel via a TouchableOpacity and captured the drag start, so the
          list couldn't scroll and stray taps closed the panel. */}
      <View style={styles.root} pointerEvents="box-none">
        <Pressable style={styles.overlay} onPress={onClose} />
        <View style={[styles.panel, { bottom: bottomOffset }]}>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.headerLeft}>
              <Text style={styles.title}>Notificări</Text>
              {unreadCount > 0 && (
                <View style={styles.unreadBadge}>
                  <Text style={styles.unreadBadgeText}>{unreadCount}</Text>
                </View>
              )}
            </View>
            {unreadCount > 0 && (
              <TouchableOpacity
                onPress={onMarkAllRead}
                activeOpacity={0.7}
                style={styles.markAllButton}
              >
                <Text style={styles.markAllText}>Marchează toate citite</Text>
              </TouchableOpacity>
            )}
          </View>

          {/* Notification List */}
          <ScrollView
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
          >
            {notifications.length === 0 ? (
              <View style={styles.emptyState}>
                <Svg width={36} height={36} viewBox="0 0 24 24" fill="none">
                  <Path
                    d="M18 8A6 6 0 106 8c0 7-3 9-3 9h18s-3-2-3-9zM13.73 21a2 2 0 01-3.46 0"
                    stroke={colors.textTertiary}
                    strokeWidth={1.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </Svg>
                <Text style={styles.emptyText}>Nicio notificare</Text>
              </View>
            ) : (
              notifications.map((notification, index) => (
                <NotificationItem
                  key={notification.id || index}
                  notification={notification}
                  onOpenPhoto={setViewingPhoto}
                  onMarkOneRead={onMarkOneRead}
                />
              ))
            )}
          </ScrollView>

          {/* Two-part bottom bar:
              1) SUNĂ — apel telefonic direct la numerele configurate în
                 Settings → Contact Urgențe (pentru 112 / pază / tehnic)
              2) ALERTĂ ÎN APLICAȚIE — trimite raport (opțional cu foto +
                 notă vocală) către toți admin/proprietari — accesibil
                 fără tură activă */}
          <View style={styles.emergencySection}>
            <View style={styles.emergencyHeader}>
              <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                <Path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.37 1.9.72 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.35 1.85.59 2.81.72a2 2 0 011.72 2z"
                  stroke={colors.danger} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
              </Svg>
              <Text style={styles.emergencyHeaderText}>Sună la Contact Urgență</Text>
            </View>
            <View style={styles.emergencyGrid}>
              <EmergencyButton
                label="Urgență Medicală"
                phone={emergencyContacts?.medical}
                bg={colors.dangerBg}
                border={colors.dangerBorder}
                color={colors.danger}
                iconPath="M12 8v4m0 4h.01M4.93 4.93l14.14 14.14M12 2a10 10 0 100 20 10 10 0 000-20z"
                onPressCall={callEmergency}
              />
              <EmergencyButton
                label="Problemă Tehnică"
                phone={emergencyContacts?.tehnica}
                bg={colors.amberBg}
                border={colors.amberBorder}
                color={colors.amber}
                iconPath="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"
                onPressCall={callEmergency}
              />
              <EmergencyButton
                label="Alertă Pază"
                phone={emergencyContacts?.paza}
                bg={colors.cyanBg}
                border={colors.cyanBorder}
                color={colors.cyan}
                iconPath="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                onPressCall={callEmergency}
              />
            </View>
          </View>

          <View style={styles.reporterSection}>
            <View style={styles.emergencyHeader}>
              <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                <Path
                  d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01"
                  stroke={colors.danger}
                  strokeWidth={2}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </Svg>
              <Text style={styles.emergencyHeaderText}>Alertă în aplicație (către admini)</Text>
            </View>
            <EmergencyReporter compact />
          </View>
        </View>
      </View>

      {/* Full-screen photo viewer — mounted at the Modal root so it sits
          above the notifications panel and its own tap-to-close doesn't
          bubble into the notification list. */}
      <PhotoViewerOverlay uri={viewingPhoto} onClose={() => setViewingPhoto(null)} />
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  overlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(20,10,10,0.15)',
  },
  panel: {
    position: 'absolute',
    top: 100,
    right: 12,
    width: SCREEN_WIDTH * 0.9,
    maxWidth: 400,
    backgroundColor: colors.surface,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
    shadowColor: '#140A0A',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.08,
    shadowRadius: 20,
    elevation: 8,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.2,
  },
  unreadBadge: {
    backgroundColor: colors.redAccent,
    borderRadius: 10,
    minWidth: 20,
    height: 20,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  unreadBadgeText: {
    color: colors.white,
    fontSize: 10,
    fontWeight: '700',
  },
  markAllButton: {
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  markAllText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.purple,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingVertical: 4,
  },
  notifItem: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  notifItemUnread: {
    backgroundColor: colors.purpleBg,
  },
  notifIcon: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
  },
  notifIconAlert: {
    backgroundColor: colors.redBg,
    borderWidth: 1,
    borderColor: colors.redBorder,
  },
  notifIconSuccess: {
    backgroundColor: colors.greenLight,
    borderWidth: 1,
    borderColor: colors.greenBorder,
  },
  notifIconInfo: {
    backgroundColor: colors.cyanBg,
    borderWidth: 1,
    borderColor: colors.cyanBorder,
  },
  notifContent: {
    flex: 1,
  },
  notifMessage: {
    fontSize: 13,
    color: colors.textSecondary,
    lineHeight: 18,
    marginBottom: 4,
  },
  notifMessageUnread: {
    color: colors.textPrimary,
    fontWeight: '500',
  },
  notifTime: {
    fontSize: 11,
    color: colors.textTertiary,
  },
  notifMediaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 6,
    marginBottom: 4,
    flexWrap: 'wrap',
  },
  notifThumb: {
    width: 56,
    height: 56,
    borderRadius: 8,
    backgroundColor: colors.border,
  },
  notifThumbHint: {
    position: 'absolute',
    right: 2,
    bottom: 2,
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: 'rgba(20,10,10,0.65)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  photoViewerOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.92)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 100,
  },
  photoViewerImage: {
    width: '100%',
    height: '100%',
  },
  photoViewerClose: {
    position: 'absolute',
    top: 40,
    right: 20,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  audioPlayer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    backgroundColor: colors.purpleBg,
  },
  audioPlayerText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.purple,
  },
  reporterSection: {
    borderTopWidth: 1,
    borderTopColor: colors.border,
    paddingHorizontal: 12,
    paddingTop: 12,
    paddingBottom: 14,
    gap: 8,
  },
  unreadDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.purple,
    marginTop: 6,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
    gap: 10,
  },
  emptyText: {
    fontSize: 13,
    color: colors.textTertiary,
  },

  // Raportează Problemă
  emergencySection: {
    borderTopWidth: 1,
    borderTopColor: colors.border,
    paddingHorizontal: 12,
    paddingVertical: 12,
    gap: 10,
  },
  emergencyHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  emergencyHeaderText: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  emergencyGrid: {
    flexDirection: 'row',
    gap: 8,
  },
  emergencyBtn: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 8,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    minHeight: 84,
  },
  emergencyBtnDisabled: {
    opacity: 0.55,
  },
  emergencyBtnLabel: {
    fontSize: 11,
    fontWeight: '700',
    textAlign: 'center',
    letterSpacing: 0.1,
  },
  emergencyBtnPhone: {
    fontSize: 10,
    color: colors.textTertiary,
    marginTop: 2,
  },
});
