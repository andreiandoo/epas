import React, { useRef, useEffect, useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  Animated,
  Dimensions,
  RefreshControl,
  TextInput,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import useSwipeToDismiss from '../../hooks/useSwipeToDismiss';
import { colors } from '../../theme/colors';
import { getCategoryLabel } from '../../utils/eventCategories';
import { pickString } from '../../utils/pickString';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

function PulsingDot() {
  const pulseAnim = useRef(new Animated.Value(1)).current;

  useEffect(() => {
    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, {
          toValue: 0.3,
          duration: 1000,
          useNativeDriver: true,
        }),
        Animated.timing(pulseAnim, {
          toValue: 1,
          duration: 1000,
          useNativeDriver: true,
        }),
      ])
    );
    animation.start();
    return () => animation.stop();
  }, [pulseAnim]);

  return (
    <Animated.View
      style={[styles.pulsingDot, { opacity: pulseAnim }]}
    />
  );
}

function StatusBadge({ category, event }) {
  // Backend status trumps the date-based category — a draft with a future
  // event_date still needs the "Nepublicat" chip so the organizer knows
  // ticket sales won't happen until they hit Publish.
  const status = event?.status;
  const label =
    status === 'draft' ? 'Draft' :
    status === 'pending_review' ? 'În revizuire' :
    status === 'rejected' ? 'Respins' :
    null;
  if (label) {
    return (
      <View style={[styles.statusBadge, { backgroundColor: colors.amberLight, borderColor: colors.amberBorder }]}>
        <Text style={[styles.statusBadgeText, { color: colors.amber }]}>{label}</Text>
      </View>
    );
  }

  const configs = {
    live: { label: 'LIVE', color: colors.green, bg: colors.greenLight, border: colors.greenBorder, pulse: true },
    today: { label: 'Azi', color: colors.amber, bg: colors.amberLight, border: colors.amberBorder, pulse: false },
    past: { label: 'Încheiat', color: colors.textTertiary, bg: 'rgba(20,10,10,0.05)', border: 'rgba(20,10,10,0.08)', pulse: false },
    future: { label: 'Viitor', color: colors.purple, bg: colors.purpleLight, border: colors.purpleBorder, pulse: false },
    unpublished: { label: 'Nepublicat', color: colors.amber, bg: colors.amberLight, border: colors.amberBorder, pulse: false },
  };
  const config = configs[category] || configs.future;

  return (
    <View style={[styles.statusBadge, { backgroundColor: config.bg, borderColor: config.border }]}>
      {config.pulse && <PulsingDot />}
      <Text style={[styles.statusBadgeText, { color: config.color }]}>{config.label}</Text>
    </View>
  );
}

function ChevronRight() {
  return (
    <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
      <Path
        d="M9 18l6-6-6-6"
        stroke={colors.textTertiary}
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </Svg>
  );
}

function SectionHeader({ category }) {
  const colorMap = {
    live: colors.green,
    today: colors.amber,
    past: colors.textTertiary,
    future: colors.purple,
    unpublished: colors.amber,
  };
  const sectionColor = colorMap[category] || colors.textSecondary;

  return (
    <View style={styles.sectionHeader}>
      <View style={[styles.sectionDot, { backgroundColor: sectionColor }]} />
      <Text style={[styles.sectionHeaderText, { color: sectionColor }]}>
        {getCategoryLabel(category)}
      </Text>
    </View>
  );
}

