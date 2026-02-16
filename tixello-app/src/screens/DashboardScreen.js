import React, { useMemo } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import Svg, { Path, Circle, Line, Rect, Polyline } from 'react-native-svg';
import { useAuth } from '../context/AuthContext';
import { useEvent } from '../context/EventContext';
import { useApp } from '../context/AppContext';
import { formatCurrency } from '../utils/formatCurrency';
import { colors } from '../theme/colors';

// ---------------------------------------------------------------------------
// SVG Icon component (replaces Ionicons)
// ---------------------------------------------------------------------------

function Icon({ name, size = 20, color = '#fff' }) {
  const s = size;
  switch (name) {
    case 'alert-circle':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Circle cx="12" cy="12" r="10" />
          <Line x1="12" y1="8" x2="12" y2="12" />
          <Line x1="12" y1="16" x2="12.01" y2="16" />
        </Svg>
      );
    case 'ticket':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
          <Path d="M13 5v2M13 17v2M13 11v2" />
        </Svg>
      );
    case 'check-circle':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
          <Polyline points="22 4 12 14.01 9 11.01" />
        </Svg>
      );
    case 'cash':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Line x1="12" y1="1" x2="12" y2="23" />
          <Path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </Svg>
      );
    case 'chart':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M18 20V10M12 20V4M6 20v-6" />
        </Svg>
      );
    case 'people':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <Circle cx="9" cy="7" r="4" />
          <Path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <Path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </Svg>
      );
    case 'trending-up':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
          <Polyline points="17 6 23 6 23 12" />
        </Svg>
      );
    case 'hourglass':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M5 22h14M5 2h14M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" />
        </Svg>
      );
    case 'speedometer':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Z" />
          <Path d="M12 12l4-4" />
          <Circle cx="12" cy="12" r="1" />
        </Svg>
      );
    case 'qr-code':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Rect x="3" y="3" width="7" height="7" />
          <Rect x="14" y="3" width="7" height="7" />
          <Rect x="3" y="14" width="7" height="7" />
          <Rect x="14" y="14" width="3" height="3" />
          <Line x1="21" y1="14" x2="21" y2="14.01" />
          <Line x1="21" y1="21" x2="21" y2="21.01" />
          <Line x1="17" y1="21" x2="17" y2="21.01" />
        </Svg>
      );
    case 'cart':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Circle cx="9" cy="21" r="1" />
          <Circle cx="20" cy="21" r="1" />
          <Path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
        </Svg>
      );
    case 'list':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Line x1="8" y1="6" x2="21" y2="6" />
          <Line x1="8" y1="12" x2="21" y2="12" />
          <Line x1="8" y1="18" x2="21" y2="18" />
          <Line x1="3" y1="6" x2="3.01" y2="6" />
          <Line x1="3" y1="12" x2="3.01" y2="12" />
          <Line x1="3" y1="18" x2="3.01" y2="18" />
        </Svg>
      );
    case 'clock':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Circle cx="12" cy="12" r="10" />
          <Polyline points="12 6 12 12 16 14" />
        </Svg>
      );
    case 'x-circle':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Circle cx="12" cy="12" r="10" />
          <Line x1="15" y1="9" x2="9" y2="15" />
          <Line x1="9" y1="9" x2="15" y2="15" />
        </Svg>
      );
    case 'scan':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2" />
          <Line x1="7" y1="12" x2="17" y2="12" />
        </Svg>
      );
    case 'credit-card':
      return (
        <Svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <Rect x="1" y="4" width="22" height="16" rx="2" ry="2" />
          <Line x1="1" y1="10" x2="23" y2="10" />
        </Svg>
      );
    default:
      return <View style={{ width: s, height: s }} />;
  }
}

// ---------------------------------------------------------------------------
// Admin Dashboard
// ---------------------------------------------------------------------------

