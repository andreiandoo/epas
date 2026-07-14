import React, { useEffect, useRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Animated } from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useEvent } from '../context/EventContext';

function StatusBadge({ timeCategory }) {
  const isLive = timeCategory === 'live';
  const isPast = timeCategory === 'past';

  let label = 'Viitor';
  let dotColor = colors.textTertiary;
  let bgColor = colors.surface;
  let borderColor = colors.border;
  let textColor = colors.textTertiary;

  if (isLive) {
    label = 'LIVE';
    dotColor = colors.green;
    bgColor = colors.greenLight;
    borderColor = colors.greenBorder;
    textColor = colors.green;
  } else if (isPast) {
    label = 'Încheiat';
  }

  return (
    <View style={[styles.statusBadge, { backgroundColor: bgColor, borderColor }]}>
      {isLive ? <LiveDot color={dotColor} /> : <StaticDot color={dotColor} />}
      <Text style={[styles.statusBadgeText, { color: textColor }]}>{label}</Text>
    </View>
  );
}

function LiveDot({ color }) {
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
      style={[styles.dot, { backgroundColor: color, opacity: pulseAnim }]}
    />
  );
}

function StaticDot({ color }) {
  return <View style={[styles.dot, { backgroundColor: color }]} />;
}

function ChevronRight() {
  return (
    <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
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

function formatEventDateShort(event) {
  if (!event) return '';
  const startDate = event.start_date || event.date || event.event_date;
  if (!startDate) return '';

  try {
    const d = new Date(startDate);
    // Romanian short date: "15 Mar"
    const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec'];
    return `${d.getDate()} ${months[d.getMonth()]}`;
  } catch {
    return '';
  }
}

function getVenueAndCity(event) {
  if (!event) return '';
  const parts = [];
  const venueName = event.venue?.name || event.venue_name;
  const cityName = event.venue?.city || event.venue_city;
  if (venueName) parts.push(venueName);
  if (cityName && cityName !== venueName) parts.push(cityName);
  return parts.join(', ');
}

export default function EventSelector({ onPress }) {
  const { selectedEvent } = useEvent();

  if (!selectedEvent) {
    return (
      <TouchableOpacity style={styles.container} onPress={onPress} activeOpacity={0.7}>
        <View style={styles.inner}>
          <View style={styles.content}>
            <Text style={styles.noEventText}>Niciun eveniment selectat</Text>
            <Text style={styles.metaText}>Apasă pentru a alege un eveniment</Text>
          </View>
          <ChevronRight />
        </View>
      </TouchableOpacity>
    );
  }

  const dateShort = formatEventDateShort(selectedEvent);
  const venueCity = getVenueAndCity(selectedEvent);
  const eventTitle = selectedEvent.title || selectedEvent.name || 'Eveniment Fără Titlu';
  const timeCategory = selectedEvent.timeCategory || 'upcoming';

  return (
    <TouchableOpacity style={styles.container} onPress={onPress} activeOpacity={0.7}>
      <View style={styles.inner}>
        <View style={styles.content}>
          <View style={styles.titleRow}>
            <Text style={styles.eventName} numberOfLines={1}>
              {dateShort ? (
                <>
                  <Text style={styles.eventDatePrefix}>{dateShort}  </Text>
                  <Text>{eventTitle}</Text>
                </>
              ) : (
                eventTitle
              )}
            </Text>
            <StatusBadge timeCategory={timeCategory} />
          </View>
          {venueCity ? (
            <Text style={styles.metaText} numberOfLines={1}>{venueCity}</Text>
          ) : null}
        </View>
        <ChevronRight />
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  container: {
    backgroundColor: colors.purpleBg,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  inner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  content: {
    flex: 1,
    marginRight: 12,
  },
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 3,
  },
  eventName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    flexShrink: 1,
  },
  eventDatePrefix: {
    color: colors.purple,
    fontWeight: '700',
  },
  noEventText: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 2,
  },
  metaText: {
    fontSize: 12,
    color: colors.textTertiary,
    lineHeight: 16,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 12,
    borderWidth: 1,
    gap: 5,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 0.5,
  },
  dot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
});
