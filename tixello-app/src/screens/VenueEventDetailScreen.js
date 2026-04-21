import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  FlatList,
  TextInput,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import Svg, { Path, Circle, Rect } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { getEvent, listAttendees } from '../api/venueOwner';

function BackIcon({ size = 22, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textPrimary} strokeWidth={2}>
      <Path d="M19 12H5M12 19l-7-7 7-7" />
    </Svg>
  );
}

function SearchIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textSecondary} strokeWidth={2}>
      <Circle cx="11" cy="11" r="8" />
      <Path d="M21 21l-4.35-4.35" />
    </Svg>
  );
}

function ChevronIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textTertiary} strokeWidth={2}>
      <Path d="M9 18l6-6-6-6" />
    </Svg>
  );
}

function formatDate(iso) {
  if (!iso) return '—';
  try {
    const d = new Date(iso);
    return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch { return iso; }
}

function statusColor(status) {
  switch (status) {
    case 'used': return colors.green;
    case 'valid': return colors.cyan;
    case 'cancelled':
    case 'refunded':
      return colors.red;
    case 'pending': return colors.amber;
    default: return colors.textSecondary;
  }
}

function statusLabel(status) {
  switch (status) {
    case 'used': return 'Utilizat';
    case 'valid': return 'Valid';
    case 'cancelled': return 'Anulat';
    case 'refunded': return 'Rambursat';
    case 'pending': return 'În așteptare';
    default: return status || '—';
  }
}

function AttendeeRow({ ticket, onPress }) {
  const customer = ticket.customer || {};
  const customerName = customer.full_name || '—';
  const seat = ticket.seat;
  const seatText = seat
    ? [seat.section_name, seat.row_label && `rând ${seat.row_label}`, seat.seat_number && `loc ${seat.seat_number}`]
        .filter(Boolean).join(' · ')
    : null;

  return (
    <TouchableOpacity style={styles.row} onPress={onPress} activeOpacity={0.7}>
      <View style={{ flex: 1 }}>
        <View style={styles.rowHeader}>
          <Text style={styles.rowName} numberOfLines={1}>{customerName}</Text>
          <Text style={[styles.rowStatus, { color: statusColor(ticket.status) }]}>
            {statusLabel(ticket.status)}
          </Text>
        </View>
        <Text style={styles.rowMeta} numberOfLines={1}>
          {ticket.ticket_type?.name || 'Bilet'} · #{ticket.id}
          {ticket.order?.order_number ? ` · ${ticket.order.order_number}` : ''}
        </Text>
        {seatText && <Text style={styles.rowSeat}>{seatText}</Text>}
        <Text style={styles.rowDate}>{formatDate(ticket.order?.placed_at)}</Text>
      </View>
      <ChevronIcon />
    </TouchableOpacity>
  );
}

export default function VenueEventDetailScreen({ route, navigation }) {
  const insets = useSafeAreaInsets();
  const { eventId, title } = route.params || {};

  const [event, setEvent] = useState(null);
  const [tickets, setTickets] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');

  const searchDebounce = useRef(null);

  const fetchEvent = useCallback(async () => {
    try {
      const data = await getEvent(eventId);
      if (data?.success) setEvent(data.data?.event || null);
    } catch (e) {}
  }, [eventId]);

  const fetchAttendees = useCallback(async ({ query = search, nextPage = 1, append = false } = {}) => {
    try {
      if (!append) setIsLoading(true);
      setError('');
      const data = await listAttendees(eventId, { search: query, page: nextPage, perPage: 25 });
      if (data?.success) {
        const items = data.data || [];
        setTickets(prev => append ? [...prev, ...items] : items);
        setPage(data.meta?.current_page || 1);
        setLastPage(data.meta?.last_page || 1);
        setTotal(data.meta?.total || 0);
      } else {
        setError(data?.message || 'Nu am putut încărca lista');
      }
    } catch (err) {
      setError(err?.message || 'Eroare de conexiune');
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
      setIsRefreshing(false);
    }
  }, [eventId, search]);

  useEffect(() => {
    fetchEvent();
    fetchAttendees({ query: '', nextPage: 1 });
  }, [eventId]);

  // Debounced search
  useEffect(() => {
    if (searchDebounce.current) clearTimeout(searchDebounce.current);
    searchDebounce.current = setTimeout(() => {
      fetchAttendees({ query: search, nextPage: 1 });
    }, 350);
    return () => clearTimeout(searchDebounce.current);
  }, [search]);

  const onEndReached = () => {
    if (isLoadingMore || page >= lastPage) return;
    setIsLoadingMore(true);
    fetchAttendees({ query: search, nextPage: page + 1, append: true });
  };

  const stats = event?.stats || {};

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      <View style={styles.topBar}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
          <BackIcon />
        </TouchableOpacity>
        <Text style={styles.topTitle} numberOfLines={1}>{event?.title || title || 'Eveniment'}</Text>
      </View>

      {event && (
        <View style={styles.summary}>
          <Text style={styles.summaryDate}>
            {formatDate(event.start_date)}
            {event.start_time ? ` · ${event.start_time.slice(0, 5)}` : ''}
          </Text>
          {event.marketplace_organizer?.name && (
            <Text style={styles.summaryOrganizer}>Organizator: {event.marketplace_organizer.name}</Text>
          )}
          <View style={styles.summaryStats}>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>
                {stats.stock_total > 0 ? `${stats.tickets_sold || 0}/${stats.stock_total}` : (stats.tickets_sold || 0)}
              </Text>
              <Text style={styles.statLabel}>Vândute</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{stats.checked_in_count || 0}</Text>
              <Text style={styles.statLabel}>Check-in</Text>
            </View>
            <View style={styles.statBox}>
              <Text style={styles.statValue}>{total || 0}</Text>
              <Text style={styles.statLabel}>Bilete în listă</Text>
            </View>
          </View>
        </View>
      )}

      <View style={styles.searchWrap}>
        <SearchIcon />
        <TextInput
          style={styles.searchInput}
          placeholder="Caută după nume sau număr comandă"
          placeholderTextColor={colors.textTertiary}
          value={search}
          onChangeText={setSearch}
          autoCorrect={false}
          autoCapitalize="none"
        />
      </View>

      {error !== '' && (
        <View style={styles.errorBox}>
          <Text style={styles.errorText}>{error}</Text>
        </View>
      )}

      {isLoading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={colors.purple} />
        </View>
      ) : (
        <FlatList
          data={tickets}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <AttendeeRow
              ticket={item}
              onPress={() => navigation.navigate('VenueTicketDetail', { ticketId: item.id })}
            />
          )}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 40 }}
          onEndReached={onEndReached}
          onEndReachedThreshold={0.5}
          refreshControl={
            <RefreshControl
              refreshing={isRefreshing}
              onRefresh={() => {
                setIsRefreshing(true);
                fetchEvent();
                fetchAttendees({ query: search, nextPage: 1 });
              }}
              tintColor={colors.textPrimary}
            />
          }
          ListFooterComponent={isLoadingMore ? (
            <ActivityIndicator color={colors.textSecondary} style={{ marginVertical: 16 }} />
          ) : null}
          ListEmptyComponent={
            <View style={styles.empty}>
              <Text style={styles.emptyText}>
                {search ? 'Niciun rezultat pentru căutarea ta.' : 'Nu sunt bilete pentru acest eveniment.'}
              </Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 10,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.05)',
  },
  backBtn: { padding: 4 },
  topTitle: { flex: 1, color: colors.textPrimary, fontSize: 17, fontWeight: '700' },
  summary: { padding: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
  summaryDate: { color: colors.textSecondary, fontSize: 13 },
  summaryOrganizer: { color: colors.textTertiary, fontSize: 12, marginTop: 2 },
  summaryStats: { flexDirection: 'row', gap: 10, marginTop: 12 },
  statBox: { flex: 1, backgroundColor: colors.surface, padding: 12, borderRadius: 10, borderWidth: 1, borderColor: colors.border },
  statValue: { color: colors.textPrimary, fontSize: 18, fontWeight: '700' },
  statLabel: { color: colors.textSecondary, fontSize: 11, marginTop: 2 },
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginVertical: 12,
    paddingHorizontal: 12,
    backgroundColor: colors.surface,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
  },
  searchInput: { flex: 1, color: colors.textPrimary, paddingVertical: 10, fontSize: 14 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    backgroundColor: colors.surface,
    borderRadius: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: colors.border,
  },
  rowHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  rowName: { color: colors.textPrimary, fontSize: 15, fontWeight: '600', flex: 1, marginRight: 8 },
  rowStatus: { fontSize: 11, fontWeight: '700', textTransform: 'uppercase' },
  rowMeta: { color: colors.textSecondary, fontSize: 12, marginTop: 3 },
  rowSeat: { color: colors.textTertiary, fontSize: 12, marginTop: 2 },
  rowDate: { color: colors.textQuaternary, fontSize: 11, marginTop: 4 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  empty: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { color: colors.textTertiary, fontSize: 14, textAlign: 'center', paddingHorizontal: 24 },
  errorBox: {
    marginHorizontal: 16,
    marginBottom: 8,
    padding: 10,
    backgroundColor: colors.redBg,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.redBorder,
  },
  errorText: { color: colors.red, fontSize: 13 },
});