function EventItem({ event, category, onPress }) {
  const isLive = category === 'live';
  const isPast = category === 'past';

  // Guard every field that could be a translatable JSON object. Backend
  // usually resolves these server-side but auto-eager-loaded relations
  // occasionally leak the raw {ro,en} shape and rendering that inside
  // <Text> crashes with "Objects are not valid as a React child".
  const venueName = pickString(event.venue_name || event.venue?.name);
  const venueCity = pickString(event.venue_city || event.venue?.city);
  // API returns `starts_at` as a single ISO string (e.g. "2026-05-15T19:00:00").
  // Legacy fields kept as fallback.
  const rawDate = event.starts_at || event.event_date || event.date || event.start_date || '';
  let formattedDate = '';
  if (rawDate) {
    try {
      const d = new Date(rawDate);
      if (!isNaN(d.getTime())) {
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        formattedDate = `${day}.${month}.${year}`;
        // If we got a full ISO with a time component, append HH:MM
        if (typeof rawDate === 'string' && rawDate.includes('T')) {
          const hh = String(d.getHours()).padStart(2, '0');
          const mm = String(d.getMinutes()).padStart(2, '0');
          formattedDate += ` \u00B7 ${hh}:${mm}`;
        } else if (event.start_time) {
          formattedDate += ` \u00B7 ${event.start_time.slice(0, 5)}`;
        }
      }
    } catch { formattedDate = ''; }
  }
  const venueLine = venueCity ? (venueName ? `${venueName}, ${venueCity}` : venueCity) : venueName;

  return (
    <TouchableOpacity
      style={[
        styles.eventItem,
        isLive && styles.eventItemLive,
        isPast && styles.eventItemPast,
      ]}
      onPress={onPress}
      activeOpacity={0.7}
    >
      <View style={styles.eventItemContent}>
        <View style={styles.eventItemLeft}>
          {formattedDate ? (
            <Text style={[styles.eventDate, isPast && styles.eventDatePast]} numberOfLines={1}>
              {formattedDate}
            </Text>
          ) : null}
          <Text style={[styles.eventName, isPast && styles.eventNamePast]} numberOfLines={1}>
            {pickString(event.name || event.title, 'Eveniment fără titlu')}
          </Text>
          {venueLine ? (
            <Text style={styles.eventMeta} numberOfLines={1}>{venueLine}</Text>
          ) : null}
        </View>
        <View style={styles.eventItemRight}>
          <StatusBadge category={category} event={event} />
          <ChevronRight />
        </View>
      </View>
    </TouchableOpacity>
  );
}

/**
 * Resolve a sortable timestamp from an event row. Combines event_date with
 * start_time when both are present so two events on the same day are still
 * stable-ordered. Falls back to NaN-ish so undated events sink to the bottom.
 */
function eventSortKey(event) {
  const raw = event.starts_at || event.event_date || event.date || event.start_date;
  if (!raw) return Number.MAX_SAFE_INTEGER;
  try {
    let parsed;
    if (typeof raw === 'string' && raw.includes('T')) {
      parsed = new Date(raw);
    } else {
      const time = event.start_time && event.start_time.length >= 4
        ? event.start_time.slice(0, 5)
        : '00:00';
      parsed = new Date(`${raw}T${time}:00`);
    }
    const t = parsed.getTime();
    return isNaN(t) ? Number.MAX_SAFE_INTEGER : t;
  } catch {
    return Number.MAX_SAFE_INTEGER;
  }
}

// Filter tabs: 'curente' merges live + today + future (anything the
// operator can still operate on), 'draft' surfaces unpublished only, and
// 'trecute' isolates past. `curente` is the default because 90%+ of the
// time the operator wants an actionable event.
const FILTER_TO_CATEGORIES = {
  curente: ['live', 'today', 'future'],
  draft: ['unpublished'],
  trecute: ['past'],
};

