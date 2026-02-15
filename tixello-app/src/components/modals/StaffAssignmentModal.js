import React, { useState } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  TextInput,
  Dimensions,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

const GATES = ['Gate A', 'Gate B', 'VIP Entrance', 'Box Office 1', 'Box Office 2'];
const ROLES = ['Scanner', 'POS', 'Supervisor'];

function getInitials(name) {
  if (!name) return '??';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function getRoleColor(role) {
  switch (role) {
    case 'Scanner': return colors.green;
    case 'POS': return colors.cyan;
    case 'Supervisor': return colors.amber;
    default: return colors.textSecondary;
  }
}

function getRoleBg(role) {
  switch (role) {
    case 'Scanner': return colors.greenLight;
    case 'POS': return colors.cyanLight;
    case 'Supervisor': return colors.amberLight;
    default: return 'rgba(255,255,255,0.05)';
  }
}

function getRoleBorder(role) {
  switch (role) {
    case 'Scanner': return colors.greenBorder;
    case 'POS': return colors.cyanBorder;
    case 'Supervisor': return colors.amberBorder;
    default: return 'rgba(255,255,255,0.08)';
  }
}

function OptionPicker({ options, selected, onSelect, getColor, getBg, getBorder }) {
  return (
    <View style={styles.optionPicker}>
      {options.map((option) => {
        const isSelected = selected === option;
        const optionColor = getColor ? getColor(option) : colors.purple;
        const optionBg = getBg ? getBg(option) : colors.purpleLight;
        const optionBorder = getBorder ? getBorder(option) : colors.purpleBorder;

        return (
          <TouchableOpacity
            key={option}
            style={[
              styles.optionItem,
              isSelected && {
                backgroundColor: optionBg,
                borderColor: optionBorder,
              },
            ]}
            onPress={() => onSelect(option)}
            activeOpacity={0.7}
          >
            <Text
              style={[
                styles.optionItemText,
                isSelected && { color: optionColor },
              ]}
            >
              {option}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

function AssignmentCard({ assignment, onRemove }) {
  return (
    <View style={styles.assignmentCard}>
      <View style={styles.assignmentTop}>
        {/* Avatar */}
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{getInitials(assignment.name)}</Text>
        </View>

        {/* Info */}
        <View style={styles.assignmentInfo}>
          <Text style={styles.assignmentName}>{assignment.name}</Text>
          <Text style={styles.assignmentGate}>{assignment.gate}</Text>
        </View>

        {/* Remove button */}
        <TouchableOpacity
          style={styles.removeButton}
          onPress={() => onRemove(assignment.id)}
          activeOpacity={0.7}
        >
          <Svg width={16} height={16} viewBox="0 0 24 24" fill="none">
            <Path
              d="M18 6L6 18M6 6l12 12"
              stroke={colors.red}
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
        </TouchableOpacity>
      </View>

      <View style={styles.assignmentBottom}>
        {/* Shift time */}
        <View style={styles.shiftInfo}>
          <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
            <Path
              d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 6v6l4 2"
              stroke={colors.textTertiary}
              strokeWidth={1.5}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
          </Svg>
          <Text style={styles.shiftText}>
            {assignment.shiftStart} - {assignment.shiftEnd}
          </Text>
        </View>

        {/* Role badge */}
        <View
          style={[
            styles.roleBadge,
            {
              backgroundColor: getRoleBg(assignment.role),
              borderColor: getRoleBorder(assignment.role),
            },
          ]}
        >
          <Text style={[styles.roleBadgeText, { color: getRoleColor(assignment.role) }]}>
            {assignment.role}
          </Text>
        </View>
      </View>
    </View>
  );
}

export default function StaffAssignmentModal({ visible, onClose }) {
  const [assignments, setAssignments] = useState([]);
  const [staffName, setStaffName] = useState('');
  const [selectedGate, setSelectedGate] = useState(GATES[0]);
  const [selectedRole, setSelectedRole] = useState(ROLES[0]);
  const [shiftStart, setShiftStart] = useState('');
  const [shiftEnd, setShiftEnd] = useState('');

  const handleAdd = () => {
    if (!staffName.trim()) return;

    const newAssignment = {
      id: String(Date.now()),
      name: staffName.trim(),
      gate: selectedGate,
      role: selectedRole,
      shiftStart: shiftStart.trim() || '09:00',
      shiftEnd: shiftEnd.trim() || '17:00',
    };

    setAssignments(prev => [...prev, newAssignment]);
    setStaffName('');
    setShiftStart('');
    setShiftEnd('');
  };

  const handleRemove = (assignmentId) => {
    setAssignments(prev => prev.filter(a => a.id !== assignmentId));
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
              <Text style={styles.title}>Staff Assignment & Scheduling</Text>
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
            {/* Add Assignment Form */}
            <View style={styles.addForm}>
              <Text style={styles.sectionTitle}>Add Assignment</Text>

              {/* Staff Name */}
              <Text style={styles.formLabel}>Staff Name</Text>
              <TextInput
                style={styles.formInput}
                placeholder="Enter staff member name"
                placeholderTextColor={colors.textQuaternary}
                value={staffName}
                onChangeText={setStaffName}
              />

              {/* Gate Picker */}
              <Text style={styles.formLabel}>Gate</Text>
              <OptionPicker
                options={GATES}
                selected={selectedGate}
                onSelect={setSelectedGate}
              />

              {/* Role Picker */}
              <Text style={styles.formLabel}>Role</Text>
              <OptionPicker
                options={ROLES}
                selected={selectedRole}
                onSelect={setSelectedRole}
                getColor={getRoleColor}
                getBg={getRoleBg}
                getBorder={getRoleBorder}
              />

              {/* Shift Times */}
              <Text style={styles.formLabel}>Shift Times</Text>
              <View style={styles.timeRow}>
                <View style={styles.timeField}>
                  <Text style={styles.timeFieldLabel}>Start</Text>
                  <TextInput
                    style={styles.formInput}
                    placeholder="09:00"
                    placeholderTextColor={colors.textQuaternary}
                    value={shiftStart}
                    onChangeText={setShiftStart}
                    keyboardType="numbers-and-punctuation"
                  />
                </View>
                <View style={styles.timeSeparator}>
                  <Text style={styles.timeSeparatorText}>to</Text>
                </View>
                <View style={styles.timeField}>
                  <Text style={styles.timeFieldLabel}>End</Text>
                  <TextInput
                    style={styles.formInput}
                    placeholder="17:00"
                    placeholderTextColor={colors.textQuaternary}
                    value={shiftEnd}
                    onChangeText={setShiftEnd}
                    keyboardType="numbers-and-punctuation"
                  />
                </View>
              </View>

              {/* Add Button */}
              <TouchableOpacity
                style={[styles.addButton, !staffName.trim() && styles.addButtonDisabled]}
                onPress={handleAdd}
                activeOpacity={0.8}
                disabled={!staffName.trim()}
              >
                <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                  <Path
                    d="M12 5v14M5 12h14"
                    stroke={colors.white}
                    strokeWidth={2.5}
                    strokeLinecap="round"
                  />
                </Svg>
                <Text style={styles.addButtonText}>Add Assignment</Text>
              </TouchableOpacity>
            </View>

            {/* Divider */}
            <View style={styles.divider} />

            {/* Current Assignments */}
            <View style={styles.assignmentsSection}>
              <View style={styles.assignmentsSectionHeader}>
                <Text style={styles.sectionTitle}>Current Assignments</Text>
                <View style={styles.countBadge}>
                  <Text style={styles.countBadgeText}>{assignments.length}</Text>
                </View>
              </View>

              {assignments.length === 0 ? (
                <View style={styles.emptyState}>
                  <Svg width={40} height={40} viewBox="0 0 24 24" fill="none">
                    <Path
                      d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                      stroke={colors.textTertiary}
                      strokeWidth={1.5}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </Svg>
                  <Text style={styles.emptyText}>No staff assignments yet</Text>
                  <Text style={styles.emptySubtext}>Add staff members using the form above</Text>
                </View>
              ) : (
                assignments.map(assignment => (
                  <AssignmentCard
                    key={assignment.id}
                    assignment={assignment}
                    onRemove={handleRemove}
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
    fontSize: 18,
    fontWeight: '700',
    color: colors.textPrimary,
    letterSpacing: 0.3,
    flex: 1,
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
    marginLeft: 12,
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
  formLabel: {
    fontSize: 12,
    fontWeight: '500',
    color: colors.textTertiary,
    marginBottom: 8,
    marginTop: 4,
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
    marginBottom: 4,
  },
  optionPicker: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    marginBottom: 8,
  },
  optionItem: {
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'rgba(255,255,255,0.03)',
  },
  optionItemText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textTertiary,
  },
  timeRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 8,
    marginBottom: 8,
  },
  timeField: {
    flex: 1,
  },
  timeFieldLabel: {
    fontSize: 10,
    fontWeight: '500',
    color: colors.textQuaternary,
    marginBottom: 4,
    letterSpacing: 0.3,
  },
  timeSeparator: {
    paddingBottom: 14,
  },
  timeSeparatorText: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  addButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.purple,
    borderRadius: 10,
    paddingVertical: 14,
    marginTop: 12,
    gap: 8,
  },
  addButtonDisabled: {
    opacity: 0.4,
  },
  addButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.white,
  },
  divider: {
    height: 1,
    backgroundColor: colors.border,
    marginVertical: 20,
  },
  assignmentsSection: {
    marginBottom: 12,
  },
  assignmentsSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  countBadge: {
    backgroundColor: colors.purpleLight,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
  },
  countBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.purple,
  },
  assignmentCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 14,
    marginBottom: 10,
  },
  assignmentTop: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.purple,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '700',
  },
  assignmentInfo: {
    flex: 1,
    marginLeft: 12,
  },
  assignmentName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  assignmentGate: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  removeButton: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: colors.redBg,
    borderWidth: 1,
    borderColor: colors.redBorder,
    alignItems: 'center',
    justifyContent: 'center',
  },
  assignmentBottom: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  shiftInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  shiftText: {
    fontSize: 12,
    color: colors.textSecondary,
    fontWeight: '500',
  },
  roleBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
    borderWidth: 1,
  },
  roleBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  emptyState: {
    alignItems: 'center',
    paddingVertical: 40,
    gap: 8,
  },
  emptyText: {
    fontSize: 14,
    fontWeight: '500',
    color: colors.textTertiary,
  },
  emptySubtext: {
    fontSize: 12,
    color: colors.textQuaternary,
  },
});