function ReportsOnlyBanner() {
  return (
    <View style={styles.reportsBanner}>
      <Icon name="alert-circle" size={20} color={colors.amber} />
      <Text style={styles.reportsBannerText}>
        Acest eveniment s-a încheiat. Doar rapoartele sunt disponibile.
      </Text>
    </View>
  );
}

function ReportsStatsGrid({ stats }) {
  const totalSold = stats?.total_sold ?? 0;
  const checkedIn = stats?.checked_in ?? 0;
  const revenue = stats?.revenue ?? 0;
  const checkInRate = stats?.check_in_rate?.toFixed(1) ??
    (totalSold > 0 ? ((checkedIn / totalSold) * 100).toFixed(1) : '0.0');

  const cards = [
    {
      label: 'Total Vândute',
      value: totalSold.toLocaleString(),
      icon: 'ticket',
      color: colors.purple,
      bg: colors.purpleBg,
      border: colors.purpleBorder,
    },
    {
      label: 'Intrați',
      value: checkedIn.toLocaleString(),
      icon: 'check-circle',
      color: colors.green,
      bg: colors.greenBg,
      border: colors.greenBorder,
    },
    {
      label: 'Venituri',
      value: formatCurrency(revenue),
      icon: 'cash',
      color: colors.cyan,
      bg: colors.cyanBg,
      border: colors.cyanBorder,
    },
    {
      label: 'Rata Check-in',
      value: `${checkInRate}%`,
      icon: 'chart',
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
          <Icon name={card.icon} size={22} color={card.color} />
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
            <Icon name="people" size={20} color={colors.purple} />
          </View>
          <Text style={styles.primaryStatLabel}>Intrați</Text>
          <View style={styles.trendBadge}>
            <Icon name="trending-up" size={12} color={colors.green} />
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
          <Icon name="ticket" size={18} color={colors.green} />
          <Text style={styles.statCardValue}>{totalSold.toLocaleString()}</Text>
          <Text style={styles.statCardLabel}>Vândute</Text>
        </View>
        <View style={[styles.statCard, { borderColor: colors.cyanBorder }]}>
          <Icon name="cash" size={18} color={colors.cyan} />
          <Text style={styles.statCardValue}>{formatCurrency(revenue)}</Text>
          <Text style={styles.statCardLabel}>Venituri</Text>
        </View>
        <View style={[styles.statCard, { borderColor: colors.amberBorder }]}>
          <Icon name="hourglass" size={18} color={colors.amber} />
          <Text style={styles.statCardValue}>{remaining.toLocaleString()}</Text>
          <Text style={styles.statCardLabel}>Rămase</Text>
        </View>
        <View style={[styles.statCard, { borderColor: colors.purpleBorder }]}>
          <Icon name="speedometer" size={18} color={colors.purple} />
          <Text style={styles.statCardValue}>{capacityPct}%</Text>
          <Text style={styles.statCardLabel}>Capacitate</Text>
        </View>
      </View>
    </View>
  );
}

function QuickActions({ navigation, onShowGuestList, onShowStaff }) {
  const actions = [
    {
      key: 'scan',
      label: 'Scanare',
      icon: 'qr-code',
      color: colors.purple,
      bg: colors.purpleBg,
      border: colors.purpleBorder,
      onPress: () => navigation.navigate('CheckIn'),
    },
    {
      key: 'sell',
      label: 'Vânzare',
      icon: 'cart',
      color: colors.green,
      bg: colors.greenBg,
      border: colors.greenBorder,
      onPress: () => navigation.navigate('Sales'),
    },
    {
      key: 'guests',
      label: 'Listă Invitați',
      icon: 'list',
      color: colors.cyan,
      bg: colors.cyanBg,
      border: colors.cyanBorder,
      onPress: onShowGuestList,
    },
    {
      key: 'staff',
      label: 'Echipă',
      icon: 'people',
      color: colors.amber,
      bg: colors.amberBg,
      border: colors.amberBorder,
      onPress: onShowStaff,
    },
  ];

  return (
    <View style={styles.quickActionsSection}>
      <Text style={styles.sectionTitle}>Acțiuni Rapide</Text>
      <View style={styles.quickActionsGrid}>
        {actions.map((action) => (
          <TouchableOpacity
            key={action.key}
            style={[
              styles.quickActionBtn,
              { backgroundColor: action.bg, borderColor: action.border },
            ]}
            onPress={action.onPress}
            activeOpacity={0.7}
          >
            <Icon name={action.icon} size={24} color={action.color} />
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
        <Text style={styles.sectionTitle}>Activitate Recentă</Text>
        <View style={styles.emptyState}>
          <Icon name="clock" size={32} color={colors.textQuaternary} />
          <Text style={styles.emptyStateText}>Nicio activitate recentă</Text>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.recentSection}>
      <Text style={styles.sectionTitle}>Activitate Recentă</Text>
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
          <Icon
            name={scan.status === 'valid' ? 'check-circle' : 'x-circle'}
            size={18}
            color={scan.status === 'valid' ? colors.green : colors.red}
          />
        </View>
      ))}
    </View>
  );
}