export default function EventsModal({ visible, onClose, events, onSelectEvent, onRefresh }) {
  const { translateY, panResponder } = useSwipeToDismiss(onClose);
  const [filter, setFilter] = useState('curente');
  const categories = FILTER_TO_CATEGORIES[filter] || FILTER_TO_CATEGORIES.curente;
  const [refreshing, setRefreshing] = useState(false);
  const [query, setQuery] = useState('');
  useEffect(() => {
    if (!visible) {
      setQuery('');
      setFilter('curente');
    }
  }, [visible]);
  const matchesQuery = (event) => {
    if (!query.trim()) return true;
    const needle = query.trim().toLowerCase();
    const haystack = [
      pickString(event.name), pickString(event.title),
      pickString(event.venue_name), pickString(event.venue?.name),
      pickString(event.venue_city), pickString(event.venue?.city),
    ].filter(Boolean).join(' ').toLowerCase();
    return haystack.includes(needle);
  };

  // Auto-refresh whenever the sheet opens — covers the "just published a new
  // event on the web admin" case: mobile picker shows the fresh list without
  // the operator having to close/reopen the app.
  useEffect(() => {
    if (!visible || !onRefresh) return;
    let cancelled = false;
    (async () => {
      try { await onRefresh(); } catch {}
      if (!cancelled) setRefreshing(false);
    })();
    return () => { cancelled = true; };
  }, [visible]);

  const handleManualRefresh = async () => {
    if (!onRefresh) return;
    setRefreshing(true);
    try { await onRefresh(); } catch {}
    setRefreshing(false);
  };

  const handleSelectEvent = (event) => {
    if (onSelectEvent) {
      onSelectEvent(event);
    }
    if (onClose) {
      onClose();
    }
  };

  const hasEvents = events && categories.some(cat => events[cat] && events[cat].length > 0);
  const emptyLabel = filter === 'draft' ? 'Niciun draft'
    : filter === 'trecute' ? 'Niciun eveniment trecut'
    : 'Niciun eveniment curent';

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <TouchableOpacity style={styles.overlayTouchable} onPress={onClose} activeOpacity={1} />
        <Animated.View style={[styles.sheet, { transform: [{ translateY }] }]}>
          {/* Header */}
          <View style={styles.header} {...panResponder.panHandlers}>
            <View style={styles.handle} />
            <View style={styles.headerRow}>
              <Text style={styles.title}>Selectează Eveniment</Text>
              <TouchableOpacity onPress={onClose} style={styles.closeButton} activeOpacity={0.7}>
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
            {/* Search — free-text over name + venue + city. Cleared on close. */}
            <View style={styles.searchWrap}>
              <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                <Path d="M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.35-4.35"
                  stroke={colors.textTertiary} strokeWidth={2}
                  strokeLinecap="round" strokeLinejoin="round" />
              </Svg>
              <TextInput
                value={query}
                onChangeText={setQuery}
                placeholder="Caută după nume, venue, oraș"
                placeholderTextColor={colors.textTertiary}
                style={styles.searchInput}
                autoCorrect={false}
                autoCapitalize="none"
                returnKeyType="search"
              />
              {query.length > 0 && (
                <TouchableOpacity onPress={() => setQuery('')} activeOpacity={0.7}>
                  <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
                    <Path d="M18 6L6 18M6 6l12 12"
                      stroke={colors.textTertiary} strokeWidth={2}
                      strokeLinecap="round" strokeLinejoin="round" />
                  </Svg>
                </TouchableOpacity>
              )}
            </View>

            {/* Filter tabs — count per tab so the operator sees at a
                glance whether there's anything to switch to. */}
            <View style={styles.filterTabs}>
              {[
                { key: 'curente', label: 'Curente' },
                { key: 'draft', label: 'Draft' },
                { key: 'trecute', label: 'Trecute' },
              ].map(t => {
                const count = FILTER_TO_CATEGORIES[t.key]
                  .reduce((n, cat) => n + ((events?.[cat] || []).length), 0);
                const active = filter === t.key;
                return (
                  <TouchableOpacity
                    key={t.key}
                    style={[styles.filterTab, active && styles.filterTabActive]}
                    onPress={() => setFilter(t.key)}
                    activeOpacity={0.7}
                  >
                    <Text style={[styles.filterTabText, active && styles.filterTabTextActive]}>
                      {t.label}
                    </Text>
                    {count > 0 ? (
                      <View style={[styles.filterTabBadge, active && styles.filterTabBadgeActive]}>
                        <Text style={[styles.filterTabBadgeText, active && styles.filterTabBadgeTextActive]}>
                          {count}
                        </Text>
                      </View>
                    ) : null}
                  </TouchableOpacity>
                );
              })}
            </View>
          </View>

          {/* Event List */}
          <ScrollView
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
            refreshControl={onRefresh ? (
              <RefreshControl
                refreshing={refreshing}
                onRefresh={handleManualRefresh}
                tintColor={colors.purple}
                colors={[colors.purple]}
              />
            ) : undefined}
          >
            {!hasEvents ? (
              <View style={styles.emptyState}>
                <Svg width={48} height={48} viewBox="0 0 24 24" fill="none">
                  <Path
                    d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"
                    stroke={colors.textTertiary}
                    strokeWidth={1.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </Svg>
                <Text style={styles.emptyText}>{emptyLabel}</Text>
              </View>
            ) : (
              (() => {
                const rendered = categories.map(category => {
                  const categoryEvents = (events[category] || []).filter(matchesQuery);
                  if (categoryEvents.length === 0) return null;
                  const sortedEvents = category === 'unpublished'
                    ? [...categoryEvents].sort((a, b) => (b.id || 0) - (a.id || 0))
                    : category === 'past'
                      ? [...categoryEvents].sort((a, b) => eventSortKey(b) - eventSortKey(a))
                      : [...categoryEvents].sort((a, b) => eventSortKey(a) - eventSortKey(b));
                  return (
                    <View key={category} style={styles.section}>
                      <SectionHeader category={category} />
                      {sortedEvents.map((event, index) => (
                        <EventItem
                          key={event.id || `${category}-${index}`}
                          event={event}
                          category={category}
                          onPress={() => handleSelectEvent(event)}
                        />
                      ))}
                    </View>
                  );
                }).filter(Boolean);
                if (rendered.length === 0 && query.trim()) {
                  return (
                    <View style={styles.emptyState}>
                      <Text style={styles.emptyText}>Nimic pentru „{query.trim()}"</Text>
                    </View>
                  );
                }
                return rendered;
              })()
            )}
          </ScrollView>
        </Animated.View>
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
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: SCREEN_HEIGHT * 0.85,
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
    backgroundColor: 'rgba(20,10,10,0.15)',
    marginBottom: 16,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.3,
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
  searchWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: colors.textPrimary,
    padding: 0,
  },
  filterTabs: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 12,
  },
  filterTab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.background,
  },
  filterTabActive: {
    backgroundColor: colors.purple,
    borderColor: colors.purple,
  },
  filterTabText: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  filterTabTextActive: {
    color: colors.white,
  },
  filterTabBadge: {
    minWidth: 20,
    height: 18,
    paddingHorizontal: 5,
    borderRadius: 9,
    backgroundColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  filterTabBadgeActive: {
    backgroundColor: 'rgba(255,255,255,0.24)',
  },
  filterTabBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.textSecondary,
  },
  filterTabBadgeTextActive: {
    color: colors.white,
  },
  scrollView: {
    flexGrow: 0,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 20,
  },
  section: {
    marginBottom: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
    gap: 8,
  },
  sectionDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  sectionHeaderText: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1.2,
  },
  eventItem: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: 8,
    overflow: 'hidden',
  },
  eventItemLive: {
    borderLeftWidth: 3,
    borderLeftColor: colors.green,
  },
  eventItemPast: {
    opacity: 0.6,
  },
  eventItemContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 14,
  },
  eventItemLeft: {
    flex: 1,
    marginRight: 12,
  },
  eventDate: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.purple,
    letterSpacing: 0.4,
    marginBottom: 3,
  },
  eventDatePast: {
    color: colors.textTertiary,
  },
  eventName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 4,
  },
  eventNamePast: {
    color: colors.textSecondary,
  },
  eventMeta: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  eventItemRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
    borderWidth: 1,
    gap: 5,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  pulsingDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#10B981',
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
