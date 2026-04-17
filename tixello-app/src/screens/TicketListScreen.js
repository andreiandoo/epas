import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  TextInput,
  StyleSheet,
  ActivityIndicator,
  Dimensions,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useEvent } from '../context/EventContext';
import { getParticipants, checkinByBarcode } from '../api/participants';

const { width: SCREEN_WIDTH } = Dimensions.get('window');

// ─── SVG Icon Components ──────────────────────────────────────────────────────

function SearchIcon({ size = 18, color = colors.textTertiary }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function CloseIcon({ size = 24, color = colors.white }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M18 6L6 18M6 6l12 12"
        stroke={color}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function TicketIcon({ size = 20, color = colors.purple }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <Path
        d="M2 9V6a2 2 0 012-2h16a2 2 0 012 2v3M2 9a3 3 0 000 6M2 15v3a2 2 0 002 2h16a2 2 0 002-2v-3M22 15a3 3 0 000-6M22 9H2M7 4v2M7 18v2M17 4v2M17 18v2"
        stroke={color}
        strokeWidth={1.8}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

// ─── Status Badge Component ──────────────────────────────────────────────────

function StatusBadge({ status, checkedInAt }) {
  let label, bgColor, textColor, borderColor;

  if (checkedInAt || status === 'checked_in') {
    label = 'Checked-in';
    bgColor = colors.greenBg;
    textColor = colors.green;
    borderColor = colors.greenBorder;
  } else if (status === 'cancelled' || status === 'refunded') {
    label = 'Invalid';
    bgColor = colors.redBg;
    textColor = colors.red;
    borderColor = colors.redBorder;
  } else {
    label = 'Nevalidat';
    bgColor = colors.amberBg;
    textColor = colors.amber;
    borderColor = colors.amberBorder;
  }

  return (
    <View style={[styles.statusBadge, { backgroundColor: bgColor, borderColor }]}>
      <Text style={[styles.statusBadgeText, { color: textColor }]}>{label}</Text>
    </View>
  );
}

// ─── Ticket Card Component ───────────────────────────────────────────────────

function TicketCard({ item, onCheckIn, isCheckingIn }) {
  const attendeeName = item.attendee?.name;
  const buyerName = item.customer?.name || item.name || item.full_name || 'Anonim';
  const primaryName = attendeeName || buyerName;
  const secondaryName = attendeeName && attendeeName !== buyerName ? `cumpărat de ${buyerName}` : null;
  const code = item.code || item.barcode || item.ticket_code || '—';
  const ticketType = item.ticket_type || item.ticket_type_name || '—';
  const isCheckedIn = !!item.checked_in_at || item.status === 'checked_in';
  const isInvalid = item.status === 'cancelled' || item.status === 'refunded';
  const canCheckIn = !isCheckedIn && !isInvalid && !!item.barcode;

  return (
    <View style={[styles.ticketCard, isCheckedIn && styles.ticketCardChecked]}>
      <View style={styles.ticketCardLeft}>
        <Text style={styles.ticketBeneficiary} numberOfLines={1}>{primaryName}</Text>
        {secondaryName ? (
          <Text style={styles.ticketBuyer} numberOfLines={1}>{secondaryName}</Text>
        ) : null}
        <Text style={styles.ticketCode} numberOfLines={1}>{code}</Text>
        <Text style={styles.ticketType} numberOfLines={1}>{ticketType}</Text>
      </View>
      {isCheckedIn ? (
        <StatusBadge status={item.status} checkedInAt={item.checked_in_at} />
      ) : isInvalid ? (
        <StatusBadge status={item.status} checkedInAt={null} />
      ) : canCheckIn ? (
        <TouchableOpacity
          style={styles.checkInButton}
          onPress={() => onCheckIn(item)}
          disabled={isCheckingIn}
          activeOpacity={0.7}
        >
          {isCheckingIn ? (
            <ActivityIndicator size="small" color={colors.white} />
          ) : (
            <Text style={styles.checkInButtonText}>Check-in</Text>
          )}
        </TouchableOpacity>
      ) : (
        <StatusBadge status={item.status} checkedInAt={null} />
      )}
    </View>
  );
}

// ─── Main TicketListScreen Component ─────────────────────────────────────────

export default function TicketListScreen({ onClose, onCheckInSuccess }) {
  const { selectedEvent, refreshStats, refreshTicketTypes, incrementCheckedIn } = useEvent();
  const [participants, setParticipants] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [checkingInId, setCheckingInId] = useState(null);

  // Fetch all participants paginated
  const fetchAllParticipants = useCallback(async (silent = false) => {
    if (!selectedEvent?.id) return;
    if (!silent) setIsLoading(true);
    else setIsRefreshing(true);

    try {
      let allParticipants = [];
      let page = 1;
      let hasMore = true;

      while (hasMore) {
        const response = await getParticipants(selectedEvent.id, { per_page: 200, page });
        const data = response.data || [];
        // Handle both array and nested participants
        const list = Array.isArray(data) ? data : (data.participants || []);
        allParticipants = [...allParticipants, ...list];
        const meta = response.meta || {};
        hasMore = meta.current_page < meta.last_page;
        page++;
      }

      setParticipants(allParticipants);
    } catch (e) {
      console.error('Failed to fetch participants:', e);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, [selectedEvent?.id]);

  // Initial load
  useEffect(() => {
    if (selectedEvent?.id) {
      fetchAllParticipants(false);
    }
  }, [selectedEvent?.id]);

  // Auto-refresh every 10 seconds
  useEffect(() => {
    if (!selectedEvent?.id) return;

    const interval = setInterval(() => {
      fetchAllParticipants(true);
    }, 10000);

    return () => clearInterval(interval);
  }, [selectedEvent?.id, fetchAllParticipants]);

  // Filter by search query — matches code, buyer/beneficiary name or phone
  const filteredParticipants = participants.filter((p) => {
    if (!searchQuery.trim()) return true;
    const query = searchQuery.toLowerCase().trim();
    const buyerName = (p.customer?.name || p.name || p.full_name || '').toLowerCase();
    const buyerPhone = (p.customer?.phone || '').toLowerCase();
    const buyerEmail = (p.customer?.email || '').toLowerCase();
    const attendeeName = (p.attendee?.name || '').toLowerCase();
    const attendeePhone = (p.attendee?.phone || '').toLowerCase();
    const attendeeEmail = (p.attendee?.email || '').toLowerCase();
    const code = (p.code || p.barcode || p.ticket_code || '').toLowerCase();
    const orderNum = (p.order_number || '').toLowerCase();
    return (
      code.includes(query) ||
      buyerName.includes(query) ||
      attendeeName.includes(query) ||
      buyerPhone.includes(query) ||
      attendeePhone.includes(query) ||
      buyerEmail.includes(query) ||
      attendeeEmail.includes(query) ||
      orderNum.includes(query)
    );
  });

  const handleCheckIn = useCallback(async (item) => {
    if (!selectedEvent?.id || !item.barcode) return;
    setCheckingInId(item.id);
    try {
      await checkinByBarcode(selectedEvent.id, item.barcode);
      // Update local state immediately
      setParticipants(prev =>
        prev.map(p => p.id === item.id
          ? { ...p, checked_in_at: new Date().toISOString(), status: 'checked_in' }
          : p
        )
      );
      // Refresh dashboard stats (card + per-type modals)
      if (incrementCheckedIn) incrementCheckedIn();
      if (refreshStats) refreshStats();
      if (refreshTicketTypes) refreshTicketTypes();
      if (onCheckInSuccess) onCheckInSuccess();
    } catch (e) {
      console.error('Check-in failed:', e);
      // If already checked in, still mark as such
      if (e.message?.toLowerCase().includes('already')) {
        setParticipants(prev =>
          prev.map(p => p.id === item.id
            ? { ...p, checked_in_at: new Date().toISOString(), status: 'checked_in' }
            : p
          )
        );
      }
    }
    setCheckingInId(null);
  }, [selectedEvent?.id, incrementCheckedIn, refreshStats, refreshTicketTypes, onCheckInSuccess]);

  // Sort: unchecked first, checked-in last
  const sortedParticipants = [...filteredParticipants].sort((a, b) => {
    const aChecked = a.checked_in_at || a.status === 'checked_in' ? 1 : 0;
    const bChecked = b.checked_in_at || b.status === 'checked_in' ? 1 : 0;
    return aChecked - bChecked;
  });

  // Stats — use eventStats.total_sold for total count (consistent with Dashboard)
  const { eventStats } = useEvent();
  const totalCount = eventStats?.total_sold ?? participants.length;
  const checkedInCount = eventStats?.checked_in ?? participants.filter(
    (p) => p.checked_in_at || p.status === 'checked_in'
  ).length;

  const renderItem = useCallback(
    ({ item }) => (
      <TicketCard
        item={item}
        onCheckIn={handleCheckIn}
        isCheckingIn={checkingInId === item.id}
      />
    ),
    [handleCheckIn, checkingInId]
  );

  const keyExtractor = useCallback(
    (item, index) => item.id?.toString() || item.barcode || index.toString(),
    []
  );

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <TouchableOpacity
          style={styles.closeButton}
          onPress={onClose}
          activeOpacity={0.7}
        >
          <CloseIcon size={22} color={colors.white} />
        </TouchableOpacity>
        <View style={styles.headerTitleWrap}>
          <TicketIcon size={20} color={colors.purple} />
          <Text style={styles.headerTitle}>Bilete Eveniment</Text>
        </View>
        <View style={styles.headerSpacer} />
      </View>

      {/* Stats bar */}
      <View style={styles.statsBar}>
        <Text style={styles.statsText}>
          {totalCount} bilete ({checkedInCount} intrați)
        </Text>
        {isRefreshing && (
          <ActivityIndicator size="small" color={colors.purple} style={{ marginLeft: 8 }} />
        )}
      </View>

      {/* Search bar */}
      <View style={styles.searchContainer}>
        <SearchIcon size={18} color={colors.textTertiary} />
        <TextInput
          style={styles.searchInput}
          placeholder="Caută după cod, nume, telefon..."
          placeholderTextColor={colors.textQuaternary}
          value={searchQuery}
          onChangeText={setSearchQuery}
          autoCapitalize="none"
          autoCorrect={false}
        />
        {searchQuery.length > 0 && (
          <TouchableOpacity onPress={() => setSearchQuery('')} activeOpacity={0.7}>
            <CloseIcon size={16} color={colors.textTertiary} />
          </TouchableOpacity>
        )}
      </View>

      {/* Content */}
      {isLoading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color={colors.purple} />
          <Text style={styles.loadingText}>Se încarcă biletele...</Text>
        </View>
      ) : (
        <FlatList
          data={sortedParticipants}
          renderItem={renderItem}
          keyExtractor={keyExtractor}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={
            <View style={styles.emptyState}>
              <Text style={styles.emptyStateText}>
                {searchQuery.trim()
                  ? 'Niciun bilet găsit pentru căutarea ta'
                  : 'Niciun bilet pentru acest eveniment'}
              </Text>
            </View>
          }
        />
      )}
    </View>
  );
}

// ─── Styles ─────────────────────────────────────────────────────────────────

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },

  // ── Header ──────────────────────────────────────────────────────────────────
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 52,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  closeButton: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerTitleWrap: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  headerTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  headerSpacer: {
    width: 40,
  },

  // ── Stats Bar ───────────────────────────────────────────────────────────────
  statsBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    paddingHorizontal: 16,
    backgroundColor: colors.purpleBg,
    borderBottomWidth: 1,
    borderBottomColor: colors.purpleBorder,
  },
  statsText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.purple,
  },

  // ── Search ──────────────────────────────────────────────────────────────────
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.borderMedium,
    borderRadius: 12,
    marginHorizontal: 16,
    marginTop: 12,
    marginBottom: 8,
    paddingHorizontal: 14,
    height: 44,
    gap: 10,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: colors.textPrimary,
    paddingVertical: 0,
  },

  // ── List ────────────────────────────────────────────────────────────────────
  listContent: {
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 32,
  },

  // ── Ticket Card ─────────────────────────────────────────────────────────────
  ticketCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
    marginBottom: 8,
  },
  ticketCardLeft: {
    flex: 1,
    marginRight: 12,
  },
  ticketCardChecked: {
    opacity: 0.55,
  },
  ticketBeneficiary: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 3,
  },
  ticketBuyer: {
    fontSize: 11,
    color: colors.textTertiary,
    fontStyle: 'italic',
    marginBottom: 3,
  },
  ticketCode: {
    fontSize: 13,
    fontWeight: '500',
    color: colors.textSecondary,
    fontFamily: 'monospace',
    marginBottom: 2,
  },
  ticketType: {
    fontSize: 12,
    color: colors.textTertiary,
  },

  // ── Status Badge ────────────────────────────────────────────────────────────
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    borderWidth: 1,
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: '700',
  },

  // ── Check-in Button ─────────────────────────────────────────────────────────
  checkInButton: {
    backgroundColor: colors.purple,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    minWidth: 80,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkInButtonText: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.white,
  },

  // ── Loading ─────────────────────────────────────────────────────────────────
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 14,
    color: colors.textSecondary,
  },

  // ── Empty State ─────────────────────────────────────────────────────────────
  emptyState: {
    paddingVertical: 60,
    alignItems: 'center',
  },
  emptyStateText: {
    fontSize: 14,
    color: colors.textTertiary,
    textAlign: 'center',
  },
});
