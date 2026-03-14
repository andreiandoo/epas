import React, { useMemo, useState, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Modal,
  ActivityIndicator,
  Alert,
  RefreshControl,
} from 'react-native';
import Svg, { Path, Circle, Line, Rect, Polyline } from 'react-native-svg';
import { useAuth } from '../context/AuthContext';
import { useEvent } from '../context/EventContext';
import { useApp } from '../context/AppContext';
import { apiGet } from '../api/client';
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

function AdminLiveStats({ stats, onShowSales, onShowTicketSales, onShowRemaining }) {
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
        <TouchableOpacity
          style={[styles.statCard, { borderColor: colors.greenBorder }]}
          onPress={onShowTicketSales}
          activeOpacity={0.7}
        >
          <Icon name="ticket" size={18} color={colors.green} />
          <Text style={styles.statCardValue}>{totalSold.toLocaleString()}</Text>
          <Text style={styles.statCardLabel}>Vânzări</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.statCard, { borderColor: colors.cyanBorder }]}
          onPress={onShowSales}
          activeOpacity={0.7}
        >
          <Icon name="cash" size={18} color={colors.cyan} />
          <Text style={styles.statCardValue}>{formatCurrency(revenue)}</Text>
          <Text style={styles.statCardLabel}>Venituri</Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.statCard, { borderColor: colors.amberBorder }]}
          onPress={onShowRemaining}
          activeOpacity={0.7}
        >
          <Icon name="hourglass" size={18} color={colors.amber} />
          <Text style={styles.statCardValue}>{remaining.toLocaleString()}</Text>
          <Text style={styles.statCardLabel}>Rămase</Text>
        </TouchableOpacity>
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

