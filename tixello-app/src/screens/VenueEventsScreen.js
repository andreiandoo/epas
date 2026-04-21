import React, { useCallback, useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  FlatList,
  RefreshControl,
  ActivityIndicator,
  Image,
} from 'react-native';
import Svg, { Path, Circle, Rect } from 'react-native-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme/colors';
import { useAuth } from '../context/AuthContext';
import { listEvents } from '../api/venueOwner';

const TABS = [
  { key: 'upcoming', label: 'Viitor' },
  { key: 'past', label: 'Trecut' },
  { key: 'all', label: 'Toate' },
];

function CalendarIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textSecondary} strokeWidth={1.8}>
      <Rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
      <Path d="M16 2v4M8 2v4M3 10h18" />
    </Svg>
  );
}

function TicketIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textSecondary} strokeWidth={1.8}>
      <Path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z" />
    </Svg>
  );
}

function CheckIcon({ size = 16, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.green} strokeWidth={2}>
      <Path d="M20 6L9 17l-5-5" />
    </Svg>
  );
}

function ChevronIcon({ size = 18, color }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke={color || colors.textTertiary} strokeWidth={2}>
      <Path d="M9 18l6-6-6-6" />
    </Svg>
  );
}

function formatDate(iso) {
  if (!iso) return '';
  try {
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch {
    return iso;
  }
}

function EventCard({ event, onPress }) {
  const stats = event.stats || {};
  const soldLabel = stats.stock_total > 0
    ? `${stats.tickets_sold} / ${stats.stock_total}`
    : `${stats.tickets_sold}`;

  return (
    <TouchableOpacity style={styles.card} onPress={onPress} activeOpacity={0.7}>
      <View style={styles.cardHeader}>
        <Text style={styles.cardTitle} numberOfLines={2}>{event.title}</Text>
        <ChevronIcon />
      </View>

      <View style={styles.metaRow}>
        <CalendarIcon />
        <Text style={styles.metaText}>
          {formatDate(event.start_date)}
          {event.start_time ? ` · ${event.start_time.slice(0, 5)}` : ''}
        </Text>
      </View>

      {event.marketplace_organizer?.name && (
        <Text style={styles.organizerText} numberOfLines={1}>
          Organizator: {event.marketplace_organizer.name}
        </Text>
      )}

      <View style={styles.statsRow}>
        <View style={styles.statItem}>
          <TicketIcon color={colors.purple} />
          <Text style={styles.statValue}>{soldLabel}</Text>
          <Text style={styles.statLabel}>vândute</Text>
        </View>
        <View style={styles.statItem}>
          <CheckIcon color={colors.green} />
          <Text style={styles.statValue}>{stats.checked_in_count || 0}</Text>
          <Text style={styles.statLabel}>check-in</Text>
        </View>
      </View>

      {event.is_cancelled && <Text style={styles.badgeCancelled}>Anulat</Text>}
      {event.is_postponed && <Text style={styles.badgePostponed}>Reprogramat</Text>}
    </TouchableOpacity>
  );
}

export default function VenueEventsScreen({ navigation }) {
  const insets = useSafeAreaInsets();
  const { venueOwner, logout } = useAuth();
  const [scope, setScope] = useState('upcoming');
  const [events, setEvents] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState('');

  const fetchEvents = useCallback(async (nextScope = scope, { isRefresh = false } = {}) => {
    try {
      if (!isRefresh) setIsLoading(true);
      setError('');
      const data = await listEvents(nextScope);
      if (data?.success) {
        setEvents(data.data?.events || []);
      } else {
        setError(data?.message || 'Nu am putut încărca evenimentele');
      }
    } catch (err) {
      setError(err?.message || 'Eroare de conexiune');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, [scope]);

  useEffect(() => {
    fetchEvents(scope);
  }, [scope]);

  const venueName = venueOwner?.venues?.[0]?.name || venueOwner?.tenant?.public_name || 'Locația mea';

  return (
    <View style={[styles.container, { paddingTop: insets.top }]}>
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.headerTitle} numberOfLines={1}>{venueName}</Text>
          <Text style={styles.headerSubtitle}>Evenimente la locația ta</Text>
        </View>
        <TouchableOpacity onPress={logout} style={styles.logoutButton}>
          <Text style={styles.logoutText}>Ieșire</Text>
        </TouchableOpacity>
      </View>

      <View style={styles.tabs}>
        {TABS.map(tab => (
          <TouchableOpacity
            key={tab.key}
            style={[styles.tab, scope === tab.key && styles.tabActive]}
            onPress={() => setScope(tab.key)}
          >
            <Text style={[styles.tabLabel, scope === tab.key && styles.tabLabelActive]}>
              {tab.label}
            </Text>
          </TouchableOpacity>
        ))}
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
          data={events}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <EventCard
              event={item}
              onPress={() => navigation.navigate('VenueEventDetail', { eventId: item.id, title: item.title })}
            />
          )}
          contentContainerStyle={styles.list}
          refreshControl={
            <RefreshControl
              refreshing={isRefreshing}
              onRefresh={() => { setIsRefreshing(true); fetchEvents(scope, { isRefresh: true }); }}
              tintColor={colors.textPrimary}
            />
          }
          ListEmptyComponent={
            <View style={styles.empty}>
              <Text style={styles.emptyText}>Nu sunt evenimente pentru acest filtru.</Text>
            </View>
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 20,
    paddingVertical: 14,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.05)',
  },
  headerTitle: { color: colors.textPrimary, fontSize: 18, fontWeight: '700' },
  headerSubtitle: { color: colors.textSecondary, fontSize: 12, marginTop: 2 },
  logoutButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    backgroundColor: 'rgba(255,255,255,0.06)',
  },
  logoutText: { color: colors.textSecondary, fontSize: 12, fontWeight: '600' },
  tabs: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    paddingVertical: 12,
    gap: 8,
  },
  tab: {
    flex: 1,
    paddingVertical: 10,
    alignItems: 'center',
    borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  tabActive: { backgroundColor: colors.purple },
  tabLabel: { color: colors.textSecondary, fontWeight: '600', fontSize: 13 },
  tabLabelActive: { color: '#fff' },
  list: { padding: 16, paddingBottom: 40 },
  card: {
    backgroundColor: colors.surface,
    borderRadius: 14,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 8 },
  cardTitle: { color: colors.textPrimary, fontSize: 16, fontWeight: '700', flex: 1, marginRight: 10 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 },
  metaText: { color: colors.textSecondary, fontSize: 13 },
  organizerText: { color: colors.textTertiary, fontSize: 12, marginTop: 6 },
  statsRow: { flexDirection: 'row', gap: 20, marginTop: 12, paddingTop: 12, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
  statItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  statValue: { color: colors.textPrimary, fontSize: 14, fontWeight: '700' },
  statLabel: { color: colors.textSecondary, fontSize: 12 },
  badgeCancelled: { marginTop: 8, color: colors.red, fontSize: 12, fontWeight: '700' },
  badgePostponed: { marginTop: 8, color: colors.amber, fontSize: 12, fontWeight: '700' },
  errorBox: {
    marginHorizontal: 20,
    marginVertical: 8,
    padding: 12,
    backgroundColor: colors.redBg,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.redBorder,
  },
  errorText: { color: colors.red, fontSize: 13 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  empty: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { color: colors.textTertiary, fontSize: 14 },
});
