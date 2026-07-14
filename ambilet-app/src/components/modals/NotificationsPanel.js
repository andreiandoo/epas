import React from 'react';
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
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { useApp } from '../../context/AppContext';

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

function NotificationItem({ notification }) {
  const isUnread = notification.unread;

  return (
    <View style={[styles.notifItem, isUnread && styles.notifItemUnread]}>
      <NotificationIcon type={notification.type} />
      <View style={styles.notifContent}>
        <Text style={[styles.notifMessage, isUnread && styles.notifMessageUnread]} numberOfLines={2}>
          {notification.message}
        </Text>
        <Text style={styles.notifTime}>{notification.time}</Text>
      </View>
      {isUnread && <View style={styles.unreadDot} />}
    </View>
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

export default function NotificationsPanel({ visible, onClose, notifications = [], onMarkAllRead }) {
  const unreadCount = notifications.filter(n => n.unread).length;
  const { emergencyContacts } = useApp();

  const callEmergency = (label, phone) => {
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
    >
      <TouchableOpacity
        style={styles.overlay}
        onPress={onClose}
        activeOpacity={1}
      >
        <View style={styles.panel}>
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
                />
              ))
            )}
          </ScrollView>

          {/* Raportează Problemă — one-tap dial for the three configured contacts.
              Numbers live in Settings → Contact Urgențe (stored locally). */}
          <View style={styles.emergencySection}>
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
              <Text style={styles.emergencyHeaderText}>Raportează Problemă</Text>
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
        </View>
      </TouchableOpacity>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(20,10,10,0.15)',
  },
  panel: {
    position: 'absolute',
    top: 100,
    right: 12,
    width: SCREEN_WIDTH * 0.9,
    maxWidth: 400,
    maxHeight: 620,
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
    flexGrow: 0,
    maxHeight: 340,
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
