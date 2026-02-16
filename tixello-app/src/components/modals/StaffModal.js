import React from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  Dimensions,
} from 'react-native';
import Svg, { Path, Defs, LinearGradient, Stop, Rect } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { formatCurrency } from '../../utils/formatCurrency';

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

function StaffCard({ member }) {
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
        <DetailItem label="Poartă" value={member.gate || '--'} />
        <DetailItem label="Început Tură" value={member.shiftStart || '--'} />
        <DetailItem label="Ultima Activitate" value={member.lastActive || '--'} />
        <DetailItem label="Scanări" value={member.scans != null ? String(member.scans) : '0'} />
        <DetailItem label="Vânzări" value={member.sales != null ? String(member.sales) : '0'} />
        <DetailItem label="Numerar" value={member.cashAmount != null ? formatCurrency(member.cashAmount) : formatCurrency(0)} />
        <DetailItem label="Card" value={member.cardAmount != null ? formatCurrency(member.cardAmount) : formatCurrency(0)} />
      </View>
    </View>
  );
}

export default function StaffModal({ visible, onClose, staffMembers = [] }) {
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
        <View style={styles.sheet}>
          {/* Header */}
          <View style={styles.header}>
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
            {staffMembers.length === 0 ? (
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
              staffMembers.map((member, index) => (
                <StaffCard key={member.id || index} member={member} />
              ))
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
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
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
    backgroundColor: 'rgba(255,255,255,0.03)',
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
});
