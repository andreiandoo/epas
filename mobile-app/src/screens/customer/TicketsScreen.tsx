import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  StyleSheet,
  RefreshControl,
  ActivityIndicator,
  Platform,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../store/authStore';
import { customerService } from '../../services/api';
import { Ticket } from '../../types';

export function CustomerTicketsScreen() {
  const navigation = useNavigation<any>();
  const auth = useAuthStore((state) => state.auth);
  const [tickets, setTickets] = useState<Ticket[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchTickets = async () => {
    if (!auth) return;
    try {
      const response = await customerService.getTickets(auth.token);
      setTickets(response.tickets);
    } catch (e) {
      console.error('Failed to fetch tickets:', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchTickets();
  }, []);

  const onRefresh = () => {
    setRefreshing(true);
    fetchTickets();
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'valid':
        return '#10b981';
      case 'used':
        return '#6b7280';
      case 'void':
        return '#ef4444';
      default:
        return '#6b7280';
    }
  };

  const renderTicket = ({ item }: { item: Ticket }) => (
    <TouchableOpacity
      style={styles.ticketCard}
      onPress={() => navigation.navigate('TicketDetail', { ticketCode: item.code })}
    >
      <View style={styles.ticketHeader}>
        <Text style={styles.eventTitle} numberOfLines={1}>
          {item.event.title}
        </Text>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) }]}>
          <Text style={styles.statusText}>{item.status.toUpperCase()}</Text>
        </View>
      </View>

      <View style={styles.ticketInfo}>
        <View style={styles.infoRow}>
          <Ionicons name="calendar-outline" size={16} color="#6b7280" />
          <Text style={styles.infoText}>{item.event.event_date}</Text>
        </View>
        {item.event.venue_name && (
          <View style={styles.infoRow}>
            <Ionicons name="location-outline" size={16} color="#6b7280" />
            <Text style={styles.infoText}>{item.event.venue_name}</Text>
          </View>
        )}
        <View style={styles.infoRow}>
          <Ionicons name="ticket-outline" size={16} color="#6b7280" />
          <Text style={styles.infoText}>{item.ticket_type.name}</Text>
        </View>
        {item.seat_label && (
          <View style={styles.infoRow}>
            <Ionicons name="grid-outline" size={16} color="#6b7280" />
            <Text style={styles.infoText}>Seat: {item.seat_label}</Text>
          </View>
        )}
      </View>

      <View style={styles.ticketFooter}>
        <Text style={styles.ticketCode}>#{item.code}</Text>
        <Ionicons name="chevron-forward" size={20} color="#9ca3af" />
      </View>
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#6366f1" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={tickets}
        renderItem={renderTicket}
        keyExtractor={(item) => item.id}
        contentContainerStyle={styles.list}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.empty}>
            <Ionicons name="ticket-outline" size={64} color="#d1d5db" />
            <Text style={styles.emptyText}>No tickets yet</Text>
            <Text style={styles.emptySubtext}>
              Your purchased tickets will appear here
            </Text>
          </View>
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  list: {
    padding: 16,
  },
  ticketCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  ticketHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  eventTitle: {
    flex: 1,
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginRight: 8,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 10,
    fontWeight: '600',
    color: '#fff',
  },
  ticketInfo: {
    marginBottom: 12,
  },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
  },
  infoText: {
    marginLeft: 8,
    fontSize: 14,
    color: '#6b7280',
  },
  ticketFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#f3f4f6',
  },
  ticketCode: {
    fontSize: 12,
    color: '#9ca3af',
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  empty: {
    alignItems: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#374151',
    marginTop: 16,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
});
