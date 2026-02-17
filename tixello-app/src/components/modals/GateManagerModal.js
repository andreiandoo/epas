import React, { useState, useEffect } from 'react';
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
  ActivityIndicator,
} from 'react-native';
import Svg, { Path } from 'react-native-svg';
import { colors } from '../../theme/colors';
import { useEvent } from '../../context/EventContext';
import { getVenueGates, createVenueGate, updateVenueGate, deleteVenueGate } from '../../api/gates';

const { height: SCREEN_HEIGHT } = Dimensions.get('window');

// API type values mapped to Romanian display labels
const TYPE_API_TO_DISPLAY = {
  entry: 'Intrare',
  vip: 'VIP',
  pos: 'POS',
  exit: 'Ie\u0219ire',
};

const TYPE_DISPLAY_TO_API = {
  'Intrare': 'entry',
  'VIP': 'vip',
  'POS': 'pos',
  'Ie\u0219ire': 'exit',
};

const GATE_TYPES_DISPLAY = ['Intrare', 'VIP', 'POS', 'Ie\u0219ire'];

function getDisplayType(apiType) {
  return TYPE_API_TO_DISPLAY[apiType] || apiType;
}

function getApiType(displayType) {
  return TYPE_DISPLAY_TO_API[displayType] || displayType;
}

