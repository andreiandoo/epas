import React, { useEffect, useRef } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Animated,
  Dimensions,
} from 'react-native';
import Svg, { Polyline, Rect, Defs, LinearGradient, Stop, Path } from 'react-native-svg';
import { colors } from '../theme/colors';
import { useEvent } from '../context/EventContext';
import { formatCurrency } from '../utils/formatCurrency';

const { width: SCREEN_WIDTH } = Dimensions.get('window');
const CARD_GAP = 12;
const CARD_PADDING = 16;
const GRID_PADDING = 16;
const HALF_CARD_WIDTH = (SCREEN_WIDTH - GRID_PADDING * 2 - CARD_GAP) / 2;

// Mock data for gates
const GATE_DATA = [
  { name: 'Gate A', scans: 1247, percentage: 85 },
  { name: 'Gate B', scans: 983, percentage: 67 },
  { name: 'Gate C', scans: 654, percentage: 45 },
  { name: 'VIP Entrance', scans: 312, percentage: 21 },
];

// Mock hourly distribution data
const HOURLY_DATA = [
  { hour: '16:00', value: 25 },
  { hour: '17:00', value: 45 },
  { hour: '18:00', value: 72 },
  { hour: '19:00', value: 95 },
  { hour: '20:00', value: 88 },
  { hour: '21:00', value: 60 },
  { hour: '22:00', value: 30 },
];

// Mock mini chart points for check-in rate sparkline
const SPARK_POINTS = '0,28 15,22 30,25 45,18 60,20 75,12 90,15 105,8 120,10 135,5';

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
      style={[
        styles.pulseDot,
        { opacity: pulseAnim },
      ]}
    />
  );
}

function MetricCard({ label, value, suffix, trend, wide, children }) {
  return (
    <View style={[styles.metricCard, wide && styles.metricCardWide]}>
      <Text style={styles.metricLabel}>{label}</Text>
      <View style={styles.metricValueRow}>
        <Text style={styles.metricValue}>{value}</Text>
        {suffix ? <Text style={styles.metricSuffix}>{suffix}</Text> : null}
        {trend ? (
          <View style={styles.trendBadge}>
            <Text style={styles.trendText}>{trend}</Text>
          </View>
        ) : null}
      </View>
      {children}
    </View>
  );
}

function GateBar({ name, scans, percentage }) {
  const barAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.timing(barAnim, {
      toValue: percentage,
      duration: 800,
      useNativeDriver: false,
    }).start();
  }, [percentage]);

  const barWidth = barAnim.interpolate({
    inputRange: [0, 100],
    outputRange: ['0%', '100%'],
  });

  return (
    <View style={styles.gateItem}>
      <View style={styles.gateHeader}>
        <Text style={styles.gateName}>{name}</Text>
        <View style={styles.gateStats}>
          <Text style={styles.gatePercentage}>{percentage}%</Text>
          <Text style={styles.gateScanCount}>{scans.toLocaleString()} scans</Text>
        </View>
      </View>
      <View style={styles.gateBarTrack}>
        <Animated.View style={[styles.gateBarFill, { width: barWidth }]} />
      </View>
    </View>
  );
}

function RevenueRow({ name, color, amount, maxAmount }) {
  const barAnim = useRef(new Animated.Value(0)).current;
  const ratio = maxAmount > 0 ? amount / maxAmount : 0;

  useEffect(() => {
    Animated.timing(barAnim, {
      toValue: ratio,
      duration: 800,
      useNativeDriver: false,
    }).start();
  }, [ratio]);

  const barWidth = barAnim.interpolate({
    inputRange: [0, 1],
    outputRange: ['0%', '100%'],
  });

  return (
    <View style={styles.revenueRow}>
      <View style={styles.revenueHeader}>
        <View style={styles.revenueNameRow}>
          <View style={[styles.revenueDot, { backgroundColor: color }]} />
          <Text style={styles.revenueName}>{name}</Text>
        </View>
        <Text style={styles.revenueAmount}>{formatCurrency(amount)}</Text>
      </View>
      <View style={styles.revenueBarTrack}>
        <Animated.View
          style={[
            styles.revenueBarFill,
            { width: barWidth, backgroundColor: color },
          ]}
        />
      </View>
    </View>
  );
}

