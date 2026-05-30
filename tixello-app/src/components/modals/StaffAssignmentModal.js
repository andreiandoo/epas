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
  Animated,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { useEvent } from '../../context/EventContext';
import { getTeamMembers, inviteTeamMember, removeTeamMember, updateTeamMember, activateTeamMember } from '../../api/team';
import { getVenueGates } from '../../api/gates';
import { getEvents } from '../../api/events';
import useSwipeToDismiss from '../../hooks/useSwipeToDismiss';
import { categorizeEvent } from '../../utils/eventCategories';

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

function MemberCard({
  member,
  gates,
  upcomingEvents,
  isExpanded,
  isEditingEvents,
  editingEventIds,
  savingEvents,
  onToggleExpand,
  onAssignGate,
  onRemove,
  onActivate,
  onOpenEventEditor,
  onToggleEditingEvent,
  onSaveEventEditor,
  onCancelEventEditor,
}) {
  const isOwner = member.role === 'owner';
  const isAdmin = member.role === 'admin';
  const assignedGate = gates.find(g => g.id === member.gate_id);
  const eventIds = Array.isArray(member.event_ids) ? member.event_ids : [];
  const accessLabel = isOwner || isAdmin
    ? 'Toate evenimentele'
    : (eventIds.length === 0 ? 'Toate evenimentele' : `${eventIds.length} eveniment${eventIds.length === 1 ? '' : 'e'}`);

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
        {member.status === 'pending' ? (
          <TouchableOpacity
            style={[
              styles.statusBadge,
              {
                backgroundColor: getStatusBg(member.status),
                borderColor: getStatusBorder(member.status),
              },
            ]}
            onPress={() => onActivate(member.id)}
            activeOpacity={0.7}
          >
            <Svg width={12} height={12} viewBox="0 0 24 24" fill="none">
              <Path d="M20 6L9 17l-5-5" stroke={colors.green} strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" />
            </Svg>
            <Text style={[styles.statusBadgeText, { color: colors.green }]}>
              Activează
            </Text>
          </TouchableOpacity>
        ) : (
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
        )}

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

      {/* Event whitelist row + editor (not for owner/admin) */}
      {!isOwner && !isAdmin && (
        <View style={styles.eventAccessRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.eventAccessLabel}>Acces la evenimente</Text>
            <Text style={styles.eventAccessValue}>{accessLabel}</Text>
          </View>
          <TouchableOpacity
            style={styles.eventAccessBtn}
            onPress={() => (isEditingEvents ? onCancelEventEditor() : onOpenEventEditor(member))}
            activeOpacity={0.7}
          >
            <Text style={styles.eventAccessBtnText}>{isEditingEvents ? 'Anulează' : 'Modifică'}</Text>
          </TouchableOpacity>
        </View>
      )}

      {isEditingEvents && (
        <View style={styles.eventEditorBox}>
          {upcomingEvents.length === 0 ? (
            <Text style={styles.eventPickerEmpty}>Nu există evenimente viitoare.</Text>
          ) : (
            <View style={styles.eventPickerList}>
              {upcomingEvents.map(ev => {
                const isSel = editingEventIds.includes(ev.id);
                const dateLabel = ev.starts_at
                  ? new Date(ev.starts_at).toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' })
                  : '';
                return (
                  <TouchableOpacity
                    key={ev.id}
                    style={[styles.eventPickerRow, isSel && styles.eventPickerRowSelected]}
                    onPress={() => onToggleEditingEvent(ev.id)}
                    activeOpacity={0.7}
                  >
                    <View style={[styles.checkbox, isSel && styles.checkboxSelected]}>
                      {isSel && (
                        <Svg width={12} height={12} viewBox="0 0 24 24" fill="none">
                          <Path d="M20 6L9 17l-5-5" stroke={colors.white} strokeWidth={3} strokeLinecap="round" strokeLinejoin="round" />
                        </Svg>
                      )}
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.eventPickerName} numberOfLines={1}>{ev.name || ev.title}</Text>
                      {dateLabel ? (
                        <Text style={styles.eventPickerDate}>{dateLabel}</Text>
                      ) : null}
                    </View>
                  </TouchableOpacity>
                );
              })}
            </View>
          )}
          <Text style={styles.eventPickerHint}>
            Nu selecta nimic = acces la toate evenimentele viitoare.
          </Text>
          <TouchableOpacity
            style={[styles.addButton, savingEvents && styles.addButtonDisabled, { marginTop: 8 }]}
            onPress={onSaveEventEditor}
            activeOpacity={0.8}
            disabled={savingEvents}
          >
            {savingEvents ? (
              <ActivityIndicator size="small" color={colors.white} />
            ) : (
              <Text style={styles.addButtonText}>Salvează</Text>
            )}
          </TouchableOpacity>
        </View>
      )}

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
  const { translateY, panResponder } = useSwipeToDismiss(onClose);
  const { selectedEvent } = useEvent();
  const [members, setMembers] = useState([]);
  const [gates, setGates] = useState([]);
  const [upcomingEvents, setUpcomingEvents] = useState([]);
  const [loading, setLoading] = useState(false);
  const [inviting, setInviting] = useState(false);
  const [expandedMemberId, setExpandedMemberId] = useState(null);
  // Per-existing-member event whitelist editor state. Keyed by member.id —
  // null means "panel collapsed". When open holds the in-progress checkbox
  // state until the user taps Save.
  const [editingEventsForMember, setEditingEventsForMember] = useState(null);
  const [editingEventIds, setEditingEventIds] = useState([]);
  const [savingEvents, setSavingEvents] = useState(false);

  // Add-staff form state. The flow is direct-add now (not invite-by-email):
  // organizer fills first/last name + email + password + role + optional gate;
  // backend creates the member as active and emails them their credentials.
  const [inviteFirstName, setInviteFirstName] = useState('');
  const [inviteLastName, setInviteLastName] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [invitePassword, setInvitePassword] = useState('');
  const [inviteRole, setInviteRole] = useState('staff');
  const [inviteGate, setInviteGate] = useState(null);
  const [inviteEventIds, setInviteEventIds] = useState([]);

  // Activation password state
  const [activatingMemberId, setActivatingMemberId] = useState(null);
  const [activatePassword, setActivatePassword] = useState('');

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

      // Fetch upcoming events (live + today + future — exclude past). Used by
      // both the Add-staff form and the per-member event whitelist editor.
      try {
        const evResp = await getEvents({ per_page: 100, published_only: true });
        const evList = evResp.data || evResp || [];
        const enriched = (Array.isArray(evList) ? evList : []).map(e => ({
          ...e,
          timeCategory: categorizeEvent(e),
        }));
        const upcoming = enriched.filter(e => e.timeCategory !== 'past');
        setUpcomingEvents(upcoming);
      } catch (e) {
        console.error('Failed to fetch events for staff whitelist:', e);
        setUpcomingEvents([]);
      }

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

  const toggleInviteEvent = (eventId) => {
    setInviteEventIds(prev =>
      prev.includes(eventId) ? prev.filter(id => id !== eventId) : [...prev, eventId]
    );
  };

  const openEventEditor = (member) => {
    setEditingEventsForMember(member.id);
    setEditingEventIds(Array.isArray(member.event_ids) ? [...member.event_ids] : []);
  };

  const toggleEditingEvent = (eventId) => {
    setEditingEventIds(prev =>
      prev.includes(eventId) ? prev.filter(id => id !== eventId) : [...prev, eventId]
    );
  };

  const saveEventEditor = async () => {
    if (editingEventsForMember == null) return;
    setSavingEvents(true);
    try {
      const resp = await updateTeamMember({
        member_id: editingEventsForMember,
        event_ids: editingEventIds,
      });
      const updated = resp.data?.member || resp.member;
      setMembers(prev => prev.map(m => {
        if (m.id !== editingEventsForMember) return m;
        return { ...m, event_ids: updated?.event_ids ?? editingEventIds };
      }));
      setEditingEventsForMember(null);
      setEditingEventIds([]);
    } catch (e) {
      console.error('Failed to save event whitelist:', e);
      Alert.alert('Eroare', e.message || 'Nu s-a putut salva lista de evenimente.');
    }
    setSavingEvents(false);
  };

  const handleInvite = async () => {
    const fullName = `${inviteFirstName.trim()} ${inviteLastName.trim()}`.trim();
    // Inline validation that always tells the user what's missing,
    // rather than silently leaving the button disabled.
    if (!inviteEmail.trim()) {
      Alert.alert('Eroare', 'Adresa de email este obligatorie.');
      return;
    }
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRe.test(inviteEmail.trim())) {
      Alert.alert('Eroare', 'Adresa de email este invalidă.');
      return;
    }
    if (!invitePassword || invitePassword.length < 8) {
      Alert.alert('Eroare', 'Parola trebuie să aibă cel puțin 8 caractere.');
      return;
    }

    setInviting(true);
    try {
      const payload = {
        name: fullName,
        email: inviteEmail.trim(),
        password: invitePassword,
        role: inviteRole,
        permissions: inviteRole === 'staff' ? ['checkin'] : undefined,
        gate_id: inviteGate ?? null,
        // Empty array = "all events" by backend convention; admins ignore it.
        event_ids: inviteRole === 'admin' ? [] : inviteEventIds,
      };

      const response = await inviteTeamMember(payload);
      const newMember = response.data?.member || response.data || response.member || {
        id: String(Date.now()),
        name: fullName,
        email: inviteEmail.trim(),
        role: inviteRole,
        status: 'active',
        gate_id: inviteGate ?? null,
        permissions: inviteRole === 'staff' ? ['checkin'] : ['events', 'orders', 'reports', 'team', 'checkin'],
        event_ids: inviteRole === 'admin' ? [] : inviteEventIds,
      };
      setMembers(prev => [...prev, newMember]);
      setInviteFirstName('');
      setInviteLastName('');
      setInviteEmail('');
      setInvitePassword('');
      setInviteRole('staff');
      setInviteGate(null);
      setInviteEventIds([]);
      const emailSent = response.data?.email_sent ?? response.email_sent;
      Alert.alert(
        'Succes',
        emailSent === false
          ? 'Membrul a fost adăugat. Email-ul de bun venit nu a putut fi trimis — comunică-i credențialele direct.'
          : 'Membrul a fost adăugat și a primit credențialele pe email.'
      );
    } catch (e) {
      console.error('Failed to add member:', e);
      Alert.alert('Eroare', e.message || 'Nu s-a putut adăuga membrul.');
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

  const handleActivate = (memberId) => {
    setActivatingMemberId(memberId);
    setActivatePassword('');
  };

  const confirmActivate = async () => {
    if (!activatePassword || activatePassword.length < 6) {
      Alert.alert('Eroare', 'Parola trebuie sa aiba cel putin 6 caractere.');
      return;
    }

    const memberId = activatingMemberId;
    setActivatingMemberId(null);
    setActivatePassword('');

    // Optimistic update
    setMembers(prev =>
      prev.map(m => m.id === memberId ? { ...m, status: 'active' } : m)
    );

    try {
      await activateTeamMember(memberId, activatePassword);
      Alert.alert('Succes', 'Contul a fost activat.');
    } catch (e) {
      console.error('Failed to activate member:', e);
      setMembers(prev =>
        prev.map(m => m.id === memberId ? { ...m, status: 'pending' } : m)
      );
      Alert.alert('Eroare', e.message || 'Nu s-a putut activa contul.');
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
        <Animated.View style={[styles.sheet, { transform: [{ translateY }] }]}>
          {/* Header */}
          <View style={styles.header} {...panResponder.panHandlers}>
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
                      upcomingEvents={upcomingEvents}
                      isExpanded={expandedMemberId === member.id}
                      isEditingEvents={editingEventsForMember === member.id}
                      editingEventIds={editingEventIds}
                      savingEvents={savingEvents}
                      onToggleExpand={() => setExpandedMemberId(
                        expandedMemberId === member.id ? null : member.id
                      )}
                      onAssignGate={handleAssignGate}
                      onActivate={handleActivate}
                      onRemove={handleRemove}
                      onOpenEventEditor={openEventEditor}
                      onToggleEditingEvent={toggleEditingEvent}
                      onSaveEventEditor={saveEventEditor}
                      onCancelEventEditor={() => { setEditingEventsForMember(null); setEditingEventIds([]); }}
                    />
                  ))
                )}
              </View>

              {/* Divider */}
              <View style={styles.divider} />

              {/* Add New Staff Form (direct add — no invite token) */}
              <View style={styles.addForm}>
                <Text style={styles.sectionTitle}>Adaugă Personal Nou</Text>

                {/* First name */}
                <Text style={styles.formLabel}>Nume (opțional)</Text>
                <TextInput
                  style={styles.formInput}
                  placeholder="Nume"
                  placeholderTextColor={colors.textQuaternary}
                  value={inviteFirstName}
                  onChangeText={setInviteFirstName}
                />

                {/* Last name */}
                <Text style={styles.formLabel}>Prenume (opțional)</Text>
                <TextInput
                  style={styles.formInput}
                  placeholder="Prenume"
                  placeholderTextColor={colors.textQuaternary}
                  value={inviteLastName}
                  onChangeText={setInviteLastName}
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

                {/* Password */}
                <Text style={styles.formLabel}>Parolă (minim 8 caractere)</Text>
                <TextInput
                  style={styles.formInput}
                  placeholder="Parola"
                  placeholderTextColor={colors.textQuaternary}
                  value={invitePassword}
                  onChangeText={setInvitePassword}
                  secureTextEntry
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

                {/* Event whitelist (manager/staff only — admins get full access) */}
                {inviteRole !== 'admin' && (
                  <>
                    <Text style={styles.formLabel}>Acces la evenimente {inviteEventIds.length === 0 ? '(toate)' : `(${inviteEventIds.length} selectate)`}</Text>
                    {upcomingEvents.length === 0 ? (
                      <Text style={styles.eventPickerEmpty}>Nu există evenimente viitoare.</Text>
                    ) : (
                      <View style={styles.eventPickerList}>
                        {upcomingEvents.map(ev => {
                          const isSel = inviteEventIds.includes(ev.id);
                          const dateLabel = ev.starts_at
                            ? new Date(ev.starts_at).toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' })
                            : '';
                          return (
                            <TouchableOpacity
                              key={ev.id}
                              style={[styles.eventPickerRow, isSel && styles.eventPickerRowSelected]}
                              onPress={() => toggleInviteEvent(ev.id)}
                              activeOpacity={0.7}
                            >
                              <View style={[styles.checkbox, isSel && styles.checkboxSelected]}>
                                {isSel && (
                                  <Svg width={12} height={12} viewBox="0 0 24 24" fill="none">
                                    <Path d="M20 6L9 17l-5-5" stroke={colors.white} strokeWidth={3} strokeLinecap="round" strokeLinejoin="round" />
                                  </Svg>
                                )}
                              </View>
                              <View style={{ flex: 1 }}>
                                <Text style={styles.eventPickerName} numberOfLines={1}>{ev.name || ev.title}</Text>
                                {dateLabel ? (
                                  <Text style={styles.eventPickerDate}>{dateLabel}</Text>
                                ) : null}
                              </View>
                            </TouchableOpacity>
                          );
                        })}
                      </View>
                    )}
                    <Text style={styles.eventPickerHint}>
                      Nu selecta nimic = acces la toate evenimentele viitoare.
                    </Text>
                  </>
                )}

                {/* Add Button — always tappable so the user gets explicit
                    feedback from handleInvite() about what's missing
                    (email format, password length, etc.). Only disabled
                    while the request is in flight. */}
                <TouchableOpacity
                  style={[styles.addButton, inviting && styles.addButtonDisabled]}
                  onPress={handleInvite}
                  activeOpacity={0.8}
                  disabled={inviting}
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
                  <Text style={styles.addButtonText}>Adaugă în echipă</Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          )}

          {/* Password activation modal */}
          {activatingMemberId !== null && (
            <View style={styles.passwordOverlay}>
              <View style={styles.passwordCard}>
                <Text style={styles.passwordTitle}>Setează Parola</Text>
                <Text style={styles.passwordDescription}>
                  Introdu o parolă pentru ca membrul să se poată autentifica în aplicație.
                </Text>
                <TextInput
                  style={styles.passwordInput}
                  placeholder="Minim 6 caractere"
                  placeholderTextColor={colors.textQuaternary}
                  value={activatePassword}
                  onChangeText={setActivatePassword}
                  secureTextEntry
                  autoFocus
                />
                <View style={styles.passwordButtons}>
                  <TouchableOpacity
                    style={styles.passwordCancelBtn}
                    onPress={() => { setActivatingMemberId(null); setActivatePassword(''); }}
                    activeOpacity={0.7}
                  >
                    <Text style={styles.passwordCancelText}>Anulează</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.passwordConfirmBtn, (!activatePassword || activatePassword.length < 6) && { opacity: 0.4 }]}
                    onPress={confirmActivate}
                    activeOpacity={0.8}
                  >
                    <Text style={styles.passwordConfirmText}>Activează</Text>
                  </TouchableOpacity>
                </View>
              </View>
            </View>
          )}
        </Animated.View>
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
  // Event whitelist
  eventAccessRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingTop: 10,
    marginTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    gap: 10,
  },
  eventAccessLabel: {
    fontSize: 11,
    color: colors.textTertiary,
    fontWeight: '500',
  },
  eventAccessValue: {
    fontSize: 13,
    color: colors.textPrimary,
    fontWeight: '600',
    marginTop: 2,
  },
  eventAccessBtn: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: 8,
    backgroundColor: colors.purpleLight,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
  },
  eventAccessBtnText: {
    fontSize: 12,
    fontWeight: '600',
    color: colors.purple,
  },
  eventEditorBox: {
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  eventPickerList: {
    gap: 6,
    marginBottom: 6,
  },
  eventPickerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.03)',
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 10,
    padding: 10,
    gap: 10,
  },
  eventPickerRowSelected: {
    backgroundColor: colors.purpleLight,
    borderColor: colors.purpleBorder,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 5,
    borderWidth: 1.5,
    borderColor: colors.textTertiary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxSelected: {
    backgroundColor: colors.purple,
    borderColor: colors.purple,
  },
  eventPickerName: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.textPrimary,
  },
  eventPickerDate: {
    fontSize: 11,
    color: colors.textTertiary,
    marginTop: 2,
  },
  eventPickerEmpty: {
    fontSize: 12,
    color: colors.textTertiary,
    paddingVertical: 8,
    textAlign: 'center',
  },
  eventPickerHint: {
    fontSize: 11,
    color: colors.textQuaternary,
    fontStyle: 'italic',
    marginTop: 4,
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
  // Password activation modal
  passwordOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.7)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 24,
  },
  passwordCard: {
    backgroundColor: '#1E1E2E',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 24,
    width: '100%',
  },
  passwordTitle: {
    fontSize: 17,
    fontWeight: '700',
    color: colors.textPrimary,
    marginBottom: 8,
  },
  passwordDescription: {
    fontSize: 13,
    color: colors.textSecondary,
    marginBottom: 16,
    lineHeight: 18,
  },
  passwordInput: {
    height: 48,
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: 14,
    fontSize: 15,
    color: colors.textPrimary,
    marginBottom: 20,
  },
  passwordButtons: {
    flexDirection: 'row',
    gap: 10,
  },
  passwordCancelBtn: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
  },
  passwordCancelText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.textSecondary,
  },
  passwordConfirmBtn: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 10,
    backgroundColor: colors.green,
    alignItems: 'center',
  },
  passwordConfirmText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.white,
  },
});