function GateTypeIcon({ type, size = 18 }) {
  const displayType = TYPE_API_TO_DISPLAY[type] || type;
  const iconColor = getTypeColor(displayType);
  switch (displayType) {
    case 'Intrare':
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
    case 'Ie\u0219ire':
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
  const displayType = TYPE_API_TO_DISPLAY[type] || type;
  switch (displayType) {
    case 'Intrare': return colors.green;
    case 'VIP': return colors.amber;
    case 'POS': return colors.cyan;
    case 'Ie\u0219ire': return colors.red;
    default: return colors.textSecondary;
  }
}

function getTypeBg(type) {
  const displayType = TYPE_API_TO_DISPLAY[type] || type;
  switch (displayType) {
    case 'Intrare': return colors.greenLight;
    case 'VIP': return colors.amberLight;
    case 'POS': return colors.cyanLight;
    case 'Ie\u0219ire': return colors.redLight;
    default: return 'rgba(255,255,255,0.05)';
  }
}

function getTypeBorder(type) {
  const displayType = TYPE_API_TO_DISPLAY[type] || type;
  switch (displayType) {
    case 'Intrare': return colors.greenBorder;
    case 'VIP': return colors.amberBorder;
    case 'POS': return colors.cyanBorder;
    case 'Ie\u0219ire': return colors.redBorder;
    default: return 'rgba(255,255,255,0.08)';
  }
}

function TypeBadge({ type }) {
  const displayType = TYPE_API_TO_DISPLAY[type] || type;
  return (
    <View style={[styles.typeBadge, { backgroundColor: getTypeBg(displayType), borderColor: getTypeBorder(displayType) }]}>
      <Text style={[styles.typeBadgeText, { color: getTypeColor(displayType) }]}>{displayType}</Text>
    </View>
  );
}

function TypePicker({ selected, onSelect }) {
  return (
    <View style={styles.typePicker}>
      {GATE_TYPES_DISPLAY.map((type) => {
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

function GateCard({ gate, onToggle, onDelete, onAssignSelf }) {
  const displayType = getDisplayType(gate.type);
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
          <Text style={styles.gateLocation}>{gate.location || 'Nespecificat'}</Text>
        </View>

        {/* Type badge */}
        <TypeBadge type={gate.type} />
      </View>

      <View style={styles.gateCardBottom}>
        {/* Active toggle */}
        <View style={styles.toggleRow}>
          <Text style={styles.toggleLabel}>{gate.is_active ? 'Activ\u0103' : 'Inactiv\u0103'}</Text>
          <Switch
            value={gate.is_active}
            onValueChange={() => onToggle(gate.id)}
            trackColor={{ false: 'rgba(255,255,255,0.1)', true: colors.greenLight }}
            thumbColor={gate.is_active ? colors.green : colors.textTertiary}
          />
        </View>

        <View style={styles.gateCardActions}>
          {/* Self-assign button */}
          <TouchableOpacity
            style={styles.assignSelfButton}
            onPress={() => onAssignSelf(gate.id)}
            activeOpacity={0.7}
          >
            <Svg width={14} height={14} viewBox="0 0 24 24" fill="none">
              <Path
                d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"
                stroke={colors.purple}
                strokeWidth={1.8}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </Svg>
            <Text style={styles.assignSelfText}>Asigneaz\u0103-m\u0103</Text>
          </TouchableOpacity>

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
    </View>
  );
}

export default function GateManagerModal({ visible, onClose }) {
  const { selectedEvent } = useEvent();
  const [gates, setGates] = useState([]);
  const [loading, setLoading] = useState(false);
  const [newName, setNewName] = useState('');
  const [newType, setNewType] = useState('Intrare');
  const [newLocation, setNewLocation] = useState('');
  const [adding, setAdding] = useState(false);

  const venueId = selectedEvent?.venue_id;
  const venueName = selectedEvent?.venue_name || selectedEvent?.venue?.name || '';
  const venueCity = selectedEvent?.venue_city || selectedEvent?.venue?.city || '';
  const venueAddress = selectedEvent?.venue_address || selectedEvent?.venue?.address || '';

  // Fetch gates when modal opens
  useEffect(() => {
    if (visible && venueId) {
      fetchGates();
    }
    if (visible && !venueId) {
      setGates([]);
    }
  }, [visible, venueId]);

  const fetchGates = async () => {
    setLoading(true);
    try {
      const response = await getVenueGates(venueId);
      const gatesData = response.data?.gates || response.data || response.gates || [];
      setGates(Array.isArray(gatesData) ? gatesData : []);
    } catch (e) {
      console.error('Failed to fetch gates:', e);
      Alert.alert('Eroare', 'Nu s-au putut \u00eenc\u0103rca por\u021bile.');
    }
    setLoading(false);
  };

  const handleAddGate = async () => {
    if (!newName.trim() || !venueId) return;

    setAdding(true);
    try {
      const apiType = getApiType(newType);
      const response = await createVenueGate(venueId, {
        name: newName.trim(),
        type: apiType,
        location: newLocation.trim() || null,
      });
      const newGate = response.data?.gate || response.data || response.gate || response;
      setGates(prev => [...prev, newGate]);
      setNewName('');
      setNewLocation('');
      setNewType('Intrare');
    } catch (e) {
      console.error('Failed to create gate:', e);
      Alert.alert('Eroare', 'Nu s-a putut crea poarta.');
    }
    setAdding(false);
  };

  const handleToggle = async (gateId) => {
    const gate = gates.find(g => g.id === gateId);
    if (!gate || !venueId) return;

    const newActive = !gate.is_active;
    // Optimistic update
    setGates(prev =>
      prev.map(g => g.id === gateId ? { ...g, is_active: newActive } : g)
    );

    try {
      await updateVenueGate(venueId, gateId, { is_active: newActive });
    } catch (e) {
      console.error('Failed to toggle gate:', e);
      // Revert on error
      setGates(prev =>
        prev.map(g => g.id === gateId ? { ...g, is_active: !newActive } : g)
      );
      Alert.alert('Eroare', 'Nu s-a putut actualiza starea por\u021bii.');
    }
  };

  const handleDelete = async (gateId) => {
    if (!venueId) return;

    Alert.alert(
      '\u0218terge poarta',
      'Sigur dori\u021bi s\u0103 \u0219terge\u021bi aceast\u0103 poart\u0103?',
      [
        { text: 'Anuleaz\u0103', style: 'cancel' },
        {
          text: '\u0218terge',
          style: 'destructive',
          onPress: async () => {
            const previousGates = [...gates];
            setGates(prev => prev.filter(g => g.id !== gateId));

            try {
              await deleteVenueGate(venueId, gateId);
            } catch (e) {
              console.error('Failed to delete gate:', e);
              setGates(previousGates);
              Alert.alert('Eroare', 'Nu s-a putut \u0219terge poarta.');
            }
          },
        },
      ]
    );
  };

  const handleAssignSelf = (gateId) => {
    // Visual only for now
    Alert.alert('Asignare', 'Func\u021bionalitatea de auto-asignare va fi disponibil\u0103 \u00een cur\u00e2nd.');
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
              <Text style={styles.title}>Administrare Por\u021bi</Text>
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

          {!venueId ? (
            /* No venue associated */
            <View style={styles.noVenueContainer}>
              <Svg width={48} height={48} viewBox="0 0 24 24" fill="none">
                <Path
                  d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"
                  stroke={colors.textTertiary}
                  strokeWidth={1.5}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
                <Path
                  d="M12 13a3 3 0 100-6 3 3 0 000 6z"
                  stroke={colors.textTertiary}
                  strokeWidth={1.5}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
              </Svg>
              <Text style={styles.noVenueText}>Niciun loc asociat evenimentului</Text>
              <Text style={styles.noVenueSubtext}>Asocia\u021bi un loc evenimentului pentru a gestiona por\u021bile</Text>
            </View>
          ) : (
            <ScrollView
              style={styles.scrollView}
              contentContainerStyle={styles.scrollContent}
              showsVerticalScrollIndicator={false}
            >
              {/* Venue Info */}
              <View style={styles.venueInfo}>
                <View style={styles.venueIconContainer}>
                  <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
                    <Path
                      d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"
                      stroke={colors.purple}
                      strokeWidth={1.8}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                    <Path
                      d="M12 13a3 3 0 100-6 3 3 0 000 6z"
                      stroke={colors.purple}
                      strokeWidth={1.8}
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </Svg>
                </View>
                <View style={styles.venueDetails}>
                  <Text style={styles.venueName}>{venueName}</Text>
                  {(venueCity || venueAddress) && (
                    <Text style={styles.venueAddress}>
                      {[venueCity, venueAddress].filter(Boolean).join(' \u2022 ')}
                    </Text>
                  )}
                </View>
              </View>

              {/* Loading state */}
              {loading ? (
                <View style={styles.loadingContainer}>
                  <ActivityIndicator size="large" color={colors.purple} />
                  <Text style={styles.loadingText}>Se \u00eencarc\u0103 por\u021bile...</Text>
                </View>
              ) : (
                <>
                  {/* Add New Gate Form */}
                  <View style={styles.addForm}>
                    <Text style={styles.sectionTitle}>Adaug\u0103 Poart\u0103 Nou\u0103</Text>

                    {/* Name + Type row */}
                    <View style={styles.formRow}>
                      <View style={styles.formFieldFlex}>
                        <TextInput
                          style={styles.formInput}
                          placeholder="Numele por\u021bii"
                          placeholderTextColor={colors.textQuaternary}
                          value={newName}
                          onChangeText={setNewName}
                        />
                      </View>
                    </View>

                    {/* Type picker */}
                    <Text style={styles.formLabel}>Tip</Text>
                    <TypePicker selected={newType} onSelect={setNewType} />

                    {/* Location + Add button row */}
                    <View style={styles.formRow}>
                      <View style={styles.formFieldFlex}>
                        <TextInput
                          style={styles.formInput}
                          placeholder="Loca\u021bie"
                          placeholderTextColor={colors.textQuaternary}
                          value={newLocation}
                          onChangeText={setNewLocation}
                        />
                      </View>
                      <TouchableOpacity
                        style={[styles.addButton, (!newName.trim() || adding) && styles.addButtonDisabled]}
                        onPress={handleAddGate}
                        activeOpacity={0.8}
                        disabled={!newName.trim() || adding}
                      >
                        {adding ? (
                          <ActivityIndicator size="small" color={colors.white} />
                        ) : (
                          <Svg width={18} height={18} viewBox="0 0 24 24" fill="none">
                            <Path
                              d="M12 5v14M5 12h14"
                              stroke={colors.white}
                              strokeWidth={2.5}
                              strokeLinecap="round"
                            />
                          </Svg>
                        )}
                        <Text style={styles.addButtonText}>Adaug\u0103 Poart\u0103</Text>
                      </TouchableOpacity>
                    </View>
                  </View>

                  {/* Divider */}
                  <View style={styles.divider} />

                  {/* Current Gates */}
                  <View style={styles.gatesSection}>
                    <View style={styles.gatesSectionHeader}>
                      <Text style={styles.sectionTitle}>Por\u021bi Curente</Text>
                      <View style={styles.gateCountBadge}>
                        <Text style={styles.gateCountText}>{gates.length}</Text>
                      </View>
                    </View>

                    {gates.length === 0 ? (
                      <View style={styles.emptyState}>
                        <Text style={styles.emptyText}>Nicio poart\u0103 configurat\u0103</Text>
                      </View>
                    ) : (
                      gates.map(gate => (
                        <GateCard
                          key={gate.id}
                          gate={gate}
                          onToggle={handleToggle}
                          onDelete={handleDelete}
                          onAssignSelf={handleAssignSelf}
                        />
                      ))
                    )}
                  </View>
                </>
              )}
            </ScrollView>
          )}
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
  // Venue info section
  venueInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    padding: 14,
    marginBottom: 16,
  },
  venueIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: colors.purpleLight,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
    alignItems: 'center',
    justifyContent: 'center',
  },
  venueDetails: {
    flex: 1,
    marginLeft: 12,
  },
  venueName: {
    fontSize: 15,
    fontWeight: '600',
    color: colors.textPrimary,
    marginBottom: 2,
  },
  venueAddress: {
    fontSize: 12,
    color: colors.textTertiary,
  },
  // No venue state
  noVenueContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 40,
    gap: 12,
  },
  noVenueText: {
    fontSize: 16,
    fontWeight: '600',
    color: colors.textSecondary,
    textAlign: 'center',
  },
  noVenueSubtext: {
    fontSize: 13,
    color: colors.textTertiary,
    textAlign: 'center',
  },
  // Loading state
  loadingContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
    gap: 12,
  },
  loadingText: {
    fontSize: 14,
    color: colors.textTertiary,
  },
  // Add form
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
  gateCardActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  assignSelfButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    paddingHorizontal: 10,
    paddingVertical: 7,
    borderRadius: 8,
    backgroundColor: colors.purpleLight,
    borderWidth: 1,
    borderColor: colors.purpleBorder,
  },
  assignSelfText: {
    fontSize: 11,
    fontWeight: '600',
    color: colors.purple,
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
