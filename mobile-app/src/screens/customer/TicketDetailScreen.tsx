import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuthStore } from '../../store/authStore';
import { customerService } from '../../services/api';
import { Ticket } from '../../types';

export function TicketDetailScreen({ route }: any) {
  const { ticketCode } = route.params;
  const auth = useAuthStore((state) => state.auth);
  const [ticket, setTicket] = useState<Ticket | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTicket = async () => {
      if (!auth) return;
      try {
        const response = await customerService.getTicket(auth.token, ticketCode);
        setTicket(response.ticket);
      } catch (e) {
        console.error('Failed to fetch ticket:', e);
      } finally {
        setLoading(false);
      }
    };

    fetchTicket();
  }, [ticketCode]);

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#6366f1" />
      </View>
    );
  }

  if (!ticket) {
    return (
      <View style={styles.centered}>
        <Ionicons name="alert-circle-outline" size={64} color="#ef4444" />
        <Text style={styles.errorText}>Ticket not found</Text>
      </View>
    );
  }

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

  return (
    <ScrollView style={styles.container}>
      <View style={styles.qrSection}>
        {/* QR Code placeholder - in real app, render actual QR */}
        <View style={styles.qrPlaceholder}>
          <Ionicons name="qr-code" size={120} color="#111827" />
        </View>
        <Text style={styles.ticketCode}>#{ticket.code}</Text>
      </View>

      <View style={styles.card}>
        <View style={styles.statusRow}>
          <Text style={styles.label}>Status</Text>
          <View style={[styles.statusBadge, { backgroundColor: getStatusColor(ticket.status) }]}>
            <Text style={styles.statusText}>{ticket.status.toUpperCase()}</Text>
          </View>
        </View>

        <Text style={styles.eventTitle}>{ticket.event.title}</Text>

        <View style={styles.infoSection}>
          <View style={styles.infoRow}>
            <Ionicons name="calendar-outline" size={20} color="#6b7280" />
            <Text style={styles.infoText}>{ticket.event.event_date}</Text>
          </View>

          {ticket.event.start_time && (
            <View style={styles.infoRow}>
              <Ionicons name="time-outline" size={20} color="#6b7280" />
              <Text style={styles.infoText}>{ticket.event.start_time}</Text>
            </View>
          )}

          {ticket.event.venue_name && (
            <View style={styles.infoRow}>
              <Ionicons name="location-outline" size={20} color="#6b7280" />
              <Text style={styles.infoText}>{ticket.event.venue_name}</Text>
            </View>
          )}

          <View style={styles.infoRow}>
            <Ionicons name="ticket-outline" size={20} color="#6b7280" />
            <Text style={styles.infoText}>{ticket.ticket_type.name}</Text>
          </View>

          {ticket.seat_label && (
            <View style={styles.infoRow}>
              <Ionicons name="grid-outline" size={20} color="#6b7280" />
              <Text style={styles.infoText}>Seat: {ticket.seat_label}</Text>
            </View>
          )}
        </View>
      </View>

      <View style={styles.actions}>
        <TouchableOpacity style={styles.walletButton}>
          <Ionicons name="wallet-outline" size={20} color="#fff" />
          <Text style={styles.walletButtonText}>Add to Wallet</Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.shareButton}>
          <Ionicons name="share-outline" size={20} color="#6366f1" />
          <Text style={styles.shareButtonText}>Share</Text>
        </TouchableOpacity>
      </View>
    </ScrollView>
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
  errorText: {
    fontSize: 16,
    color: '#ef4444',
    marginTop: 16,
  },
  qrSection: {
    alignItems: 'center',
    paddingVertical: 32,
    backgroundColor: '#fff',
  },
  qrPlaceholder: {
    width: 200,
    height: 200,
    backgroundColor: '#f3f4f6',
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 8,
  },
  ticketCode: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 12,
    fontFamily: 'monospace',
  },
  card: {
    backgroundColor: '#fff',
    margin: 16,
    borderRadius: 12,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
  },
  statusRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  label: {
    fontSize: 14,
    color: '#6b7280',
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 4,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#fff',
  },
  eventTitle: {
    fontSize: 22,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 20,
  },
  infoSection: {},
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  infoText: {
    marginLeft: 12,
    fontSize: 16,
    color: '#374151',
  },
  actions: {
    padding: 16,
    gap: 12,
  },
  walletButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#6366f1',
    borderRadius: 8,
    paddingVertical: 16,
  },
  walletButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 8,
  },
  shareButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#fff',
    borderWidth: 2,
    borderColor: '#6366f1',
    borderRadius: 8,
    paddingVertical: 14,
  },
  shareButtonText: {
    color: '#6366f1',
    fontSize: 16,
    fontWeight: '600',
    marginLeft: 8,
  },
});
