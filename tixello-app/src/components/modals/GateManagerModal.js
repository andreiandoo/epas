import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  TextInput,
  Switch,
  Dimensions,
  Alert,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

const GATE_TYPES = ['Entry', 'VIP', 'POS', 'Exit'];

const DEFAULT_GATES = [
  { id: '1', name: 'Gate A', type: 'Entry', location: 'North Entrance', active: true },
  { id: '2', name: 'Gate B', type: 'Entry', location: 'South Entrance', active: true },
  { id: '3', name: 'VIP Entrance', type: 'VIP', location: 'East Wing', active: true },
  { id: '4', name: 'Box Office 1', type: 'POS', location: 'Main Hall', active: true },
  { id: '5', name: 'Box Office 2', type: 'POS', location: 'West Wing', active: false },
];

function GateTypeIcon({ type, size = 18 }) {
  const iconColor = getTypeColor(type);
  switch (type) {
    case 'Entry':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
          <Path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3" stroke={iconColor} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
        </Svg>
      );
    case 'VIP':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
          <Path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke={iconColor} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
        </Svg>
      );
    case 'POS':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
          <Path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" stroke={iconColor} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
        </Svg>
      );
    case 'Exit':
      return (
        <Svg width={size} height={size} viewBox="0 0 24 24" fill="none">
          <Path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9" stroke={iconColor} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" />
        </Svg>
      );
    default:
      return null;
  }
}

function getTypeColor(type) {
  switch (type) {
    case 'Entry': return colors.green;
    case 'VIP': return colors.amber;
    case 'POS': return colors.cyan;
    case 'Exit': return colors.red;
    default: return colors.textSecondary;
  }
}

function getTypeBg(type) {
  switch (type) {
    case 'Entry': return colors.greenLight;
    case 'VIP': return colors.amberLight;
    case 'POS': return colors.cyanLight;
    case 'Exit': return colors.redLight;
    default: return 'rgba(255,255,255,0.05)';
  }
}

function getTypeBorder(type) {
  switch (type) {
    case 'Entry': return colors.greenBorder;
    case 'VIP': return colors.amberBorder;
    case 'POS': return colors.cyanBorder;
    case 'Exit': return colors.redBorder;
    default: return 'rgba(255,255,255,0.08)';
  }
}

function TypeBadge({ type }) {
  return (
    <View style={[styles.typeBadge, { backgroundColor: getTypeBg(type), borderColor: getTypeBorder(type) }]}>
      <Text style={[styles.typeBadgeText, { color: getTypeColor(type) }]}>{type}</Text>
    </View>
  );
}