function HourlyChart({ data }) {
  const maxValue = Math.max(...data.map(d => d.value));
  const BAR_MAX_HEIGHT = 120;
  const barColors = [
    colors.purple,
    colors.purpleSecondary,
    colors.purple,
    colors.green,
    colors.green,
    colors.amber,
    colors.textTertiary,
  ];

  return (
    <View style={styles.hourlyChart}>
      <View style={styles.hourlyBars}>
        {data.map((item, index) => {
          const barHeight = maxValue > 0
            ? (item.value / maxValue) * BAR_MAX_HEIGHT
            : 0;
          return (
            <View key={item.hour} style={styles.hourlyBarColumn}>
              <View style={[styles.hourlyBarTrack, { height: BAR_MAX_HEIGHT }]}>
                <View
                  style={[
                    styles.hourlyBarFill,
                    {
                      height: barHeight,
                      backgroundColor: barColors[index % barColors.length],
                    },
                  ]}
                />
              </View>
              <Text style={styles.hourlyLabel}>{item.hour}</Text>
            </View>
          );
        })}
      </View>
    </View>
  );
}

export default function ReportsScreen() {
  const { eventStats, ticketTypes } = useEvent();

  // Derive mock metrics from eventStats or use defaults
  const checkinRate = eventStats?.checked_in_rate ?? 42;
  const salesRate = eventStats?.sales_rate ?? 18;
  const peakHour = eventStats?.peak_hour ?? '20:00';

  // Build revenue per ticket type (mock amounts based on ticket type data)
  const revenueData = ticketTypes.map((tt, i) => ({
    name: tt.name || `Ticket ${i + 1}`,
    color: tt.color || colors.purple,
    amount: tt.price ? tt.price * (tt.quota_sold || Math.floor(Math.random() * 200) + 50) : (Math.floor(Math.random() * 5000) + 1000),
  }));
  const maxRevenue = Math.max(...revenueData.map(r => r.amount), 1);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.contentContainer}
      showsVerticalScrollIndicator={false}
    >
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Live Reports</Text>
        <View style={styles.headerSubRow}>
          <PulsingDot />
          <Text style={styles.headerSubText}>Live - Updated just now</Text>
        </View>
      </View>

      {/* Metrics Grid */}
      <View style={styles.metricsGrid}>
        {/* Check-in Rate - full width */}
        <MetricCard
          label="Check-in Rate"
          value={checkinRate}
          suffix="/min"
          trend="+12%"
          wide
        >
          <View style={styles.sparkContainer}>
            <Svg width={140} height={36} viewBox="0 0 140 36">
              <Defs>
                <LinearGradient id="sparkGrad" x1="0" y1="0" x2="0" y2="1">
                  <Stop offset="0" stopColor={colors.purple} stopOpacity="0.4" />
                  <Stop offset="1" stopColor={colors.purple} stopOpacity="0" />
                </LinearGradient>
              </Defs>
              <Polyline
                points={SPARK_POINTS}
                fill="none"
                stroke={colors.purple}
                strokeWidth={2}
                strokeLinejoin="round"
                strokeLinecap="round"
              />
            </Svg>
          </View>
        </MetricCard>

        {/* Sales Rate + Peak Hour side by side */}
        <View style={styles.metricsRow}>
          <MetricCard label="Sales Rate" value={salesRate} suffix="/min" />
          <MetricCard label="Peak Hour" value={peakHour} />
        </View>
      </View>

      {/* Gate Performance */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Gate Performance</Text>
        <View style={styles.sectionCard}>
          {GATE_DATA.map((gate, index) => (
            <View key={gate.name}>
              <GateBar
                name={gate.name}
                scans={gate.scans}
                percentage={gate.percentage}
              />
              {index < GATE_DATA.length - 1 && <View style={styles.divider} />}
            </View>
          ))}
        </View>
      </View>

      {/* Revenue Breakdown */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Revenue Breakdown</Text>
        <View style={styles.sectionCard}>
          {revenueData.map((item, index) => (
            <View key={item.name}>
              <RevenueRow
                name={item.name}
                color={item.color}
                amount={item.amount}
                maxAmount={maxRevenue}
              />
              {index < revenueData.length - 1 && <View style={styles.divider} />}
            </View>
          ))}
          {revenueData.length === 0 && (
            <Text style={styles.emptyText}>No ticket types available</Text>
          )}
        </View>
      </View>

      {/* Hourly Distribution */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Hourly Distribution</Text>
        <View style={styles.sectionCard}>
          <HourlyChart data={HOURLY_DATA} />
        </View>
      </View>

      {/* Export Button */}
      <TouchableOpacity style={styles.exportButton} activeOpacity={0.7}>
        <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
          <Path
            d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"
            stroke={colors.purple}
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </Svg>
        <Text style={styles.exportButtonText}>Export Report</Text>
      </TouchableOpacity>

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
    paddingHorizontal: GRID_PADDING,
    paddingBottom: 100,
  },

  // Header
  header: {
    paddingTop: 56,
    paddingBottom: 20,
  },
  headerTitle: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.3,
  },
  headerSubRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 6,
    gap: 8,
  },
  headerSubText: {
    fontSize: 14,
    color: colors.textSecondary,
  },
  pulseDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.green,
  },

  // Metrics Grid
  metricsGrid: {
    gap: CARD_GAP,
    marginBottom: 24,
  },
  metricsRow: {
    flexDirection: 'row',
    gap: CARD_GAP,
  },
  metricCard: {
    flex: 1,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 16,
    padding: CARD_PADDING,
  },
  metricCardWide: {
    flex: undefined,
    width: '100%',
  },
  metricLabel: {
    fontSize: 13,
    fontWeight: '500',
    color: colors.textSecondary,
    marginBottom: 8,
  },
  metricValueRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 4,
  },
  metricValue: {
    fontSize: 32,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  metricSuffix: {
    fontSize: 16,
    fontWeight: '500',
    color: colors.textTertiary,
  },
  trendBadge: {
    backgroundColor: colors.greenLight,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 2,
    marginLeft: 8,
  },
  trendText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.green,
  },
  sparkContainer: {
    marginTop: 12,
  },

  // Sections
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  sectionCard: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 16,
    padding: CARD_PADDING,
  },
  divider: {
    height: 1,
    backgroundColor: colors.border,
    marginVertical: 12,
  },

  // Gate Performance
  gateItem: {
    gap: 8,
  },
  gateHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  gateName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  gateStats: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  gatePercentage: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.purple,
  },
  gateScanCount: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  gateBarTrack: {
    height: 8,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 4,
    overflow: 'hidden',
  },
  gateBarFill: {
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.purple,
  },

  // Revenue Breakdown
  revenueRow: {
    gap: 8,
  },
  revenueHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  revenueNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  revenueDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  revenueName: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textPrimary,
  },
  revenueAmount: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  revenueBarTrack: {
    height: 6,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 3,
    overflow: 'hidden',
  },
  revenueBarFill: {
    height: 6,
    borderRadius: 3,
  },
  emptyText: {
    fontSize: 14,
    color: colors.textTertiary,
    textAlign: 'center',
    paddingVertical: 16,
  },

  // Hourly Distribution
  hourlyChart: {
    paddingTop: 8,
  },
  hourlyBars: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    gap: 8,
  },
  hourlyBarColumn: {
    flex: 1,
    alignItems: 'center',
    gap: 8,
  },
  hourlyBarTrack: {
    width: '100%',
    borderRadius: 6,
    justifyContent: 'flex-end',
    overflow: 'hidden',
  },
  hourlyBarFill: {
    width: '100%',
    borderRadius: 6,
    minHeight: 4,
  },
  hourlyLabel: {
    fontSize: 10,
    fontWeight: '500',
    color: colors.textTertiary,
  },

  // Export Button
  exportButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 10,
    backgroundColor: colors.purpleBg,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    borderRadius: 14,
    paddingVertical: 16,
    marginTop: 4,
  },
  exportButtonText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.purple,
  },

  bottomSpacer: {
    height: 20,
  },
});
