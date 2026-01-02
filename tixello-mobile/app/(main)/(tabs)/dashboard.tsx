import { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { Card, StatusBadge, Button } from '../../../src/components/ui';
import { useAuthStore } from '../../../src/stores/authStore';
import { useEventStore } from '../../../src/stores/eventStore';
import { useAppStore } from '../../../src/stores/appStore';
import { useCartStore } from '../../../src/stores/cartStore';
import { useCheckInStore } from '../../../src/stores/checkInStore';
import { eventsApi, reportsApi } from '../../../src/api';
import { colors, spacing, typography, borderRadius } from '../../../src/utils/theme';

export default function DashboardScreen() {
  const { user, tenant, canViewRevenue, canAccessSales, isStaff } = useAuthStore();
  const { selectedEvent, setSelectedEvent, events, setEvents, setLiveStats } = useEventStore();
  const { isOnline, shiftStartTime, notifications, setShowNotifications } = useAppStore();
  const { shiftCashCollected, shiftCardCollected } = useCartStore();
  const { scanHistory } = useCheckInStore();

  const [isRefreshing, setIsRefreshing] = useState(false);
  const [stats, setStats] = useState({
    checkedIn: 0,
    totalRevenue: 0,
    capacity: 0,
    ticketsSold: 0,
  });

  // Get staff scan stats from history
  const staffScansToday = scanHistory.filter(s => {
    const scanDate = new Date(s.scanned_at).toDateString();
    return scanDate === new Date().toDateString();
  }).length;
  const validScans = scanHistory.filter(s => s.status === 'valid').length;

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      // Load events
      const eventsResponse = await eventsApi.getDoorSalesEvents();
      if (eventsResponse.data) {
        setEvents(eventsResponse.data);
        if (!selectedEvent && eventsResponse.data.length > 0) {
          // Select first live or upcoming event
          const liveEvent = eventsResponse.data.find(e => e.status === 'live');
          setSelectedEvent(liveEvent || eventsResponse.data[0]);
        }
      }

      // Load dashboard stats
      const dashboardResponse = await reportsApi.getDashboard();
      if (dashboardResponse.data) {
        setStats({
          checkedIn: dashboardResponse.data.checked_in,
          totalRevenue: dashboardResponse.data.total_revenue,
          capacity: Math.round((dashboardResponse.data.checked_in / dashboardResponse.data.tickets_sold) * 100) || 0,
          ticketsSold: dashboardResponse.data.tickets_sold,
        });
      }
    } catch (error) {
      console.error('Error loading dashboard data:', error);
    }
  };

  const onRefresh = async () => {
    setIsRefreshing(true);
    await loadData();
    setIsRefreshing(false);
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('ro-RO').format(amount) + ' lei';
  };

  const unreadCount = notifications.filter(n => n.unread).length;

  return (
    <View style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <LinearGradient
            colors={[colors.primary, colors.primaryDark]}
            style={styles.logo}
          >
            <View style={styles.logoLines}>
              <View style={[styles.logoLine, { width: '100%' }]} />
              <View style={[styles.logoLine, { width: '75%' }]} />
              <View style={[styles.logoLine, { width: '50%' }]} />
            </View>
          </LinearGradient>
          <Text style={styles.brandName}>Tixello</Text>
        </View>
        <View style={styles.headerRight}>
          <StatusBadge status={isOnline ? 'online' : 'offline'} />
          <TouchableOpacity
            style={styles.notifButton}
            onPress={() => setShowNotifications(true)}
          >
            <Ionicons name="notifications" size={20} color={colors.textPrimary} />
            {unreadCount > 0 && (
              <View style={styles.notifBadge}>
                <Text style={styles.notifBadgeText}>{unreadCount}</Text>
              </View>
            )}
          </TouchableOpacity>
        </View>
      </View>

      {/* Event Selector */}
      {selectedEvent && (
        <TouchableOpacity style={styles.eventSelector}>
          <View style={styles.eventInfo}>
            <Text style={styles.eventName} numberOfLines={1}>
              {selectedEvent.title}
            </Text>
            <Text style={styles.eventMeta}>
              {selectedEvent.event_date} • {selectedEvent.venue?.name}
            </Text>
          </View>
          <StatusBadge status={selectedEvent.status} />
          <Ionicons name="chevron-down" size={20} color={colors.textMuted} />
        </TouchableOpacity>
      )}

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
        {!isStaff() ? (
          // Admin/Supervisor Dashboard - Full Stats
          <>
            {/* Stats Grid */}
            <View style={styles.statsGrid}>
              <Card variant="primary" style={styles.statCardPrimary}>
                <Text style={styles.statValue}>
                  {stats.checkedIn.toLocaleString()}
                </Text>
                <Text style={styles.statLabel}>Checked In</Text>
                <View style={styles.statTrend}>
                  <Ionicons name="trending-up" size={14} color={colors.success} />
                  <Text style={styles.trendText}>+45/min</Text>
                </View>
              </Card>

              {canViewRevenue() && (
                <Card style={styles.statCard}>
                  <Text style={styles.statValue}>{formatCurrency(stats.totalRevenue)}</Text>
                  <Text style={styles.statLabel}>Total Revenue</Text>
                </Card>
              )}

              <Card style={styles.statCard}>
                <Text style={styles.statValue}>{stats.capacity}%</Text>
                <Text style={styles.statLabel}>Capacity</Text>
              </Card>

              <Card style={styles.statCard}>
                <Text style={styles.statValue}>
                  {stats.ticketsSold.toLocaleString()}
                </Text>
                <Text style={styles.statLabel}>Tickets Sold</Text>
              </Card>
            </View>

            {/* Quick Actions */}
            <Text style={styles.sectionTitle}>Quick Actions</Text>
            <View style={styles.actionsGrid}>
              <TouchableOpacity
                style={styles.actionButton}
                onPress={() => router.push('/(main)/(tabs)/checkin')}
              >
                <View style={[styles.actionIcon, { backgroundColor: colors.primaryLight }]}>
                  <Ionicons name="camera" size={22} color={colors.primary} />
                </View>
                <Text style={styles.actionLabel}>Scan</Text>
              </TouchableOpacity>

              {canAccessSales() && (
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={() => router.push('/(main)/(tabs)/sales')}
                >
                  <View style={[styles.actionIcon, { backgroundColor: colors.successLight }]}>
                    <Ionicons name="cart" size={22} color={colors.success} />
                  </View>
                  <Text style={styles.actionLabel}>Sell</Text>
                </TouchableOpacity>
              )}

              <TouchableOpacity style={styles.actionButton}>
                <View style={[styles.actionIcon, { backgroundColor: colors.infoLight }]}>
                  <Ionicons name="clipboard" size={22} color={colors.info} />
                </View>
                <Text style={styles.actionLabel}>Guests</Text>
              </TouchableOpacity>

              <TouchableOpacity style={styles.actionButton}>
                <View style={[styles.actionIcon, { backgroundColor: colors.warningLight }]}>
                  <Ionicons name="people" size={22} color={colors.warning} />
                </View>
                <Text style={styles.actionLabel}>Staff</Text>
              </TouchableOpacity>
            </View>
          </>
        ) : (
          // Staff Dashboard (Scanner/POS) - Simplified View
          <>
            {/* Staff Stats Card */}
            <Card style={styles.shiftCard}>
              <View style={styles.shiftHeader}>
                <Ionicons name="stats-chart" size={20} color={colors.textPrimary} />
                <Text style={styles.shiftTitle}>Your Shift</Text>
                <Text style={styles.shiftTime}>Started {shiftStartTime || '—'}</Text>
              </View>
              <View style={styles.staffStatsGrid}>
                <View style={styles.staffStatItem}>
                  <Text style={styles.staffStatValue}>{staffScansToday}</Text>
                  <Text style={styles.staffStatLabel}>Scans Today</Text>
                </View>
                <View style={styles.staffStatDivider} />
                <View style={styles.staffStatItem}>
                  <Text style={[styles.staffStatValue, { color: colors.success }]}>{validScans}</Text>
                  <Text style={styles.staffStatLabel}>Valid</Text>
                </View>
                <View style={styles.staffStatDivider} />
                <View style={styles.staffStatItem}>
                  <Text style={[styles.staffStatValue, { color: colors.error }]}>{staffScansToday - validScans}</Text>
                  <Text style={styles.staffStatLabel}>Invalid</Text>
                </View>
              </View>
            </Card>

            {/* Shift Summary Card - only for POS role */}
            {canAccessSales() && (
              <Card style={styles.shiftCard}>
                <View style={styles.shiftHeader}>
                  <Ionicons name="cash" size={20} color={colors.textPrimary} />
                  <Text style={styles.shiftTitle}>Sales Summary</Text>
                </View>
                <View style={styles.shiftGrid}>
                  <View style={styles.shiftItem}>
                    <View style={[styles.shiftIcon, { backgroundColor: colors.successLight }]}>
                      <Ionicons name="cash-outline" size={24} color={colors.success} />
                    </View>
                    <View>
                      <Text style={styles.shiftItemLabel}>Cash to turn over</Text>
                      <Text style={[styles.shiftItemValue, { color: colors.success }]}>
                        {formatCurrency(shiftCashCollected)}
                      </Text>
                    </View>
                  </View>
                  <View style={styles.shiftItem}>
                    <View style={[styles.shiftIcon, { backgroundColor: colors.infoLight }]}>
                      <Ionicons name="card-outline" size={24} color={colors.info} />
                    </View>
                    <View>
                      <Text style={styles.shiftItemLabel}>Card payments</Text>
                      <Text style={[styles.shiftItemValue, { color: colors.info }]}>
                        {formatCurrency(shiftCardCollected)}
                      </Text>
                    </View>
                  </View>
                </View>
              </Card>
            )}

            {/* Quick Actions for Staff */}
            <View style={styles.staffActions}>
              <Button
                title="Scan Tickets"
                onPress={() => router.push('/(main)/(tabs)/checkin')}
                icon={<Ionicons name="camera" size={20} color="#fff" />}
                size="lg"
                style={styles.staffActionButton}
              />
              {canAccessSales() && (
                <Button
                  title="Sell Tickets"
                  onPress={() => router.push('/(main)/(tabs)/sales')}
                  icon={<Ionicons name="cart" size={20} color="#fff" />}
                  size="lg"
                  style={[styles.staffActionButton, { backgroundColor: colors.success }]}
                />
              )}
            </View>
          </>
        )}

        <View style={styles.bottomPadding} />
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingTop: 60,
    paddingBottom: spacing.lg,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderLight,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  headerRight: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  logo: {
    width: 32,
    height: 32,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
  },
  logoLines: {
    width: 18,
    height: 12,
    justifyContent: 'space-between',
  },
  logoLine: {
    height: 2,
    backgroundColor: '#fff',
    borderRadius: 1,
  },
  brandName: {
    fontSize: typography.fontSize.xl,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: -0.5,
  },
  notifButton: {
    width: 40,
    height: 40,
    borderRadius: borderRadius.md,
    backgroundColor: colors.backgroundCard,
    borderWidth: 1,
    borderColor: colors.border,
    justifyContent: 'center',
    alignItems: 'center',
  },
  notifBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: colors.error,
    justifyContent: 'center',
    alignItems: 'center',
  },
  notifBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  eventSelector: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing.md,
    backgroundColor: colors.primaryLight,
    borderBottomWidth: 1,
    borderBottomColor: colors.borderPrimary,
    gap: spacing.md,
  },
  eventInfo: {
    flex: 1,
  },
  eventName: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  eventMeta: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginTop: 2,
  },
  content: {
    flex: 1,
    padding: spacing.xl,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
    marginBottom: spacing.xxl,
  },
  statCardPrimary: {
    width: '100%',
  },
  statCard: {
    width: '48%',
    flexGrow: 1,
  },
  statValue: {
    fontSize: typography.fontSize.xxl,
    fontWeight: '700',
    color: colors.textPrimary,
    fontFamily: typography.fontFamily.mono,
  },
  statLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
    marginTop: spacing.xs,
  },
  statTrend: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  trendText: {
    fontSize: typography.fontSize.sm,
    fontWeight: '600',
    color: colors.success,
  },
  sectionTitle: {
    fontSize: typography.fontSize.md,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: spacing.md,
  },
  actionsGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    marginBottom: spacing.xxl,
  },
  actionButton: {
    flex: 1,
    alignItems: 'center',
    padding: spacing.lg,
    backgroundColor: colors.backgroundCard,
    borderRadius: borderRadius.xl,
    borderWidth: 1,
    borderColor: colors.borderLight,
  },
  actionIcon: {
    width: 44,
    height: 44,
    borderRadius: borderRadius.md,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: spacing.sm,
  },
  actionLabel: {
    fontSize: typography.fontSize.xs,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  shiftCard: {
    marginBottom: spacing.xxl,
  },
  shiftHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginBottom: spacing.lg,
  },
  shiftTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: '600',
    color: colors.textPrimary,
    flex: 1,
  },
  shiftTime: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  staffStatsGrid: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
  },
  staffStatItem: {
    alignItems: 'center',
    flex: 1,
  },
  staffStatValue: {
    fontSize: typography.fontSize.xxl,
    fontWeight: '700',
    color: colors.textPrimary,
    fontFamily: typography.fontFamily.mono,
  },
  staffStatLabel: {
    fontSize: typography.fontSize.xs,
    color: colors.textMuted,
    marginTop: spacing.xs,
  },
  staffStatDivider: {
    width: 1,
    height: 40,
    backgroundColor: colors.border,
  },
  shiftGrid: {
    gap: spacing.md,
  },
  shiftItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
  },
  shiftIcon: {
    width: 48,
    height: 48,
    borderRadius: borderRadius.md,
    justifyContent: 'center',
    alignItems: 'center',
  },
  shiftItemLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.textMuted,
  },
  shiftItemValue: {
    fontSize: typography.fontSize.xl,
    fontWeight: '700',
    fontFamily: typography.fontFamily.mono,
  },
  staffActions: {
    gap: spacing.md,
  },
  staffActionButton: {
    width: '100%',
  },
  bottomPadding: {
    height: 100,
  },
});