function TypePicker({ selected, onSelect }) {
  return (
    <View style={styles.typePicker}>
      {GATE_TYPES.map((type) => {
        const isSelected = selected === type;
        return (
          <TouchableOpacity
            key={type}
            style={[
              styles.typeOption,
              isSelected && {
                backgroundColor: getTypeBg(type),
                borderColor: getTypeBorder(type),
              },
            ]}
            onPress={() => onSelect(type)}
            activeOpacity={0.7}
          >
            <Text
              style={[
                styles.typeOptionText,
                isSelected && { color: getTypeColor(type) },
              ]}
            >
              {type}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

function GateCard({ gate, onToggle, onDelete }) {
  return (
    <View style={styles.gateCard}>
      <View style={styles.gateCardTop}>
        {/* Icon */}
        <View style={[styles.gateIcon, { backgroundColor: getTypeBg(gate.type), borderColor: getTypeBorder(gate.type) }]}>
          <GateTypeIcon type={gate.type} />
        </View>

        {/* Info */}
        <View style={styles.gateInfo}>
          <Text style={styles.gateName}>{gate.name}</Text>
          <Text style={styles.gateLocation}>{gate.location}</Text>
        </View>

        {/* Type badge */}
        <TypeBadge type={gate.type} />
      </View>

      <View style={styles.gateCardBottom}>
        {/* Active toggle */}
        <View style={styles.toggleRow}>
          <Text style={styles.toggleLabel}>{gate.active ? 'Active' : 'Inactive'}</Text>
          <Switch
            value={gate.active}
            onValueChange={() => onToggle(gate.id)}
            trackColor={{ false: 'rgba(255,255,255,0.1)', true: colors.greenLight }}
            thumbColor={gate.active ? colors.green : colors.textTertiary}
          />
        </View>

        {/* Delete button */}
        <TouchableOpacity
          style={styles.deleteButton}
          onPress={() => onDelete(gate.id)}
          activeOpacity={0.7}
        >
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path
              d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"
              stroke={colors.red}
              strokeWidth={1.8}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
        </TouchableOpacity>
      </View>
    </View>
  );
}

export default function GateManagerModal({ visible, onClose }) {
  const [gates, setGates] = useState(DEFAULT_GATES);
  const [newName, setNewName] = useState('');
  const [newType, setNewType] = useState('Entry');
  const [newLocation, setNewLocation] = useState('');

  const handleAddGate = () => {
    if (!newName.trim()) return;

    const newGate = {
      id: String(Date.now()),
      name: newName.trim(),
      type: newType,
      location: newLocation.trim() || 'Not specified',
      active: true,
    };

    setGates(prev => [...prev, newGate]);
    setNewName('');
    setNewLocation('');
    setNewType('Entry');
  };

  const handleToggle = (gateId) => {
    setGates(prev =>
      prev.map(g => g.id === gateId ? { ...g, active: !g.active } : g)
    );
  };

  const handleDelete = (gateId) => {
    setGates(prev => prev.filter(g => g.id !== gateId));
  };

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
              <Text style={styles.title}>Gate Management</Text>
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

          <ScrollView
            style={styles.scrollView}
            contentContainerStyle={styles.scrollContent}
            showsVerticalScrollIndicator={false}
          >
            {/* Add New Gate Form */}
            <View style={styles.addForm}>
              <Text style={styles.sectionTitle}>Add New Gate</Text>

              {/* Name + Type row */}
              <View style={styles.formRow}>
                <View style={styles.formFieldFlex}>
                  <TextInput
                    style={styles.formInput}
                    placeholder="Gate name"
                    placeholderTextColor={colors.textQuaternary}
                    value={newName}
                    onChangeText={setNewName}
                  />
                </View>
              </View>

              {/* Type picker */}
              <Text style={styles.formLabel}>Type</Text>
              <TypePicker selected={newType} onSelect={setNewType} />

              {/* Location + Add button row */}
              <View style={styles.formRow}>
                <View style={styles.formFieldFlex}>
                  <TextInput
                    style={styles.formInput}
                    placeholder="Location"
                    placeholderTextColor={colors.textQuaternary}
                    value={newLocation}
                    onChangeText={setNewLocation}
                  />
                </View>
                <TouchableOpacity
                  style={[styles.addButton, !newName.trim() && styles.addButtonDisabled]}
                  onPress={handleAddGate}
                  activeOpacity={0.8}
                  disabled={!newName.trim()}
                >
                  <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                    <Path
                      d="M12 5v14M5 12h14"
                      stroke={colors.white}
                      strokeWidth={2.5}
                      strokeLinecap="round"
                    />
                  </Svg>
                  <Text style={styles.addButtonText}>Add Gate</Text>
                </TouchableOpacity>
              </View>
            </View>

            {/* Divider */}
            <View style={styles.divider} />

            {/* Current Gates */}
            <View style={styles.gatesSection}>
              <View style={styles.gatesSectionHeader}>
                <Text style={styles.sectionTitle}>Current Gates</Text>
                <View style={styles.gateCountBadge}>
                  <Text style={styles.gateCountText}>{gates.length}</Text>
                </View>
              </View>

              {gates.length === 0 ? (
                <View style={styles.emptyState}>
                  <Text style={styles.emptyText}>No gates configured</Text>
                </View>
              ) : (
                gates.map(gate => (
                  <GateCard
                    key={gate.id}
                    gate={gate}
                    onToggle={handleToggle}
                    onDelete={handleDelete}
                  />
                ))
              )}
            </View>
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
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    paddingHorizontal: 20,
    paddingTop: 20,
    paddingBottom: 20,
  },
  addForm: {
    backgroundColor: colors.surface,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 16,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  formRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 10,
  },
  formFieldFlex: {
    flex: 1,
  },
  formLabel: {
    fontSize: 12,
    fontWeight: '500',
    color: colors.textTertiary,
    marginBottom: 8,
    letterSpacing: 0.3,
  },
  formInput: {
    height: 44,
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: 14,
    fontSize: 14,
    color: colors.textPrimary,
  },
  typePicker: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 12,
  },
  typeOption: {
    flex: 1,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'rgba(255,255,255,0.03)',
    alignItems: 'center',
  },
  typeOptionText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textTertiary,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.purple,
    borderRadius: 10,
    paddingHorizontal: 16,
    height: 44,
    gap: 6,
  },
  addButtonDisabled: {
    opacity: 0.4,
  },
  addButtonText: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.white,
  },
  divider: {
    height: 1,
    backgroundColor: colors.border,
    marginVertical: 20,
  },
  gatesSection: {
    marginBottom: 12,
  },
  gatesSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  gateCountBadge: {
    backgroundColor: colors.purpleLight,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
  },
  gateCountText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.purple,
  },
  gateCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 14,
    marginBottom: 10,
  },
  gateCardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  gateIcon: {
    width: 38,
    height: 38,
    borderRadius: 10,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  gateInfo: {
    flex: 1,
    marginLeft: 12,
  },
  gateName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  gateLocation: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  typeBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
    borderWidth: 1,
  },
  typeBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  gateCardBottom: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  toggleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  toggleLabel: {
    fontSize: 12,
    fontWeight: '500',
    color: colors.textSecondary,
  },
  deleteButton: {
    width: 34,
    height: 34,
    borderRadius: 8,
    backgroundColor: colors.redBg,
    borderWidth: 1,
    borderColor: colors.redBorder,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 30,
  },
  emptyText: {
    fontSize: 14,
    color: colors.textTertiary,
  },
});