function AdminDashboard({ navigation, eventStats, isReportsOnlyMode, recentScans, onShowGuestList, onShowStaff }) {
  return (
    <>
      {isReportsOnlyMode && <ReportsOnlyBanner />}

      {isReportsOnlyMode ? (
        <ReportsStatsGrid stats={eventStats} />
      ) : (
        <>
          <AdminLiveStats stats={eventStats} />
          <QuickActions navigation={navigation} onShowGuestList={onShowGuestList} onShowStaff={onShowStaff} />
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
      <Text style={styles.turnoverTitle}>Încasări</Text>
      <View style={styles.turnoverRow}>
        <View style={styles.turnoverItem}>
          <View style={[styles.turnoverIconWrap, { backgroundColor: colors.greenBg }]}>
            <Icon name="cash" size={20} color={colors.green} />
          </View>
          <View>
            <Text style={styles.turnoverLabel}>Numerar</Text>
            <Text style={[styles.turnoverAmount, { color: colors.green }]}>
              {formatCurrency(cashTurnover)}
            </Text>
          </View>
        </View>
        <View style={styles.turnoverDivider} />
        <View style={styles.turnoverItem}>
          <View style={[styles.turnoverIconWrap, { backgroundColor: colors.cyanBg }]}>
            <Icon name="credit-card" size={20} color={colors.cyan} />
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
      label: 'Scanările Mele',
      value: myScans.toLocaleString(),
      icon: 'scan',
      color: colors.purple,
      bg: colors.purpleBg,
      border: colors.purpleBorder,
    },
    {
      label: 'Vânzările Mele',
      value: mySales.toLocaleString(),
      icon: 'cart',
      color: colors.green,
      bg: colors.greenBg,
      border: colors.greenBorder,
    },
    {
      label: 'Durata Turei',
      value: shiftDuration,
      icon: 'clock',
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
          <Icon name={item.icon} size={20} color={item.color} />
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
          <Icon name="qr-code" size={28} color={colors.white} />
          <Text style={styles.scannerActionBtnText}>Începe Scanarea</Text>
        </TouchableOpacity>

        <TouchableOpacity
          style={styles.scannerActionBtnGreen}
          onPress={() => navigation.navigate('Sales')}
          activeOpacity={0.8}
        >
          <Icon name="cart" size={28} color={colors.white} />
          <Text style={styles.scannerActionBtnText}>Începe Vânzarea</Text>
        </TouchableOpacity>
      </View>
    </>
  );
}

// ---------------------------------------------------------------------------
// Main Screen
// ---------------------------------------------------------------------------

export default function DashboardScreen({ navigation, onShowStaff, onShowGuestList }) {
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
          {selectedEvent?.title || selectedEvent?.name || 'Niciun Eveniment Selectat'}
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
          onShowGuestList={onShowGuestList}
          onShowStaff={onShowStaff}
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
