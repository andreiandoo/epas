import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  RefreshControl,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Card, Button } from '../../../src/components/ui';
import { useEventStore } from '../../../src/stores/eventStore';
import { reportsApi } from '../../../src/api';
import { colors, spacing, typography, borderRadius } from '../../../src/utils/theme';

export default function ReportsScreen() {
  const { selectedEvent } = useEventStore();
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [stats, setStats] = useState({
    revenue: 0,
    ticketsSold: 0,
    checkedIn: 0,
    checkInRate: 0,
    checkInsPerMinute: 45,
    salesPerMinute: 12,
  });
  const [gateStats, setGateStats] = useState([
    { name: 'Gate A', scans: 1234, percent: 68 },
    { name: 'Gate B', scans: 1567, percent: 82 },
    { name: 'Gate C', scans: 856, percent: 45 },
    { name: 'VIP', scans: 234, percent: 23 },
  ]);
  const [revenueBreakdown, setRevenueBreakdown] = useState([
    { type: 'General Admission', amount: 2450000, percent: 60, color: '#8B5CF6' },
    { type: 'VIP Access', amount: 1200000, percent: 30, color: '#F59E0B' },
    { type: 'Student', amount: 400000, percent: 10, color: '#06B6D4' },
  ]);
  const [hourlyData, setHourlyData] = useState([
    { hour: '18:00', value: 45 },
    { hour: '19:00', value: 78 },
    { hour: '20:00', value: 95 },
    { hour: '21:00', value: 82 },
    { hour: '22:00', value: 65 },
    { hour: '23:00', value: 40 },
  ]);

  useEffect(() => {
    loadReports();
  }, [selectedEvent]);

  const loadReports = async () => {
    try {
      const dashboardResponse = await reportsApi.getDashboard();
      if (dashboardResponse.data) {
        const data = dashboardResponse.data;
        setStats({
          revenue: data.total_revenue || 0,
          ticketsSold: data.tickets_sold || 0,
          checkedIn: data.checked_in || 0,
          checkInRate: data.check_in_rate || 0,
          checkInsPerMinute: 45,
          salesPerMinute: 12,
        });
      }

      if (selectedEvent) {
        const timelineResponse = await reportsApi.getTimeline({ event_id: selectedEvent.id });
        // Process timeline data if available
      }
    } catch (error) {
      console.error('Error loading reports:', error);
    }
  };

  const onRefresh = async () => {
    setIsRefreshing(true);
    await loadReports();
    setIsRefreshing(false);
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ro-RO').format(amount) + ' lei';
  };

  const maxHourlyValue = Math.max(...hourlyData.map(d => d.value));

  return (
    <View style={styles.container}>
      <ScrollView
        style={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        }
      >
        {/* Header */}
        <View style={styles.header}>
          <View style={styles.headerTitle}>
            <Ionicons name="bar-chart" size={20} color={colors.textPrimary} />
            <Text style={styles.title}>Live Reports</Text>
          </View>
          <View style={styles.liveIndicator}>
            <View style={styles.pulseDot} />
            <Text style={styles.liveText}>Real-time</Text>
          </View>
        </View>

        {/* Key Metrics */}
        <View style={styles.metricsGrid}>
          <Card style={styles.metricCard}>
            <View style={[styles.metricIcon, { backgroundColor: colors.successLight }]}>
              <Ionicons name="cash" size={20} color={colors.success} />
            </View>
            <Text style={styles.metricValue}>{formatCurrency(stats.revenue)}</Text>
            <Text style={styles.metricLabel}>Total Revenue</Text>
            <Text style={styles.metricTrend}>
              <Ionicons name="trending-up" size={12} color={colors.success} /> +12%
            </Text>
          </Card>

          <Card style={styles.metricCard}>
            <View style={[styles.metricIcon, { backgroundColor: colors.primaryLight }]}>
              <Ionicons name="ticket" size={20} color={colors.primary} />
            </View>
            <Text style={styles.metricValue}>{stats.ticketsSold.toLocaleString()}</Text>
            <Text style={styles.metricLabel}>Tickets Sold</Text>
          </Card>

          <Card style={styles.metricCard}>
            <View style={[styles.metricIcon, { backgroundColor: colors.infoLight }]}>
              <Ionicons name="people" size={20} color={colors.info} />
            </View>
            <Text style={styles.metricValue}>{stats.checkedIn.toLocaleString()}</Text>
            <Text style={styles.metricLabel}>Checked In</Text>
          </Card>

          <Card style={styles.metricCard}>
            <View style={[styles.metricIcon, { backgroundColor: colors.warningLight }]}>
              <Ionicons name="speedometer" size={20} color={colors.warning} />
            </View>
            <Text style={styles.metricValue}>{stats.checkInRate}%</Text>
            <Text style={styles.metricLabel}>Check-in Rate</Text>
          </Card>
        </View>

        {/* Live Stats */}
        <Card style={styles.liveStatsCard}>
          <Text style={styles.sectionTitle}>Live Activity</Text>
          <View style={styles.liveStatsRow}>
            <View style={styles.liveStat}>
              <Text style={styles.liveStatValue}>{stats.checkInsPerMinute}</Text>
              <Text style={styles.liveStatLabel}>Check-ins/min</Text>
            </View>
            <View style={styles.statDivider} />
            <View style={styles.liveStat}>
              <Text style={styles.liveStatValue}>{stats.salesPerMinute}</Text>
              <Text style={styles.liveStatLabel}>Sales/min</Text>
            </View>
          </View>
        </Card>

        {/* Gate Performance */}
        <Card style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Gate Performance</Text>
          {gateStats.map((gate) => (
            <View key={gate.name} style={styles.gateItem}>
              <View style={styles.gateInfo}>
                <Text style={styles.gateName}>{gate.name}</Text>
                <Text style={styles.gateScans}>{gate.scans.toLocaleString()} scans</Text>
              </View>
              <View style={styles.gateBarContainer}>
                <View
                  style={[styles.gateBar, { width: `${gate.percent}%` }]}
                />
              </View>
              <Text style={styles.gatePercent}>{gate.percent}%</Text>
            </View>
          ))}
        </Card>

        {/* Revenue Breakdown */}
        <Card style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Revenue by Ticket Type</Text>
          {revenueBreakdown.map((item) => (
            <View key={item.type} style={styles.revenueItem}>
              <View style={styles.revenueLabel}>
                <View
                  style={[styles.revenueDot, { backgroundColor: item.color }]}
                />
                <Text style={styles.revenueType}>{item.type}</Text>
              </View>
              <View style={styles.revenueBarContainer}>
                <View
                  style={[
                    styles.revenueBar,
                    { width: `${item.percent}%`, backgroundColor: item.color },
                  ]}
                />
              </View>
              <Text style={styles.revenueAmount}>{formatCurrency(item.amount)}</Text>
            </View>
          ))}
        </Card>

        {/* Hourly Chart */}
        <Card style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Check-ins by Hour</Text>
          <View style={styles.hourlyChart}>
            {hourlyData.map((item) => (
              <View key={item.hour} style={styles.hourBar}>
                <View
                  style={[
                    styles.hourBarFill,
                    { height: `${(item.value / maxHourlyValue) * 100}%` },
                  ]}
                />
                <Text style={styles.hourLabel}>{item.hour.split(':')[0]}</Text>
              </View>
            ))}
          </View>
        </Card>

        {/* Export Button */}
        <Button
          title="Export Report"
          onPress={() => {}}
          variant="secondary"
          icon={<Ionicons name="download" size={20} color={colors.textPrimary} />}
          style={styles.exportButton}
        />

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
    paddingTop: 60,
  },
  content: {
    flex: 1,
    padding: spacing.xl,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xl,
  },
  headerTitle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  liveIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
  },
  pulseDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.success,
  },
  liveText: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  metricsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
    marginBottom: spacing.xxl,
  },
  metricCard: {
    width: '48%',
    flexGrow: 1,
  },
  metricIcon: {
    width: 40,
    height: 40,
    borderRadius: borderRadius.md,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  metricValue: {
    fontSize: typography.fontSize.xxl,
    fontWeight: '700',
    color: colors.textPrimary,
    fontFamily: typography.fontFamily.mono,
  },
  metricLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginTop: spacing.xs,
  },
  metricTrend: {
    fontSize: typography.fontSize.sm,
    color: colors.success,
    marginTop: spacing.sm,
  },
  liveStatsCard: {
    marginBottom: spacing.xl,
  },
  liveStatsRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
  },
  liveStat: {
    alignItems: 'center',
  },
  liveStatValue: {
    fontSize: 32,
    fontWeight: '700',
    color: colors.primary,
    fontFamily: typography.fontFamily.mono,
  },
  liveStatLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginTop: spacing.xs,
  },
  statDivider: {
    width: 1,
    height: 40,
    backgroundColor: colors.border,
  },
  sectionCard: {
    marginBottom: spacing.xl,
  },
  sectionTitle: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: spacing.lg,
  },
  gateItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  gateInfo: {
    width: 100,
  },
  gateName: {
    fontSize: typography.fontSize.md,
    fontWeight: '500',
    color: colors.textPrimary,
  },
  gateScans: {
    fontSize: typography.fontSize.xs,
    color: colors.textMuted,
  },
  gateBarContainer: {
    flex: 1,
    height: 8,
    backgroundColor: colors.border,
    borderRadius: 4,
    overflow: 'hidden',
    marginHorizontal: spacing.md,
  },
  gateBar: {
    height: '100%',
    backgroundColor: colors.primary,
    borderRadius: 4,
  },
  gatePercent: {
    fontSize: typography.fontSize.sm,
    fontWeight: '600',
    color: colors.textSecondary,
    width: 40,
    textAlign: 'right',
    fontFamily: typography.fontFamily.mono,
  },
  revenueItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  revenueLabel: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    width: 140,
  },
  revenueDot: {
    width: 10,
    height: 10,
    borderRadius: 3,
  },
  revenueType: {
    fontSize: typography.fontSize.sm,
    color: colors.textPrimary,
  },
  revenueBarContainer: {
    flex: 1,
    height: 6,
    backgroundColor: colors.border,
    borderRadius: 3,
    overflow: 'hidden',
    marginHorizontal: spacing.md,
  },
  revenueBar: {
    height: '100%',
    borderRadius: 3,
  },
  revenueAmount: {
    fontSize: typography.fontSize.sm,
    color: colors.textSecondary,
    width: 90,
    textAlign: 'right',
    fontFamily: typography.fontFamily.mono,
  },
  hourlyChart: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    height: 120,
  },
  hourBar: {
    flex: 1,
    alignItems: 'center',
    height: '100%',
    justifyContent: 'flex-end',
  },
  hourBarFill: {
    width: 24,
    backgroundColor: colors.primary,
    borderTopLeftRadius: 4,
    borderTopRightRadius: 4,
    minHeight: 4,
  },
  hourLabel: {
    fontSize: typography.fontSize.xs,
    color: colors.textMuted,
    marginTop: spacing.sm,
  },
  exportButton: {
    marginTop: spacing.md,
  },
  bottomPadding: {
    height: 100,
  },
});