function AdminDashboard({ navigation, eventStats, isReportsOnlyMode, recentScans, onShowGuestList, onShowStaff, onShowSales, onShowTicketSales, onShowRemaining, onCloseShift }) {
  return (
    <>
      {isReportsOnlyMode && <ReportsOnlyBanner />}

      {isReportsOnlyMode ? (
        <ReportsStatsGrid stats={eventStats} />
      ) : (
        <>
          <AdminLiveStats stats={eventStats} onShowSales={onShowSales} onShowTicketSales={onShowTicketSales} onShowRemaining={onShowRemaining} />
          <QuickActions navigation={navigation} onShowGuestList={onShowGuestList} onShowStaff={onShowStaff} />
          <RecentActivity recentScans={recentScans} />
          {onCloseShift && (
            <TouchableOpacity
              style={styles.closeShiftBtn}
              onPress={onCloseShift}
              activeOpacity={0.7}
            >
              <Icon name="x-circle" size={20} color={colors.red} />
              <Text style={styles.closeShiftBtnText}>Închide Tura</Text>
            </TouchableOpacity>
          )}
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

function ScannerDashboard({ navigation, cashTurnover, cardTurnover, myScans, mySales, shiftStartTime, onCloseShift }) {
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

      {shiftStartTime && (
        <TouchableOpacity
          style={styles.closeShiftBtn}
          onPress={onCloseShift}
          activeOpacity={0.7}
        >
          <Icon name="x-circle" size={20} color={colors.red} />
          <Text style={styles.closeShiftBtnText}>Închide Tura</Text>
        </TouchableOpacity>
      )}
    </>
  );
}

// ---------------------------------------------------------------------------
// Close Shift Summary Modal
// ---------------------------------------------------------------------------

function ShiftSummaryModal({ visible, onClose, onConfirm, cashTurnover, cardTurnover, recentScans, recentSales, shiftStartTime }) {
  const validScans = recentScans.filter(s => s.status === 'valid').length;
  const invalidScans = recentScans.filter(s => s.status !== 'valid').length;

  // Group sales by ticket type
  const salesByType = useMemo(() => {
    const map = {};
    recentSales.forEach(sale => {
      const desc = sale.description || sale.type || 'Bilet';
      if (!map[desc]) map[desc] = { count: 0, total: 0 };
      map[desc].count += sale.qty || 1;
      map[desc].total += sale.total || 0;
    });
    return Object.entries(map);
  }, [recentSales]);

  const shiftDuration = useMemo(() => {
    if (!shiftStartTime) return '--:--';
    const now = new Date();
    const diff = Math.floor((now - new Date(shiftStartTime)) / 1000);
    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    return `${hours}h ${minutes.toString().padStart(2, '0')}m`;
  }, [shiftStartTime, visible]);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <View style={styles.shiftModal}>
          <Text style={styles.shiftModalTitle}>Rezumat Tură</Text>
          <Text style={styles.shiftModalDuration}>Durată: {shiftDuration}</Text>

          {/* Cash to hand over */}
          {cashTurnover > 0 && (
            <View style={styles.shiftCashBox}>
              <Icon name="cash" size={22} color={colors.green} />
              <View style={{ flex: 1 }}>
                <Text style={styles.shiftCashLabel}>Numerar de predat</Text>
                <Text style={styles.shiftCashAmount}>{formatCurrency(cashTurnover)}</Text>
              </View>
            </View>
          )}

          {cardTurnover > 0 && (
            <View style={[styles.shiftCashBox, { borderColor: colors.cyanBorder }]}>
              <Icon name="credit-card" size={22} color={colors.cyan} />
              <View style={{ flex: 1 }}>
                <Text style={styles.shiftCashLabel}>Încasări card</Text>
                <Text style={[styles.shiftCashAmount, { color: colors.cyan }]}>{formatCurrency(cardTurnover)}</Text>
              </View>
            </View>
          )}

          {/* Scan stats */}
          {recentScans.length > 0 && (
            <View style={styles.shiftSection}>
              <Text style={styles.shiftSectionTitle}>Scanări</Text>
              <View style={styles.shiftRow}>
                <Text style={styles.shiftRowLabel}>Total scanări</Text>
                <Text style={styles.shiftRowValue}>{recentScans.length}</Text>
              </View>
              <View style={styles.shiftRow}>
                <Text style={styles.shiftRowLabel}>Valide</Text>
                <Text style={[styles.shiftRowValue, { color: colors.green }]}>{validScans}</Text>
              </View>
              <View style={styles.shiftRow}>
                <Text style={styles.shiftRowLabel}>Invalide</Text>
                <Text style={[styles.shiftRowValue, { color: colors.red }]}>{invalidScans}</Text>
              </View>
            </View>
          )}

          {/* Sales by type */}
          {salesByType.length > 0 && (
            <View style={styles.shiftSection}>
              <Text style={styles.shiftSectionTitle}>Vânzări bilete</Text>
              {salesByType.map(([type, data]) => (
                <View key={type} style={styles.shiftRow}>
                  <Text style={styles.shiftRowLabel} numberOfLines={1}>{type}</Text>
                  <Text style={styles.shiftRowValue}>{data.count} buc - {formatCurrency(data.total)}</Text>
                </View>
              ))}
            </View>
          )}

          {recentScans.length === 0 && recentSales.length === 0 && cashTurnover === 0 && (
            <Text style={styles.shiftEmptyText}>Nicio activitate în această tură.</Text>
          )}

          <TouchableOpacity style={styles.shiftConfirmBtn} onPress={onConfirm} activeOpacity={0.7}>
            <Text style={styles.shiftConfirmBtnText}>Închide Tura</Text>
          </TouchableOpacity>
          <TouchableOpacity style={styles.shiftCancelBtn} onPress={onClose} activeOpacity={0.7}>
            <Text style={styles.shiftCancelBtnText}>Anulează</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Ticket Sales By Type Modal (Vânzări card)
// ---------------------------------------------------------------------------

function TicketSalesByTypeModal({ visible, onClose, ticketTypes }) {
  const totalSold = (ticketTypes || []).reduce((sum, t) => sum + (t.quantity_sold || 0), 0);
  const totalRevenue = (ticketTypes || []).reduce((sum, t) => sum + ((t.quantity_sold || 0) * (t.price || 0)), 0);

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <View style={styles.salesModal}>
          <Text style={styles.salesModalTitle}>Vânzări per Tip Bilet</Text>

          <ScrollView style={{ maxHeight: 400 }} showsVerticalScrollIndicator={false}>
            {(ticketTypes || []).map(tt => {
              const sold = tt.quantity_sold || 0;
              const total = tt.quantity || 0;
              const pct = total > 0 ? ((sold / total) * 100).toFixed(0) : '0';
              const barWidth = total > 0 ? Math.min((sold / total) * 100, 100) : 0;

              return (
                <View key={tt.id} style={styles.ttBreakdownCard}>
                  <View style={styles.ttBreakdownHeader}>
                    <View style={[styles.ttBreakdownDot, { backgroundColor: tt.color || colors.purple }]} />
                    <Text style={styles.ttBreakdownName} numberOfLines={1}>{tt.name}</Text>
                    <Text style={styles.ttBreakdownPrice}>{formatCurrency(tt.price)}</Text>
                  </View>
                  <View style={styles.ttBreakdownNumbers}>
                    <Text style={styles.ttBreakdownSold}>{sold}</Text>
                    <Text style={styles.ttBreakdownTotal}> / {total} bilete</Text>
                    <Text style={styles.ttBreakdownPct}>{pct}%</Text>
                  </View>
                  <View style={styles.ttBreakdownBarBg}>
                    <View style={[styles.ttBreakdownBarFill, { width: `${barWidth}%`, backgroundColor: tt.color || colors.purple }]} />
                  </View>
                  <Text style={styles.ttBreakdownRevenue}>
                    Încasări: {formatCurrency(sold * (tt.price || 0))}
                  </Text>
                </View>
              );
            })}

            {/* Totals */}
            <View style={styles.ttBreakdownTotals}>
              <View style={styles.ttBreakdownTotalRow}>
                <Text style={styles.ttBreakdownTotalLabel}>Total bilete vândute</Text>
                <Text style={styles.ttBreakdownTotalValue}>{totalSold}</Text>
              </View>
              <View style={styles.ttBreakdownTotalRow}>
                <Text style={styles.ttBreakdownTotalLabel}>Total încasări</Text>
                <Text style={[styles.ttBreakdownTotalValue, { color: colors.green }]}>{formatCurrency(totalRevenue)}</Text>
              </View>
            </View>
          </ScrollView>

          <TouchableOpacity style={styles.salesModalCloseBtn} onPress={onClose} activeOpacity={0.7}>
            <Text style={styles.salesModalCloseBtnText}>Închide</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Remaining By Type Modal (Rămase card)
// ---------------------------------------------------------------------------

function RemainingByTypeModal({ visible, onClose, ticketTypes }) {
  const totalCheckedIn = (ticketTypes || []).reduce((sum, t) => sum + (t.checked_in || 0), 0);
  const totalSold = (ticketTypes || []).reduce((sum, t) => sum + (t.quantity_sold || 0), 0);
  const totalRemaining = totalSold - totalCheckedIn;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <View style={styles.salesModal}>
          <Text style={styles.salesModalTitle}>Intrați / Rămase per Tip</Text>

          <ScrollView style={{ maxHeight: 400 }} showsVerticalScrollIndicator={false}>
            {(ticketTypes || []).map(tt => {
              const sold = tt.quantity_sold || 0;
              const checkedIn = tt.checked_in || 0;
              const remaining = sold - checkedIn;
              const pct = sold > 0 ? ((checkedIn / sold) * 100).toFixed(0) : '0';
              const barWidth = sold > 0 ? Math.min((checkedIn / sold) * 100, 100) : 0;

              return (
                <View key={tt.id} style={styles.ttBreakdownCard}>
                  <View style={styles.ttBreakdownHeader}>
                    <View style={[styles.ttBreakdownDot, { backgroundColor: tt.color || colors.purple }]} />
                    <Text style={styles.ttBreakdownName} numberOfLines={1}>{tt.name}</Text>
                  </View>
                  <View style={styles.ttBreakdownNumbers}>
                    <Text style={[styles.ttBreakdownSold, { color: colors.green }]}>{checkedIn} intrați</Text>
                    <Text style={styles.ttBreakdownTotal}> / {sold} vândute</Text>
                    <Text style={styles.ttBreakdownPct}>{pct}%</Text>
                  </View>
                  <View style={styles.ttBreakdownBarBg}>
                    <View style={[styles.ttBreakdownBarFill, { width: `${barWidth}%`, backgroundColor: colors.green }]} />
                  </View>
                  <Text style={[styles.ttBreakdownRevenue, { color: colors.amber }]}>
                    Rămase: {remaining}
                  </Text>
                </View>
              );
            })}

            {/* Totals */}
            <View style={styles.ttBreakdownTotals}>
              <View style={styles.ttBreakdownTotalRow}>
                <Text style={styles.ttBreakdownTotalLabel}>Total intrați</Text>
                <Text style={[styles.ttBreakdownTotalValue, { color: colors.green }]}>{totalCheckedIn}</Text>
              </View>
              <View style={styles.ttBreakdownTotalRow}>
                <Text style={styles.ttBreakdownTotalLabel}>Total rămase</Text>
                <Text style={[styles.ttBreakdownTotalValue, { color: colors.amber }]}>{totalRemaining}</Text>
              </View>
            </View>
          </ScrollView>

          <TouchableOpacity style={styles.salesModalCloseBtn} onPress={onClose} activeOpacity={0.7}>
            <Text style={styles.salesModalCloseBtnText}>Închide</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Sales Breakdown Modal (Venituri card — Online/POS breakdown)
// ---------------------------------------------------------------------------

function SalesBreakdownModal({ visible, onClose, eventId }) {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState(null);

  const fetchData = useCallback(async () => {
    if (!eventId) return;
    setLoading(true);
    try {
      const resp = await apiGet(`/events/${eventId}/sales-breakdown`);
      setData(resp.data || resp);
    } catch (e) {
      console.error('Failed to fetch sales breakdown:', e);
    }
    setLoading(false);
  }, [eventId]);

  React.useEffect(() => {
    if (visible && eventId) fetchData();
  }, [visible, eventId]);

  const online = data?.online || {};
  const pos = data?.pos || {};
  const byUser = pos.by_user || [];

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <View style={styles.salesModal}>
          <Text style={styles.salesModalTitle}>Detalii Vânzări</Text>

          {loading ? (
            <ActivityIndicator size="large" color={colors.purple} style={{ marginVertical: 24 }} />
          ) : data ? (
            <ScrollView style={{ maxHeight: 400 }} showsVerticalScrollIndicator={false}>
              {/* Online */}
              <View style={styles.salesBreakdownSection}>
                <View style={styles.salesBreakdownHeader}>
                  <View style={[styles.salesBreakdownDot, { backgroundColor: colors.cyan }]} />
                  <Text style={styles.salesBreakdownTitle}>Online</Text>
                </View>
                <View style={styles.salesBreakdownStats}>
                  <View style={styles.salesBreakdownStat}>
                    <Text style={styles.salesBreakdownStatValue}>{online.orders || 0}</Text>
                    <Text style={styles.salesBreakdownStatLabel}>Comenzi</Text>
                  </View>
                  <View style={styles.salesBreakdownStat}>
                    <Text style={styles.salesBreakdownStatValue}>{online.tickets || 0}</Text>
                    <Text style={styles.salesBreakdownStatLabel}>Bilete</Text>
                  </View>
                  <View style={styles.salesBreakdownStat}>
                    <Text style={[styles.salesBreakdownStatValue, { color: colors.cyan }]}>{formatCurrency(online.revenue || 0)}</Text>
                    <Text style={styles.salesBreakdownStatLabel}>Încasări</Text>
                  </View>
                </View>
              </View>

              {/* POS total */}
              <View style={styles.salesBreakdownSection}>
                <View style={styles.salesBreakdownHeader}>
                  <View style={[styles.salesBreakdownDot, { backgroundColor: colors.green }]} />
                  <Text style={styles.salesBreakdownTitle}>Fizic (POS)</Text>
                </View>
                <View style={styles.salesBreakdownStats}>
                  <View style={styles.salesBreakdownStat}>
                    <Text style={styles.salesBreakdownStatValue}>{pos.orders || 0}</Text>
                    <Text style={styles.salesBreakdownStatLabel}>Comenzi</Text>
                  </View>
                  <View style={styles.salesBreakdownStat}>
                    <Text style={styles.salesBreakdownStatValue}>{pos.tickets || 0}</Text>
                    <Text style={styles.salesBreakdownStatLabel}>Bilete</Text>
                  </View>
                  <View style={styles.salesBreakdownStat}>
                    <Text style={[styles.salesBreakdownStatValue, { color: colors.green }]}>{formatCurrency(pos.revenue || 0)}</Text>
                    <Text style={styles.salesBreakdownStatLabel}>Încasări</Text>
                  </View>
                </View>

                {/* By user */}
                {byUser.length > 0 && (
                  <View style={styles.salesByUserSection}>
                    <Text style={styles.salesByUserTitle}>Per utilizator</Text>
                    {byUser.map((u, i) => (
                      <View key={i} style={styles.salesByUserRow}>
                        <View style={styles.salesByUserInfo}>
                          <Icon name="people" size={14} color={colors.textTertiary} />
                          <Text style={styles.salesByUserName} numberOfLines={1}>{u.user}</Text>
                        </View>
                        <Text style={styles.salesByUserDetail}>{u.tickets} bil.</Text>
                        <Text style={styles.salesByUserAmount}>{formatCurrency(u.revenue)}</Text>
                      </View>
                    ))}
                  </View>
                )}
              </View>
            </ScrollView>
          ) : (
            <Text style={styles.shiftEmptyText}>Nu s-au putut încărca datele.</Text>
          )}

          <TouchableOpacity style={styles.salesModalCloseBtn} onPress={onClose} activeOpacity={0.7}>
            <Text style={styles.salesModalCloseBtnText}>Închide</Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Main Screen
// ---------------------------------------------------------------------------

export default function DashboardScreen({ navigation, onShowStaff, onShowGuestList }) {
  const { userRole } = useAuth();
  const { selectedEvent, eventStats, ticketTypes, allTicketTypes, isReportsOnlyMode, refreshStats, refreshTicketTypes, isLoadingStats } = useEvent();
  const {
    shiftStartTime,
    cashTurnover,
    cardTurnover,
    myScans,
    mySales,
    recentScans,
    recentSales,
    endShift,
  } = useApp();

  const isAdmin = userRole === 'admin' || userRole === 'owner';

  // Shift summary modal
  const [showShiftSummary, setShowShiftSummary] = useState(false);
  // Sales breakdown modal
  const [showSalesBreakdown, setShowSalesBreakdown] = useState(false);
  // Ticket sales by type modal
  const [showTicketSales, setShowTicketSales] = useState(false);
  // Remaining by type modal
  const [showRemaining, setShowRemaining] = useState(false);

  const handleCloseShift = () => {
    setShowShiftSummary(true);
  };

  const confirmCloseShift = () => {
    setShowShiftSummary(false);
    endShift();
    Alert.alert('Tură închisă', 'Tura a fost închisă cu succes.');
  };

  return (
    <View style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.contentContainer}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={isLoadingStats}
            onRefresh={() => { refreshStats(); refreshTicketTypes(); }}
            tintColor={colors.purple}
            colors={[colors.purple]}
          />
        }
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
            onShowSales={() => setShowSalesBreakdown(true)}
            onShowTicketSales={() => setShowTicketSales(true)}
            onShowRemaining={() => setShowRemaining(true)}
            onCloseShift={shiftStartTime ? handleCloseShift : null}
          />
        ) : (
          <ScannerDashboard
            navigation={navigation}
            cashTurnover={cashTurnover}
            cardTurnover={cardTurnover}
            myScans={myScans}
            mySales={mySales}
            shiftStartTime={shiftStartTime}
            onCloseShift={handleCloseShift}
          />
        )}
      </ScrollView>

      <ShiftSummaryModal
        visible={showShiftSummary}
        onClose={() => setShowShiftSummary(false)}
        onConfirm={confirmCloseShift}
        cashTurnover={cashTurnover}
        cardTurnover={cardTurnover}
        recentScans={recentScans}
        recentSales={recentSales}
        shiftStartTime={shiftStartTime}
      />

      <SalesBreakdownModal
        visible={showSalesBreakdown}
        onClose={() => setShowSalesBreakdown(false)}
        eventId={selectedEvent?.id}
      />

      <TicketSalesByTypeModal
        visible={showTicketSales}
        onClose={() => setShowTicketSales(false)}
        ticketTypes={allTicketTypes}
      />

      <RemainingByTypeModal
        visible={showRemaining}
        onClose={() => setShowRemaining(false)}
        ticketTypes={allTicketTypes}
      />
    </View>
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

  // Close Shift button
  closeShiftBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.red + '40',
    borderRadius: 14,
    paddingVertical: 14,
    marginTop: 20,
    gap: 8,
  },
  closeShiftBtnText: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.red,
  },

  // Modal overlay
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    alignItems: 'center',
    justifyContent: 'center',
  },

  // Shift Summary Modal
  shiftModal: {
    backgroundColor: '#16161F',
    borderRadius: 20,
    padding: 24,
    marginHorizontal: 20,
    width: '90%',
    maxWidth: 400,
    borderWidth: 1,
    borderColor: colors.border,
  },
  shiftModalTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
    textAlign: 'center',
    marginBottom: 4,
  },
  shiftModalDuration: {
    fontSize: 13,
    color: colors.textTertiary,
    textAlign: 'center',
    marginBottom: 20,
  },
  shiftCashBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.greenBg,
    borderWidth: 1,
    borderColor: colors.greenBorder,
    borderRadius: 12,
    padding: 14,
    gap: 12,
    marginBottom: 10,
  },
  shiftCashLabel: {
    fontSize: 13,
    color: colors.textSecondary,
    marginBottom: 2,
  },
  shiftCashAmount: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.green,
  },
  shiftSection: {
    marginTop: 14,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  shiftSectionTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 10,
  },
  shiftRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 5,
  },
  shiftRowLabel: {
    fontSize: 14,
    color: colors.textSecondary,
    flex: 1,
  },
  shiftRowValue: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  shiftEmptyText: {
    fontSize: 14,
    color: colors.textTertiary,
    textAlign: 'center',
    marginVertical: 16,
  },
  shiftConfirmBtn: {
    backgroundColor: colors.red,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 20,
  },
  shiftConfirmBtnText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.white,
  },
  shiftCancelBtn: {
    paddingVertical: 12,
    alignItems: 'center',
  },
  shiftCancelBtnText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },

  // Sales Breakdown Modal
  salesModal: {
    backgroundColor: '#16161F',
    borderRadius: 20,
    padding: 24,
    marginHorizontal: 20,
    width: '90%',
    maxWidth: 420,
    borderWidth: 1,
    borderColor: colors.border,
  },
  salesModalTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: colors.textPrimary,
    textAlign: 'center',
    marginBottom: 20,
  },
  salesBreakdownSection: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    padding: 14,
    marginBottom: 12,
  },
  salesBreakdownHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
  },
  salesBreakdownDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  salesBreakdownTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  salesBreakdownStats: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  salesBreakdownStat: {
    alignItems: 'center',
  },
  salesBreakdownStatValue: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  salesBreakdownStatLabel: {
    fontSize: 11,
    color: colors.textTertiary,
    marginTop: 2,
  },
  salesByUserSection: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  salesByUserTitle: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textSecondary,
    marginBottom: 8,
  },
  salesByUserRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 6,
    gap: 8,
  },
  salesByUserInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  salesByUserName: {
    fontSize: 14,
    color: colors.textPrimary,
    fontWeight: '500',
    flex: 1,
  },
  salesByUserDetail: {
    fontSize: 13,
    color: colors.textSecondary,
  },
  salesByUserAmount: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.green,
    minWidth: 60,
    textAlign: 'right',
  },
  salesModalCloseBtn: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  salesModalCloseBtnText: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
  },

  // Ticket type breakdown styles (shared by TicketSalesByType and RemainingByType modals)
  ttBreakdownCard: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },
  ttBreakdownHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  ttBreakdownDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  ttBreakdownName: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  ttBreakdownPrice: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  ttBreakdownNumbers: {
    flexDirection: 'row',
    alignItems: 'baseline',
    marginBottom: 6,
  },
  ttBreakdownSold: {
    fontSize: 20,
    fontWeight: '700',
    color: colors.textPrimary,
  },
  ttBreakdownTotal: {
    fontSize: 13,
    color: colors.textTertiary,
    marginRight: 8,
  },
  ttBreakdownPct: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textSecondary,
    marginLeft: 'auto',
  },
  ttBreakdownBarBg: {
    height: 6,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: 3,
    marginBottom: 6,
    overflow: 'hidden',
  },
  ttBreakdownBarFill: {
    height: '100%',
    borderRadius: 3,
  },
  ttBreakdownRevenue: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  ttBreakdownTotals: {
    borderTopWidth: 1,
    borderTopColor: colors.border,
    paddingTop: 12,
    marginTop: 4,
    gap: 6,
  },
  ttBreakdownTotalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  ttBreakdownTotalLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  ttBreakdownTotalValue: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.textPrimary,
  },
});
