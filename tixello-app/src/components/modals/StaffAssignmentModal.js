import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  Modal,
  StyleSheet,
  ScrollView,
  TextInput,
  Dimensions,
  Alert,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { useEvent } from '../../context/EventContext';
import { getTeamMembers, inviteTeamMember, removeTeamMember, updateTeamMember } from '../../api/team';
import { getVenueGates } from '../../api/gates';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

const ROLES = ['admin', 'manager', 'staff'];
const ROLE_DISPLAY = { admin: 'Admin', manager: 'Manager', staff: 'Staff' };

function getInitials(name) {
  if (!name) return '??';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

function getRoleColor(role) {
  switch (role) {
    case 'admin': return colors.amber;
    case 'manager': return colors.cyan;
    case 'staff': return colors.green;
    case 'owner': return colors.purple;
    default: return colors.textSecondary;
  }
}

function getRoleBg(role) {
  switch (role) {
    case 'admin': return colors.amberLight;
    case 'manager': return colors.cyanLight;
    case 'staff': return colors.greenLight;
    case 'owner': return colors.purpleLight;
    default: return 'rgba(255,255,255,0.05)';
  }
}

function getRoleBorder(role) {
  switch (role) {
    case 'admin': return colors.amberBorder;
    case 'manager': return colors.cyanBorder;
    case 'staff': return colors.greenBorder;
    case 'owner': return colors.purpleBorder;
    default: return 'rgba(255,255,255,0.08)';
  }
}

function getRoleDisplayLabel(role) {
  if (role === 'owner') return 'Proprietar';
  return ROLE_DISPLAY[role] || role;
}

function getStatusColor(status) {
  switch (status) {
    case 'active': return colors.green;
    case 'pending': return colors.amber;
    case 'inactive': return colors.textTertiary;
    default: return colors.textSecondary;
  }
}

function getStatusBg(status) {
  switch (status) {
    case 'active': return colors.greenLight;
    case 'pending': return colors.amberLight;
    case 'inactive': return 'rgba(255,255,255,0.05)';
    default: return 'rgba(255,255,255,0.05)';
  }
}

function getStatusBorder(status) {
  switch (status) {
    case 'active': return colors.greenBorder;
    case 'pending': return colors.amberBorder;
    case 'inactive': return 'rgba(255,255,255,0.08)';
    default: return 'rgba(255,255,255,0.08)';
  }
}

function getStatusLabel(status) {
  switch (status) {
    case 'active': return 'Activ';
    case 'pending': return 'În așteptare';
    case 'inactive': return 'Inactiv';
    default: return status;
  }
}

function OptionPicker({ options, selected, onSelect, getColor, getBg, getBorder, getLabel }) {
  return (
    <View style={styles.optionPicker}>
      {options.map((option) => {
        const isSelected = selected === option;
        const optionColor = getColor ? getColor(option) : colors.purple;
        const optionBg = getBg ? getBg(option) : colors.purpleLight;
        const optionBorder = getBorder ? getBorder(option) : colors.purpleBorder;
        const label = getLabel ? getLabel(option) : option;

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
              {label}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

function MemberCard({ member, gates, isExpanded, onToggleExpand, onAssignGate, onRemove }) {
  const isOwner = member.role === 'owner';
  const assignedGate = gates.find(g => g.id === member.gate_id);

  return (
    <View style={[styles.memberCard, isExpanded && styles.memberCardExpanded]}>
      <TouchableOpacity
        style={styles.memberTop}
        onPress={onToggleExpand}
        activeOpacity={0.7}
      >
        {/* Avatar */}
        <View style={[styles.avatar, { backgroundColor: getRoleColor(member.role) }]}>
          <Text style={styles.avatarText}>{getInitials(member.name)}</Text>
        </View>

        {/* Info */}
        <View style={styles.memberInfo}>
          <View style={styles.memberNameRow}>
            <Text style={styles.memberName}>{member.name}</Text>
            {isOwner && (
              <View style={[styles.ownerBadge]}>
                <Text style={styles.ownerBadgeText}>Proprietar</Text>
              </View>
            )}
          </View>
          <Text style={styles.memberEmail}>{member.email}</Text>
        </View>

        {/* Remove button - only for non-owner */}
        {!isOwner && (
          <TouchableOpacity
            style={styles.removeButton}
            onPress={() => onRemove(member.id)}
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
        )}
      </TouchableOpacity>

      <View style={styles.memberBottom}>
        {/* Status badge */}
        <View
          style={[
            styles.statusBadge,
            {
              backgroundColor: getStatusBg(member.status),
              borderColor: getStatusBorder(member.status),
            },
          ]}
        >
          <View style={[styles.statusDot, { backgroundColor: getStatusColor(member.status) }]} />
          <Text style={[styles.statusBadgeText, { color: getStatusColor(member.status) }]}>
            {getStatusLabel(member.status)}
          </Text>
        </View>

        {/* Gate badge */}
        {assignedGate ? (
          <View style={[styles.gateBadge, { backgroundColor: colors.cyanLight, borderColor: colors.cyanBorder }]}>
            <Svg width={10} height={10} viewBox="0 0 24 24" fill="none">
              <Path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" stroke={colors.cyan} strokeWidth={2} strokeLinecap="round" />
            </Svg>
            <Text style={[styles.gateBadgeText, { color: colors.cyan }]}>{assignedGate.name}</Text>
          </View>
        ) : (
          <TouchableOpacity
            style={[styles.gateBadge, { backgroundColor: 'rgba(255,255,255,0.05)', borderColor: colors.border }]}
            onPress={onToggleExpand}
            activeOpacity={0.7}
          >
            <Text style={[styles.gateBadgeText, { color: colors.textTertiary }]}>+ Poartă</Text>
          </TouchableOpacity>
        )}

        {/* Role badge */}
        <View
          style={[
            styles.roleBadge,
            {
              backgroundColor: getRoleBg(member.role),
              borderColor: getRoleBorder(member.role),
            },
          ]}
        >
          <Text style={[styles.roleBadgeText, { color: getRoleColor(member.role) }]}>
            {getRoleDisplayLabel(member.role)}
          </Text>
        </View>
      </View>

      {/* Gate assignment picker (expanded) */}
      {isExpanded && gates.length > 0 && (
        <View style={styles.gatePickerSection}>
          <Text style={styles.gatePickerLabel}>Alocă la poartă:</Text>
          <View style={styles.gatePickerRow}>
            <TouchableOpacity
              style={[
                styles.gatePickerItem,
                !member.gate_id && styles.gatePickerItemSelected,
              ]}
              onPress={() => onAssignGate(member.id, null)}
              activeOpacity={0.7}
            >
              <Text style={[
                styles.gatePickerItemText,
                !member.gate_id && { color: colors.purple },
              ]}>Niciuna</Text>
            </TouchableOpacity>
            {gates.map(gate => {
              const isSelected = member.gate_id === gate.id;
              return (
                <TouchableOpacity
                  key={gate.id}
                  style={[
                    styles.gatePickerItem,
                    isSelected && styles.gatePickerItemSelected,
                  ]}
                  onPress={() => onAssignGate(member.id, gate.id)}
                  activeOpacity={0.7}
                >
                  <Text style={[
                    styles.gatePickerItemText,
                    isSelected && { color: colors.purple },
                  ]}>{gate.name}</Text>
                </TouchableOpacity>
              );
            })}
          </View>
        </View>
      )}
      {isExpanded && gates.length === 0 && (
        <View style={styles.gatePickerSection}>
          <Text style={styles.gatePickerLabel}>Nu există porți configurate. Adaugă porți din Administrare Porți.</Text>
        </View>
      )}
    </View>
  );
}

export default function StaffAssignmentModal({ visible, onClose }) {
  const { selectedEvent } = useEvent();
  const [members, setMembers] = useState([]);
  const [gates, setGates] = useState([]);
  const [loading, setLoading] = useState(false);
  const [inviting, setInviting] = useState(false);
  const [expandedMemberId, setExpandedMemberId] = useState(null);

  // Invite form state
  const [inviteName, setInviteName] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState('staff');
  const [inviteGate, setInviteGate] = useState(null);

  const venueId = selectedEvent?.venue_id;

  // Fetch team members and gates when modal opens
  useEffect(() => {
    if (visible) {
      fetchData();
    }
  }, [visible]);

  const fetchData = async () => {
    setLoading(true);
    try {
      // Fetch team members
      const teamResponse = await getTeamMembers();
      const teamData = teamResponse.data?.members || teamResponse.data || teamResponse.members || [];
      setMembers(Array.isArray(teamData) ? teamData : []);

      // Fetch gates if venue exists
      if (venueId) {
        try {
          const gatesResponse = await getVenueGates(venueId);
          const gatesData = gatesResponse.data?.gates || gatesResponse.data || gatesResponse.gates || [];
          setGates(Array.isArray(gatesData) ? gatesData : []);
        } catch (e) {
          console.error('Failed to fetch gates:', e);
          setGates([]);
        }
      }
    } catch (e) {
      console.error('Failed to fetch team:', e);
      Alert.alert('Eroare', 'Nu s-au putut încărca membrii echipei.');
    }
    setLoading(false);
  };

  const handleInvite = async () => {
    if (!inviteName.trim() || !inviteEmail.trim()) return;

    setInviting(true);
    try {
      const payload = {
        name: inviteName.trim(),
        email: inviteEmail.trim(),
        role: inviteRole,
        permissions: ['checkin'],
      };

      const response = await inviteTeamMember(payload);
      const newMember = response.data?.member || response.data || response.member || {
        id: String(Date.now()),
        name: inviteName.trim(),
        email: inviteEmail.trim(),
        role: inviteRole,
        status: 'pending',
        permissions: ['checkin'],
      };
      setMembers(prev => [...prev, newMember]);
      setInviteName('');
      setInviteEmail('');
      setInviteRole('staff');
      setInviteGate(null);
      Alert.alert('Succes', 'Invitația a fost trimisă.');
    } catch (e) {
      console.error('Failed to invite member:', e);
      Alert.alert('Eroare', e.message || 'Nu s-a putut trimite invitația.');
    }
    setInviting(false);
  };

  const handleAssignGate = async (memberId, gateId) => {
    // Optimistic update
    setMembers(prev =>
      prev.map(m => m.id === memberId ? { ...m, gate_id: gateId } : m)
    );
    setExpandedMemberId(null);

    try {
      await updateTeamMember({ member_id: memberId, gate_id: gateId });
    } catch (e) {
      console.error('Failed to assign gate:', e);
      // Revert on error
      setMembers(prev =>
        prev.map(m => m.id === memberId ? { ...m, gate_id: null } : m)
      );
    }
  };

  const handleRemove = async (memberId) => {
    Alert.alert(
      'Elimină membru',
      'Sigur doriți să eliminați acest membru din echipă?',
      [
        { text: 'Anulează', style: 'cancel' },
        {
          text: 'Elimină',
          style: 'destructive',
          onPress: async () => {
            const previousMembers = [...members];
            setMembers(prev => prev.filter(m => m.id !== memberId));

            try {
              await removeTeamMember(memberId);
            } catch (e) {
              console.error('Failed to remove member:', e);
              setMembers(previousMembers);
              Alert.alert('Eroare', 'Nu s-a putut elimina membrul.');
            }
          },
        },
      ]
    );
  };

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <KeyboardAvoidingView
        style={styles.overlay}
        behavior="padding"
      >
        <TouchableOpacity style={styles.overlayTouchable} onPress={onClose} activeOpacity={1} />
        <View style={styles.sheet}>
          {/* Header */}
          <View style={styles.header}>
            <View style={styles.handle} />
            <View style={styles.headerRow}>
              <Text style={styles.title}>Echipă & Personal</Text>
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

          {loading ? (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color={colors.purple} />
              <Text style={styles.loadingText}>Se încarcă echipa...</Text>
            </View>
          ) : (
            <ScrollView
              style={styles.scrollView}
              contentContainerStyle={styles.scrollContent}
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
            >
              {/* Existing Team Members */}
              <View style={styles.membersSection}>
                <View style={styles.membersSectionHeader}>
                  <Text style={styles.sectionTitle}>Echipă Existentă</Text>
                  <View style={styles.countBadge}>
                    <Text style={styles.countBadgeText}>{members.length}</Text>
                  </View>
                </View>

                {members.length === 0 ? (
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
                    <Text style={styles.emptyText}>Niciun membru în echipă</Text>
                    <Text style={styles.emptySubtext}>Invitați personal folosind formularul de mai jos</Text>
                  </View>
                ) : (
                  members.map(member => (
                    <MemberCard
                      key={member.id}
                      member={member}
                      gates={gates}
                      isExpanded={expandedMemberId === member.id}
                      onToggleExpand={() => setExpandedMemberId(
                        expandedMemberId === member.id ? null : member.id
                      )}
                      onAssignGate={handleAssignGate}
                      onRemove={handleRemove}
                    />
                  ))
                )}
              </View>

              {/* Divider */}
              <View style={styles.divider} />

              {/* Invite New Staff Form */}
              <View style={styles.addForm}>
                <Text style={styles.sectionTitle}>Invită Personal Nou</Text>

                {/* Name */}
                <Text style={styles.formLabel}>Nume</Text>
                <TextInput
                  style={styles.formInput}
                  placeholder="Numele complet"
                  placeholderTextColor={colors.textQuaternary}
                  value={inviteName}
                  onChangeText={setInviteName}
                />

                {/* Email */}
                <Text style={styles.formLabel}>Email</Text>
                <TextInput
                  style={styles.formInput}
                  placeholder="adresa@email.com"
                  placeholderTextColor={colors.textQuaternary}
                  value={inviteEmail}
                  onChangeText={setInviteEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                />

                {/* Role Picker */}
                <Text style={styles.formLabel}>Rol</Text>
                <OptionPicker
                  options={ROLES}
                  selected={inviteRole}
                  onSelect={setInviteRole}
                  getColor={getRoleColor}
                  getBg={getRoleBg}
                  getBorder={getRoleBorder}
                  getLabel={getRoleDisplayLabel}
                />

                {/* Gate Picker (optional) */}
                {gates.length > 0 && (
                  <>
                    <Text style={styles.formLabel}>Poartă (opțional)</Text>
                    <View style={styles.optionPicker}>
                      <TouchableOpacity
                        style={[
                          styles.optionItem,
                          inviteGate === null && {
                            backgroundColor: colors.purpleLight,
                            borderColor: colors.purpleBorder,
                          },
                        ]}
                        onPress={() => setInviteGate(null)}
                        activeOpacity={0.7}
                      >
                        <Text
                          style={[
                            styles.optionItemText,
                            inviteGate === null && { color: colors.purple },
                          ]}
                        >
                          Niciuna
                        </Text>
                      </TouchableOpacity>
                      {gates.map((gate) => {
                        const isSelected = inviteGate === gate.id;
                        return (
                          <TouchableOpacity
                            key={gate.id}
                            style={[
                              styles.optionItem,
                              isSelected && {
                                backgroundColor: colors.purpleLight,
                                borderColor: colors.purpleBorder,
                              },
                            ]}
                            onPress={() => setInviteGate(gate.id)}
                            activeOpacity={0.7}
                          >
                            <Text
                              style={[
                                styles.optionItemText,
                                isSelected && { color: colors.purple },
                              ]}
                            >
                              {gate.name}
                            </Text>
                          </TouchableOpacity>
                        );
                      })}
                    </View>
                  </>
                )}

                {/* Invite Button */}
                <TouchableOpacity
                  style={[styles.addButton, (!inviteName.trim() || !inviteEmail.trim() || inviting) && styles.addButtonDisabled]}
                  onPress={handleInvite}
                  activeOpacity={0.8}
                  disabled={!inviteName.trim() || !inviteEmail.trim() || inviting}
                >
                  {inviting ? (
                    <ActivityIndicator size="small" color={colors.white} />
                  ) : (
                    <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                      <Path
                        d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"
                        stroke={colors.white}
                        strokeWidth={2}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </Svg>
                  )}
                  <Text style={styles.addButtonText}>Trimite Invitație</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          )}
        </View>
      </KeyboardAvoidingView>
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
    paddingBottom: 120,
  },
  // Loading state
  loadingContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 12,
  },
  loadingText: {
    fontSize: 14,
    color: colors.textTertiary,
  },
  // Members section
  membersSection: {
    marginBottom: 12,
  },
  membersSectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 12,
  },
  countBadge: {
    backgroundColor: colors.purpleLight,
    borderRadius: 8,
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    marginBottom: 12,
  },
  countBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.purple,
  },
  // Member card
  memberCard: {
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 14,
    marginBottom: 10,
  },
  memberCardExpanded: {
    borderColor: colors.purpleBorder,
  },
  memberTop: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  avatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '700',
  },
  memberInfo: {
    flex: 1,
    marginLeft: 12,
  },
  memberNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 2,
  },
  memberName: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  memberEmail: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  ownerBadge: {
    backgroundColor: colors.purpleLight,
    borderRadius: 4,
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
  },
  ownerBadgeText: {
    fontSize: 9,
    fontWeight: '700',
    color: colors.purple,
    letterSpacing: 0.3,
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
  memberBottom: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
    borderWidth: 1,
  },
  statusDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.3,
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
  // Gate badge on member card
  gateBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: 6,
    borderWidth: 1,
  },
  gateBadgeText: {
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  // Gate picker (expanded)
  gatePickerSection: {
    paddingTop: 12,
    marginTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  gatePickerLabel: {
    fontSize: 12,
    fontWeight: '500',
    color: colors.textTertiary,
    marginBottom: 8,
  },
  gatePickerRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  gatePickerItem: {
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'rgba(255,255,255,0.03)',
  },
  gatePickerItemSelected: {
    backgroundColor: colors.purpleLight,
    borderColor: colors.purpleBorder,
  },
  gatePickerItemText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.textTertiary,
  },
  // Divider
  divider: {
    height: 1,
    backgroundColor: colors.border,
    marginVertical: 20,
  },
  // Add / Invite form
  addForm: {
    backgroundColor: colors.surface,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 16,
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
  // Empty state
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
