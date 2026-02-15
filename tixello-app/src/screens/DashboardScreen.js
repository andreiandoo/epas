import React, { useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { useEvent } from '../context/EventContext';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../utils/formatCurrency';
import { colors } from '../theme/colors';

// ---------------------------------------------------------------------------
// Admin Dashboard
// ---------------------------------------------------------------------------

function ReportsOnlyBanner() {
  return (
    <View style={styles.reportsBanner}>
      <Ionicons name="alert-circle" size={20} color={colors.amber} />
      <Text style={styles.reportsBannerText}>
        This event has ended. Only reports are available.
      </Text>
    </View>
  );
}

function ReportsStatsGrid({ stats }) {
  const totalSold = stats?.total_sold ?? 0;
  const checkedIn = stats?.checked_in ?? 0;
  const revenue = stats?.revenue ?? 0;
  const checkInRate =
    totalSold > 0 ? ((checkedIn / totalSold) * 100).toFixed(1) : '0.0';

  const cards = [
    {
      label: 'Total Sold',
      value: totalSold.toLocaleString(),
      icon: 'ticket-outline',
      color: colors.purple,
      bg: colors.purpleBg,
      border: colors.purpleBorder,
    },
    {
      label: 'Checked In',
      value: checkedIn.toLocaleString(),
      icon: 'checkmark-circle-outline',
      color: colors.green,
      bg: colors.greenBg,
      border: colors.greenBorder,
    },
    {
      label: 'Revenue',
      value: formatCurrency(revenue),
      icon: 'cash-outline',
      color: colors.cyan,
      bg: colors.cyanBg,
      border: colors.cyanBorder,
    },
    {
      label: 'Check-in Rate',
      value: `${checkInRate}%`,
      icon: 'analytics-outline',
      color: colors.amber,
      bg: colors.amberBg,
      border: colors.amberBorder,
    },
  ];

  return (
    <View style={styles.reportsGrid}>
      {cards.map((card) => (
        <View
          key={card.label}
          style={[styles.reportsCard, { backgroundColor: card.bg, borderColor: card.border }]}
        >
          <Ionicons name={card.icon} size={22} color={card.color} />
          <Text style={[styles.reportsCardValue, { color: card.color }]}>
            {card.value}
          </Text>
          <Text style={styles.reportsCardLabel}>{card.label}</Text>
        </View>
      ))}
    </View>
  );
}

function AdminLiveStats({ stats }) {
  const totalSold = stats?.total_sold ?? 0;
  const checkedIn = stats?.checked_in ?? 0;
  const revenue = stats?.revenue ?? 0;
  const capacity = stats?.capacity ?? 0;
  const remaining = totalSold > 0 ? totalSold - checkedIn : 0;
  const capacityPct = capacity > 0 ? ((totalSold / capacity) * 100).toFixed(1) : '0.0';
  const checkedInPct = totalSold > 0 ? ((checkedIn / totalSold) * 100).toFixed(1) : '0.0';
  const barWidth = totalSold > 0 ? Math.min((checkedIn / totalSold) * 100, 100) : 0;

  return (
    <View style={styles.statsSection}>
      {/* Primary card - Checked In (full width) */}
      <View style={styles.primaryStatCard}>
        <View style={styles.primaryStatHeader}>
          <View style={styles.primaryStatIconWrap}>
            <Ionicons name="people" size={20} color={colors.purple} />
          </View>
          <Text style={styles.primaryStatLabel}>Checked In</Text>
          <View style={styles.trendBadge}>
            <Ionicons name="trending-up" size={12} color={colors.green} />
            <Text style={styles.trendText}>{checkedInPct}%</Text>
          </View>
        </View>
        <Text style={styles.primaryStatValue}>
          {checkedIn.toLocaleString()}
          <Text style={styles.primaryStatTotal}> / {totalSold.toLocaleString()}</Text>
        </Text>
        <View style={styles.capacityBarBg}>
          <View style={[styles.capacityBarFill, { width: `${barWidth}%` }]} />
        </View>
      </View>

      {/* Secondary stats (2 columns) */}
      <View style={styles.statsGrid}>
        <View style={[styles.statCard, { borderColor: colors.greenBorder }]}>
          <Ionicons name="ticket-outline" size={18} color={colors.green} />
          <Text style={styles.statCardValue}>{totalSold.toLocaleString()}</Text>
          <Text style={styles.statCardLabel}>Sold</Text>
        </View>
        <View style={[styles.statCard, { borderColor: colors.cyanBorder }]}>
          <Ionicons name="cash-outline" size={18} color={colors.cyan} />
          <Text style={styles.statCardValue}>{formatCurrency(revenue)}</Text>
          <Text style={styles.statCardLabel}>Revenue</Text>
        </View>
        <View style={[styles.statCard, { borderColor: colors.amberBorder }]}>
          <Ionicons name="hourglass-outline" size={18} color={colors.amber} />
          <Text style={styles.statCardValue}>{remaining.toLocaleString()}</Text>
          <Text style={styles.statCardLabel}>Remaining</Text>
        </View>
        <View style={[styles.statCard, { borderColor: colors.purpleBorder }]}>
          <Ionicons name="speedometer-outline" size={18} color={colors.purple} />
          <Text style={styles.statCardValue}>{capacityPct}%</Text>
          <Text style={styles.statCardLabel}>Capacity</Text>
        </View>
      </View>
    </View>
  );
}

function QuickActions({ navigation }) {
  const actions = [
    {
      key: 'scan',
      label: 'Scan',
      icon: 'qr-code-outline',
      color: colors.purple,
      bg: colors.purpleBg,
      border: colors.purpleBorder,
      route: 'CheckIn',
    },
    {
      key: 'sell',
      label: 'Sell',
      icon: 'cart-outline',
      color: colors.green,
      bg: colors.greenBg,
      border: colors.greenBorder,
      route: 'Sales',
    },
    {
      key: 'guests',
      label: 'Guest List',
      icon: 'list-outline',
      color: colors.cyan,
      bg: colors.cyanBg,
      border: colors.cyanBorder,
      route: 'GuestList',
    },
    {
      key: 'staff',
      label: 'Staff',
      icon: 'people-outline',
      color: colors.amber,
      bg: colors.amberBg,
      border: colors.amberBorder,
      route: 'Staff',
    },
  ];

  return (
    <View style={styles.quickActionsSection}>
      <Text style={styles.sectionTitle}>Quick Actions</Text>
      <View style={styles.quickActionsGrid}>
        {actions.map((action) => (
          <TouchableOpacity
            key={action.key}
            style={[
              styles.quickActionBtn,
              { backgroundColor: action.bg, borderColor: action.border },
            ]}
            onPress={() => navigation.navigate(action.route)}
            activeOpacity={0.7}
          >
            <Ionicons name={action.icon} size={24} color={action.color} />
            <Text style={[styles.quickActionLabel, { color: action.color }]}>
              {action.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    </View>
  );
}

function RecentActivity({ recentScans }) {
  if (!recentScans || recentScans.length === 0) {
    return (
      <View style={styles.recentSection}>
        <Text style={styles.sectionTitle}>Recent Activity</Text>
        <View style={styles.emptyState}>
          <Ionicons name="time-outline" size={32} color={colors.textQuaternary} />
          <Text style={styles.emptyStateText}>No recent activity</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.recentSection}>
      <Text style={styles.sectionTitle}>Recent Activity</Text>
      {recentScans.slice(0, 10).map((scan, index) => (
        <View
          key={scan.id ?? `scan-${index}`}
          style={[
            styles.activityItem,
            index < recentScans.length - 1 && styles.activityItemBorder,
          ]}
        >
          <View
            style={[
              styles.activityDot,
              {
                backgroundColor:
                  scan.status === 'valid' ? colors.green : colors.red,
              },
            ]}
          />
          <View style={styles.activityContent}>
            <Text style={styles.activityName} numberOfLines={1}>
              {scan.name || scan.ticket_holder || 'Unknown'}
            </Text>
            <Text style={styles.activityMeta}>
              {scan.ticket_type || 'Ticket'} - {scan.time || 'Just now'}
            </Text>
          </View>
          <Ionicons
            name={scan.status === 'valid' ? 'checkmark-circle' : 'close-circle'}
            size={18}
            color={scan.status === 'valid' ? colors.green : colors.red}
          />
        </View>
      ))}
    </View>
  );
}

function AdminDashboard({ navigation, eventStats, isReportsOnlyMode, recentScans }) {
  return (
    <>
      {isReportsOnlyMode && <ReportsOnlyBanner />}

      {isReportsOnlyMode ? (
        <ReportsStatsGrid stats={eventStats} />
      ) : (
        <>
          <AdminLiveStats stats={eventStats} />
          <QuickActions navigation={navigation} />
          <RecentActivity recentScans={recentScans} />
        </>
      )}
    </>
  );
}

// ---------------------------------------------------------------------------
// Scanner Dashboard
// ---------------------------------------------------------------------------

function TurnoverCard({ cashTurnover, cardTurnover }) {
  return (
    <View style={styles.turnoverCard}>
      <Text style={styles.turnoverTitle}>Turnover</Text>
      <View style={styles.turnoverRow}>
        <View style={styles.turnoverItem}>
          <View style={[styles.turnoverIconWrap, { backgroundColor: colors.greenBg }]}>
            <Ionicons name="cash-outline" size={20} color={colors.green} />
          </View>
          <View>
            <Text style={styles.turnoverLabel}>Cash</Text>
            <Text style={[styles.turnoverAmount, { color: colors.green }]}>
              {formatCurrency(cashTurnover)}
            </Text>
          </View>
        </View>
        <View style={styles.turnoverDivider} />
        <View style={styles.turnoverItem}>
          <View style={[styles.turnoverIconWrap, { backgroundColor: colors.cyanBg }]}>
            <Ionicons name="card-outline" size={20} color={colors.cyan} />
          </View>
          <View>
            <Text style={styles.turnoverLabel}>Card</Text>
            <Text style={[styles.turnoverAmount, { color: colors.cyan }]}>
              {formatCurrency(cardTurnover)}
            </Text>
          </View>
        </View>
      </View>
    </View>
  );
}

function ScannerStats({ myScans, mySales, shiftStartTime }) {
  const shiftDuration = useMemo(() => {
    if (!shiftStartTime) return '--:--';
    const now = new Date();
    const diff = Math.floor((now - new Date(shiftStartTime)) / 1000);
    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
  }, [shiftStartTime]);

  const statItems = [
    {
      label: 'My Scans',
      value: myScans.toLocaleString(),
      icon: 'scan-outline',
      color: colors.purple,
      bg: colors.purpleBg,
      border: colors.purpleBorder,
    },
    {
      label: 'My Sales',
      value: mySales.toLocaleString(),
      icon: 'cart-outline',
      color: colors.green,
      bg: colors.greenBg,
      border: colors.greenBorder,
    },
    {
      label: 'Shift Duration',
      value: shiftDuration,
      icon: 'time-outline',
      color: colors.amber,
      bg: colors.amberBg,
      border: colors.amberBorder,
    },
  ];

  return (
    <View style={styles.scannerStatsGrid}>
      {statItems.map((item) => (
        <View
          key={item.label}
          style={[styles.scannerStatCard, { backgroundColor: item.bg, borderColor: item.border }]}
        >
          <Ionicons name={item.icon} size={20} color={item.color} />
          <Text style={[styles.scannerStatValue, { color: item.color }]}>
            {item.value}
          </Text>
          <Text style={styles.scannerStatLabel}>{item.label}</Text>
        </View>
      ))}
    </View>
  );
}

function ScannerDashboard({ navigation, cashTurnover, cardTurnover, myScans, mySales, shiftStartTime }) {
  return (
    <>
      <TurnoverCard cashTurnover={cashTurnover} cardTurnover={cardTurnover} />

      <ScannerStats
        myScans={myScans}
        mySales={mySales}
        shiftStartTime={shiftStartTime}
      />

      <View style={styles.scannerActions}>
        <TouchableOpacity
          style={styles.scannerActionBtnPurple}
          onPress={() => navigation.navigate('CheckIn')}
          activeOpacity={0.8}
        >
          <Ionicons name="qr-code-outline" size={28} color={colors.white} />
          <Text style={styles.scannerActionBtnText}>Start Scanning</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.scannerActionBtnGreen}
          onPress={() => navigation.navigate('Sales')}
          activeOpacity={0.8}
        >
          <Ionicons name="cart-outline" size={28} color={colors.white} />
          <Text style={styles.scannerActionBtnText}>Start Selling</Text>
        </TouchableOpacity>
      </View>
    </>
  );
}

// ---------------------------------------------------------------------------
// Main Screen
// ---------------------------------------------------------------------------

export default function DashboardScreen({ navigation }) {
  const { userRole } = useAuth();
  const { selectedEvent, eventStats, isReportsOnlyMode } = useEvent();
  const {
    shiftStartTime,
    cashTurnover,
    cardTurnover,
    myScans,
    mySales,
    recentScans,
  } = useApp();

  const isAdmin = userRole === 'admin';

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.contentContainer}
      showsVerticalScrollIndicator={false}
    >
      {/* Event header */}
      <View style={styles.eventHeader}>
        <Text style={styles.eventName} numberOfLines={1}>
          {selectedEvent?.name || 'No Event Selected'}
        </Text>
        {selectedEvent && (
          <Text style={styles.eventMeta}>
            {selectedEvent.venue_name || selectedEvent.location || ''}
          </Text>
        )}
      </View>

      {isAdmin ? (
        <AdminDashboard
          navigation={navigation}
          eventStats={eventStats}
          isReportsOnlyMode={isReportsOnlyMode}
          recentScans={recentScans}
        />
      ) : (
        <ScannerDashboard
          navigation={navigation}
          cashTurnover={cashTurnover}
          cardTurnover={cardTurnover}
          myScans={myScans}
          mySales={mySales}
          shiftStartTime={shiftStartTime}
        />
      )}
    </ScrollView>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: colors.background,
  },
  contentContainer: {
    padding: 16,
    paddingBottom: 32,
  },

  // Event header
  eventHeader: {
    marginBottom: 20,
  },
  eventName: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  eventMeta: {
    fontSize: 13,
    color: colors.textSecondary,
    marginTop: 4,
  },

  // Reports-only banner
  reportsBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.amberBg,
    borderWidth: 1,
    borderColor: colors.amberBorder,
    borderRadius: 12,
    padding: 14,
    marginBottom: 20,
    gap: 10,
  },
  reportsBannerText: {
    flex: 1,
    fontSize: 13,
    fontWeight: '500',
    color: colors.amber,
  },

  // Reports stats grid (2x2)
  reportsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  reportsCard: {
    width: '47.5%',
    borderWidth: 1,
    borderRadius: 14,
    padding: 16,
    alignItems: 'center',
    gap: 8,
  },
  reportsCardValue: {
    fontSize: 22,
    fontWeight: '700',
  },
  reportsCardLabel: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: '500',
  },

  // Admin live stats
  statsSection: {
    marginBottom: 20,
  },
  primaryStatCard: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    borderRadius: 16,
    padding: 18,
    marginBottom: 12,
  },
  primaryStatHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  primaryStatIconWrap: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: colors.purpleBg,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 10,
  },
  primaryStatLabel: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  trendBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.greenBg,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 3,
    gap: 4,
  },
  trendText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.green,
  },
  primaryStatValue: {
    fontSize: 28,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 14,
  },
  primaryStatTotal: {
    fontSize: 20,
    fontWeight: '500',
    color: colors.textTertiary,
  },
  capacityBarBg: {
    height: 6,
    backgroundColor: colors.border,
    borderRadius: 3,
    overflow: 'hidden',
  },
  capacityBarFill: {
    height: 6,
    backgroundColor: colors.purple,
    borderRadius: 3,
  },

  // Stats grid (2 columns)
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  statCard: {
    width: '48%',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    gap: 6,
  },
  statCardValue: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  statCardLabel: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: '500',
  },

  // Quick actions
  quickActionsSection: {
    marginTop: 24,
    marginBottom: 20,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  quickActionsGrid: {
    flexDirection: 'row',
    gap: 10,
  },
  quickActionBtn: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 14,
    paddingVertical: 16,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  quickActionLabel: {
    fontSize: 11,
    fontWeight: '600',
  },

  // Recent activity
  recentSection: {
    marginBottom: 8,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 32,
    gap: 8,
  },
  emptyStateText: {
    fontSize: 13,
    color: colors.textQuaternary,
  },
  activityItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    gap: 12,
  },
  activityItemBorder: {
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  activityDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  activityContent: {
    flex: 1,
  },
  activityName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  activityMeta: {
    fontSize: 12,
    color: colors.textTertiary,
    marginTop: 2,
  },

  // Scanner: Turnover card
  turnoverCard: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    borderRadius: 16,
    padding: 18,
    marginBottom: 20,
  },
  turnoverTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 16,
  },
  turnoverRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  turnoverItem: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  turnoverIconWrap: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  turnoverLabel: {
    fontSize: 12,
    color: colors.textSecondary,
    marginBottom: 2,
  },
  turnoverAmount: {
    fontSize: 18,
    fontWeight: '700',
  },
  turnoverDivider: {
    width: 1,
    height: 36,
    backgroundColor: colors.border,
    marginHorizontal: 12,
  },

  // Scanner stats (3 columns)
  scannerStatsGrid: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 24,
  },
  scannerStatCard: {
    flex: 1,
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    alignItems: 'center',
    gap: 6,
  },
  scannerStatValue: {
    fontSize: 18,
    fontWeight: '700',
  },
  scannerStatLabel: {
    fontSize: 11,
    color: colors.textSecondary,
    fontWeight: '500',
    textAlign: 'center',
  },

  // Scanner action buttons
  scannerActions: {
    gap: 12,
  },
  scannerActionBtnPurple: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.purple,
    borderRadius: 16,
    paddingVertical: 20,
    gap: 12,
  },
  scannerActionBtnGreen: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.green,
    borderRadius: 16,
    paddingVertical: 20,
    gap: 12,
  },
  scannerActionBtnText: {
    fontSize: 17,
    fontWeight: '700',
    color: colors.white,
  },
});
