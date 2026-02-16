import React, { useRef, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  Animated,
  Dimensions,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { getCategoryLabel } from '../../utils/eventCategories';

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

function StatusBadge({ category }) {
  const configs = {
    live: { label: 'LIVE', color: colors.green, bg: colors.greenLight, border: colors.greenBorder, pulse: true },
    today: { label: 'Azi', color: colors.amber, bg: colors.amberLight, border: colors.amberBorder, pulse: false },
    past: { label: 'Încheiat', color: colors.textTertiary, bg: 'rgba(255,255,255,0.05)', border: 'rgba(255,255,255,0.08)', pulse: false },
    future: { label: 'Viitor', color: colors.purple, bg: colors.purpleLight, border: colors.purpleBorder, pulse: false },
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
          <Text style={[styles.eventName, isPast && styles.eventNamePast]} numberOfLines={1}>
            {event.name || event.title}
          </Text>
          <Text style={styles.eventMeta} numberOfLines={1}>
            {event.date || event.event_date} {event.venue ? `\u2022 ${event.venue}` : ''}
          </Text>
        </View>
        <View style={styles.eventItemRight}>
          <StatusBadge category={category} />
          <ChevronRight />
        </View>
      </View>
    </TouchableOpacity>
  );
}

export default function EventsModal({ visible, onClose, events, onSelectEvent }) {
  const categories = ['live', 'today', 'future', 'past'];

  const handleSelectEvent = (event) => {
    if (onSelectEvent) {
      onSelectEvent(event);
    }
    if (onClose) {
      onClose();
    }
  };

  const hasEvents = events && categories.some(cat => events[cat] && events[cat].length > 0);

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <View style={styles.overlay}>
        <TouchableOpacity style={styles.overlayTouchable} onPress={onClose} activeOpacity={1} />
        <View style={styles.sheet}>
          {/* Header */}
          <View style={styles.header}>
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
          </View>

          {/* Event List */}
          <ScrollView
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
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
                <Text style={styles.emptyText}>Niciun eveniment disponibil</Text>
              </View>
            ) : (
              categories.map(category => {
                const categoryEvents = events[category];
                if (!categoryEvents || categoryEvents.length === 0) return null;

                return (
                  <View key={category} style={styles.section}>
                    <SectionHeader category={category} />
                    {categoryEvents.map((event, index) => (
                      <EventItem
                        key={event.id || `${category}-${index}`}
                        event={event}
                        category={category}
                        onPress={() => handleSelectEvent(event)}
                      />
                    ))}
                  </View>
                );
              })
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
    backgroundColor: 'rgba(255,255,255,0.15)',
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
