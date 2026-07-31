import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  Dimensions,
  ActivityIndicator,
  Animated,
} from 'react-native';
import Svg, { Path, Defs, LinearGradient, Stop, Rect } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { formatCurrency } from '../../utils/formatCurrency';
import { getTeamMembers } from '../../api/team';
import { getVenueGates } from '../../api/gates';
import { useEvent } from '../../context/EventContext';
import useSwipeToDismiss from '../../hooks/useSwipeToDismiss';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

function getInitials(name) {
  if (!name) return '??';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function AvatarCircle({ name, size = 44 }) {
  const initials = getInitials(name);
  return (
    <View style={[styles.avatar, { width: size, height: size, borderRadius: size / 2 }]}>
      <Text style={[styles.avatarText, { fontSize: size * 0.35 }]}>{initials}</Text>
    </View>
  );
}

function StatusBadge({ status }) {
  const isActive = status === 'Active' || status === 'active';
  return (
    <View style={[styles.statusBadge, isActive ? styles.statusBadgeActive : styles.statusBadgeBreak]}>
      <View style={[styles.statusDot, { backgroundColor: isActive ? colors.green : colors.textTertiary }]} />
      <Text style={[styles.statusBadgeText, { color: isActive ? colors.green : colors.textTertiary }]}>
        {isActive ? 'Activ' : 'Pauză'}
      </Text>
    </View>
  );
}

function DetailItem({ label, value }) {
  return (
    <View style={styles.detailItem}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

function SummaryCard({ label, value, color }) {
  return (
    <View style={styles.summaryCard}>
      <Text style={[styles.summaryValue, { color }]}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );
}

// Prefer explicit backend fields, fall back to null-safe formatting. Any
// field the backend hasn't wired yet renders as an em-dash so it's clear
// the number isn't a real zero.
const DASH = '—';
const dashOr = (v, fmt = (x) => String(x)) => (v == null || v === '' ? DASH : fmt(v));

function StaffCard({ member, gateName }) {
  const scansRaw = member.scans ?? member.scans_count ?? member.stats?.scans;
  const salesRaw = member.sales ?? member.sales_count ?? member.stats?.sales;
  const cashRaw = member.cashAmount ?? member.cash_amount ?? member.stats?.cash_amount;
  const cardRaw = member.cardAmount ?? member.card_amount ?? member.stats?.card_amount;
  const gate = member.gate || member.gate_name || gateName || null;
  const shiftStart = member.shiftStart || member.shift_started_at || null;
  const lastActive = member.lastActive || member.last_active_at || null;

  return (
    <View style={styles.staffCard}>
      {/* Top row: Avatar + Name + Status */}
      <View style={styles.staffCardHeader}>
        <AvatarCircle name={member.name} />
        <View style={styles.staffInfo}>
          <Text style={styles.staffName}>{member.name}</Text>
          <Text style={styles.staffRole}>{member.role}</Text>
        </View>
        <StatusBadge status={member.status} />
      </View>

      {/* Details grid */}
      <View style={styles.detailsGrid}>
        <DetailItem label="Poartă" value={dashOr(gate)} />
        <DetailItem label="Început Tură" value={dashOr(shiftStart)} />
        <DetailItem label="Ultima Activitate" value={dashOr(lastActive)} />
        <DetailItem label="Scanări" value={dashOr(scansRaw)} />
        <DetailItem label="Vânzări" value={dashOr(salesRaw)} />
        <DetailItem label="Numerar" value={dashOr(cashRaw, formatCurrency)} />
        <DetailItem label="Card" value={dashOr(cardRaw, formatCurrency)} />
      </View>
    </View>
  );
}

export default function StaffModal({ visible, onClose }) {
  const { selectedEvent } = useEvent();
  const [staffMembers, setStaffMembers] = useState([]);
  const [loading, setLoading] = useState(false);
  // gate_id → name map. Team endpoint returns only gate_id today; we
  // resolve the human-readable name from /organizer/venues/{v}/gates so
  // each StaffCard can show "Poarta Nord" instead of the raw numeric id.
  const [gatesById, setGatesById] = useState({});

  const fetchMembers = useCallback(async () => {
    setLoading(true);
    try {
      // Pass event_id so the day the backend starts returning per-event
      // scan / sale / cash / card stats, this call already scopes them.
      const resp = await getTeamMembers({ event_id: selectedEvent?.id });
      const members = resp.data?.members || resp.members || [];
      setStaffMembers(members.filter(m => m.status === 'active'));
    } catch (e) {
      console.error('Failed to fetch team members:', e);
    }
    setLoading(false);
  }, [selectedEvent?.id]);

  const fetchGates = useCallback(async () => {
    const venueId = selectedEvent?.venue_id || selectedEvent?.venue?.id;
    if (!venueId) { setGatesById({}); return; }
    try {
      const resp = await getVenueGates(venueId);
      const list = resp?.data?.gates || resp?.data || resp?.gates || [];
      const map = {};
      list.forEach(g => { if (g?.id != null) map[String(g.id)] = g.name || g.label || `Poartă ${g.id}`; });
      setGatesById(map);
    } catch {
      setGatesById({});
    }
  }, [selectedEvent?.venue_id, selectedEvent?.venue?.id]);

  useEffect(() => {
    if (visible) {
      fetchMembers();
      fetchGates();
    }
  }, [visible, selectedEvent?.id]);

  const { translateY, panResponder } = useSwipeToDismiss(onClose);

  const activeCount = staffMembers.filter(m => m.status === 'Active' || m.status === 'active').length;
  const totalScans = staffMembers.reduce((sum, m) => sum + (m.scans || 0), 0);
  const totalSales = staffMembers.reduce((sum, m) => sum + (m.sales || 0), 0);

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
              <Text style={styles.title}>Prezentare Echipă</Text>
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

          {/* Summary Row */}
          <View style={styles.summaryRow}>
            <SummaryCard label="Activi" value={String(activeCount)} color={colors.green} />
            <SummaryCard label="Total Scanări" value={String(totalScans)} color={colors.purple} />
            <SummaryCard label="Total Vânzări" value={String(totalSales)} color={colors.amber} />
          </View>

          {/* Staff List */}
          <ScrollView
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
          >
            {loading ? (
              <View style={styles.emptyState}>
                <ActivityIndicator size="large" color={colors.purple} />
              </View>
            ) : staffMembers.length === 0 ? (
              <View style={styles.emptyState}>
                <Svg width={48} height={48} viewBox="0 0 24 24" fill="none">
                  <Path
                    d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                    stroke={colors.textTertiary}
                    strokeWidth={1.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </Svg>
                <Text style={styles.emptyText}>Niciun membru al echipei asignat</Text>
              </View>
            ) : (
              <>
                {staffMembers.map((member, index) => (
                  <StaffCard
                    key={member.id || index}
                    member={member}
                    gateName={member.gate_id != null ? gatesById[String(member.gate_id)] : null}
                  />
                ))}
                <View style={styles.pendingNote}>
                  <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
                    <Path
                      d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 16v-4M12 8h.01"
                      stroke={colors.textTertiary}
                      strokeWidth={2}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </Svg>
                  <Text style={styles.pendingNoteText}>
                    Cifrele per-membru (scanări, vânzări, numerar, card, tură)
                    apar când backend-ul le raportează pentru evenimentul
                    selectat. „—" = date necunoscute (nu zero real).
                  </Text>
                </View>
              </>
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
    height: SCREEN_HEIGHT * 0.85,
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
  summaryRow: {
    flexDirection: 'row',
    paddingHorizontal: 20,
    paddingVertical: 16,
    gap: 10,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  summaryCard: {
    flex: 1,
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    paddingVertical: 14,
    alignItems: 'center',
  },
  summaryValue: {
    fontSize: 22,
    fontWeight: '700',
    marginBottom: 4,
  },
  summaryLabel: {
    fontSize: 11,
    fontWeight: '500',
    color: colors.textTertiary,
    letterSpacing: 0.3,
  },
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 20,
  },
  staffCard: {
    backgroundColor: colors.surface,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 16,
    marginBottom: 12,
  },
  staffCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 14,
  },
  avatar: {
    backgroundColor: colors.purple,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: colors.white,
    fontWeight: '700',
  },
  staffInfo: {
    flex: 1,
    marginLeft: 12,
  },
  staffName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  staffRole: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 8,
    gap: 6,
  },
  statusBadgeActive: {
    backgroundColor: colors.greenLight,
    borderWidth: 1,
    borderColor: colors.greenBorder,
  },
  statusBadgeBreak: {
    backgroundColor: 'rgba(20,10,10,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(20,10,10,0.08)',
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  detailsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  detailItem: {
    backgroundColor: 'rgba(20,10,10,0.03)',
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 8,
    minWidth: '30%',
    flexGrow: 1,
  },
  detailLabel: {
    fontSize: 10,
    fontWeight: '500',
    color: colors.textTertiary,
    marginBottom: 3,
    letterSpacing: 0.3,
  },
  detailValue: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textPrimary,
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
  pendingNote: {
    flexDirection: 'row',
    gap: 8,
    padding: 12,
    marginTop: 8,
    marginBottom: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'rgba(20,10,10,0.03)',
  },
  pendingNoteText: {
    flex: 1,
    fontSize: 11,
    lineHeight: 15,
    color: colors.textTertiary,
  },
});
