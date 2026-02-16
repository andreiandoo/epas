import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  TextInput,
  Dimensions,
  ActivityIndicator,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { useEvent } from '../../context/EventContext';
import { getParticipants } from '../../api/participants';
import { checkinByBarcode } from '../../api/participants';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

function getInitials(name) {
  if (!name) return '??';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function TypeBadge({ type }) {
  const typeConfigs = {
    VIP: { color: colors.purple, bg: colors.purpleLight, border: colors.purpleBorder },
    Artist: { color: colors.amber, bg: colors.amberLight, border: colors.amberBorder },
    Press: { color: colors.cyan, bg: colors.cyanLight, border: colors.cyanBorder },
    Guest: { color: colors.textSecondary, bg: 'rgba(255,255,255,0.05)', border: 'rgba(255,255,255,0.08)' },
  };
  const config = typeConfigs[type] || typeConfigs.Guest;

  return (
    <View style={[styles.typeBadge, { backgroundColor: config.bg, borderColor: config.border }]}>
      <Text style={[styles.typeBadgeText, { color: config.color }]}>{type}</Text>
    </View>
  );
}

function GuestItem({ guest, onCheckIn, isCheckingIn }) {
  const isChecked = guest.checkedIn;

  return (
    <View style={[styles.guestItem, isChecked && styles.guestItemChecked]}>
      {/* Avatar */}
      <View style={[styles.avatar, isChecked && styles.avatarChecked]}>
        <Text style={[styles.avatarText, isChecked && styles.avatarTextChecked]}>
          {getInitials(guest.name)}
        </Text>
      </View>

      {/* Info */}
      <View style={styles.guestInfo}>
        <Text style={[styles.guestName, isChecked && styles.guestNameChecked]}>{guest.name}</Text>
        <TypeBadge type={guest.type} />
      </View>

      {/* Check-in button */}
      {isChecked ? (
        <View style={styles.checkedButton}>
          <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
            <Path
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              stroke={colors.green}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
        </View>
      ) : (
        <TouchableOpacity
          style={styles.checkInButton}
          onPress={() => onCheckIn(guest)}
          activeOpacity={0.7}
          disabled={isCheckingIn}
        >
          {isCheckingIn ? (
            <ActivityIndicator size="small" color={colors.purple} />
          ) : (
            <Text style={styles.checkInButtonText}>Check In</Text>
          )}
        </TouchableOpacity>
      )}
    </View>
  );
}

export default function GuestListModal({ visible, onClose }) {
  const { selectedEvent } = useEvent();
  const [searchQuery, setSearchQuery] = useState('');
  const [guests, setGuests] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [checkingInId, setCheckingInId] = useState(null);

  // Fetch participants when modal opens
  useEffect(() => {
    if (visible && selectedEvent) {
      fetchGuests();
    }
  }, [visible, selectedEvent?.id]);

  const fetchGuests = async () => {
    if (!selectedEvent) return;
    setIsLoading(true);
    try {
      const response = await getParticipants(selectedEvent.id, { per_page: 200 });
      const participants = response.data || [];
      const mapped = participants.map(p => ({
        id: p.id || String(Math.random()),
        name: p.full_name || p.name || `${p.first_name || ''} ${p.last_name || ''}`.trim() || 'Unknown',
        type: p.ticket_type_name || p.ticket_type || 'Guest',
        checkedIn: !!p.checked_in_at || !!p.checked_in || p.status === 'checked_in',
        barcode: p.barcode || p.ticket_code || p.code || '',
        checkedInAt: p.checked_in_at || null,
      }));
      setGuests(mapped);
    } catch (e) {
      console.error('Failed to fetch guests:', e);
    }
    setIsLoading(false);
  };

  const filteredGuests = guests.filter(guest =>
    guest.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    guest.type.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleCheckIn = async (guest) => {
    if (!selectedEvent || !guest.barcode) return;
    setCheckingInId(guest.id);
    try {
      await checkinByBarcode(selectedEvent.id, guest.barcode);
      // Update local state on success
      setGuests(prev =>
        prev.map(g => g.id === guest.id ? { ...g, checkedIn: true } : g)
      );
    } catch (e) {
      console.error('Check-in failed:', e);
      // If already checked in, mark as such
      if (e.message?.toLowerCase().includes('already')) {
        setGuests(prev =>
          prev.map(g => g.id === guest.id ? { ...g, checkedIn: true } : g)
        );
      }
    }
    setCheckingInId(null);
  };

  const handleClose = () => {
    setSearchQuery('');
    if (onClose) {
      onClose();
    }
  };

  const checkedCount = guests.filter(g => g.checkedIn).length;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={handleClose}
    >
      <View style={styles.overlay}>
        <TouchableOpacity style={styles.overlayTouchable} onPress={handleClose} activeOpacity={1} />
        <View style={styles.sheet}>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.handle} />
            <View style={styles.headerRow}>
              <View style={styles.titleRow}>
                <Text style={styles.title}>Guest List</Text>
                <View style={styles.countBadge}>
                  <Text style={styles.countBadgeText}>{checkedCount}/{guests.length}</Text>
                </View>
              </View>
              <TouchableOpacity onPress={handleClose} style={styles.closeButton} activeOpacity={0.7}>
                <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
                  <Path
                    d="M18 6L6 18M6 6l12 12"
                    stroke={colors.textSecondary}
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </Svg>
              </TouchableOpacity>
            </View>
          </View>

          {/* Search Bar */}
          <View style={styles.searchContainer}>
            <View style={styles.searchWrapper}>
              <Svg width={18} height={18} viewBox="0 0 24 24" fill="none" style={styles.searchIcon}>
                <Path
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                  stroke={colors.textTertiary}
                  strokeWidth={1.5}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </Svg>
              <TextInput
                style={styles.searchInput}
                placeholder="Search guests..."
                placeholderTextColor={colors.textQuaternary}
                value={searchQuery}
                onChangeText={setSearchQuery}
                autoCorrect={false}
              />
              {searchQuery.length > 0 && (
                <TouchableOpacity onPress={() => setSearchQuery('')} activeOpacity={0.7}>
                  <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                    <Path
                      d="M18 6L6 18M6 6l12 12"
                      stroke={colors.textTertiary}
                      strokeWidth={2}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </Svg>
                </TouchableOpacity>
              )}
            </View>
          </View>

          {/* Guest List */}
          <ScrollView
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
          >
            {isLoading ? (
              <View style={styles.emptyState}>
                <ActivityIndicator size="large" color={colors.purple} />
                <Text style={styles.emptyText}>Loading guests...</Text>
              </View>
            ) : filteredGuests.length === 0 ? (
              <View style={styles.emptyState}>
                <Svg width={48} height={48} viewBox="0 0 24 24" fill="none">
                  <Path
                    d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z"
                    stroke={colors.textTertiary}
                    strokeWidth={1.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </Svg>
                <Text style={styles.emptyText}>
                  {searchQuery ? 'No guests found' : 'No participants yet'}
                </Text>
              </View>
            ) : (
              filteredGuests.map(guest => (
                <GuestItem
                  key={guest.id}
                  guest={guest}
                  onCheckIn={handleCheckIn}
                  isCheckingIn={checkingInId === guest.id}
                />
              ))
            )}
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'flex-end',
  },
  overlayTouchable: {
    flex: 1,
  },
  sheet: {
    backgroundColor: '#15151F',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    height: SCREEN_HEIGHT * 0.8,
    paddingBottom: 34,
  },
  header: {
    alignItems: 'center',
    paddingTop: 12,
    paddingHorizontal: 20,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  handle: {
    width: 40,
    height: 4,
    borderRadius: 2,
    backgroundColor: 'rgba(255,255,255,0.15)',
    marginBottom: 16,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.3,
  },
  countBadge: {
    backgroundColor: colors.purpleLight,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
  },
  countBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.purple,
  },
  closeButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  searchContainer: {
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  searchWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: 14,
    height: 44,
  },
  searchIcon: {
    marginRight: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: colors.textPrimary,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 12,
    paddingBottom: 20,
  },
  guestItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 14,
    marginBottom: 8,
  },
  guestItemChecked: {
    opacity: 0.55,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.purple,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarChecked: {
    backgroundColor: colors.green,
  },
  avatarText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '700',
  },
  avatarTextChecked: {
    color: colors.white,
  },
  guestInfo: {
    flex: 1,
    marginLeft: 12,
    gap: 4,
  },
  guestName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  guestNameChecked: {
    color: colors.textSecondary,
  },
  typeBadge: {
    alignSelf: 'flex-start',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 6,
    borderWidth: 1,
  },
  typeBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  checkedButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: colors.greenLight,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkInButton: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: colors.purpleLight,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    minWidth: 70,
    alignItems: 'center',
  },
  checkInButtonText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.purple,
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  emptyText: {
    fontSize: 15,
    color: colors.textTertiary,
  },
});
