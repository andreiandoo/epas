import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';

export function AdminReportsScreen() {
  // Mock data for demonstration
  const stats = {
    totalSales: 12450,
    ticketsSold: 234,
    checkIns: 189,
    checkInRate: 81,
  };

  return (
    <ScrollView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Dashboard</Text>
        <Text style={styles.headerSubtitle}>Real-time event analytics</Text>
      </View>

      <View style={styles.statsGrid}>
        <View style={styles.statCard}>
          <View style={[styles.statIcon, { backgroundColor: '#dcfce7' }]}>
            <Ionicons name="cash-outline" size={24} color="#16a34a" />
          </View>
          <Text style={styles.statValue}>
            ${stats.totalSales.toLocaleString()}
          </Text>
          <Text style={styles.statLabel}>Total Sales</Text>
        </View>

        <View style={styles.statCard}>
          <View style={[styles.statIcon, { backgroundColor: '#e0e7ff' }]}>
            <Ionicons name="ticket-outline" size={24} color="#6366f1" />
          </View>
          <Text style={styles.statValue}>{stats.ticketsSold}</Text>
          <Text style={styles.statLabel}>Tickets Sold</Text>
        </View>

        <View style={styles.statCard}>
          <View style={[styles.statIcon, { backgroundColor: '#fef3c7' }]}>
            <Ionicons name="people-outline" size={24} color="#d97706" />
          </View>
          <Text style={styles.statValue}>{stats.checkIns}</Text>
          <Text style={styles.statLabel}>Check-ins</Text>
        </View>

        <View style={styles.statCard}>
          <View style={[styles.statIcon, { backgroundColor: '#fce7f3' }]}>
            <Ionicons name="trending-up-outline" size={24} color="#db2777" />
          </View>
          <Text style={styles.statValue}>{stats.checkInRate}%</Text>
          <Text style={styles.statLabel}>Check-in Rate</Text>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Recent Activity</Text>
        <View style={styles.activityCard}>
          <View style={styles.activityItem}>
            <Ionicons name="checkmark-circle" size={20} color="#10b981" />
            <Text style={styles.activityText}>Ticket #TKT-8273 checked in</Text>
            <Text style={styles.activityTime}>2m ago</Text>
          </View>
          <View style={styles.activityItem}>
            <Ionicons name="cart" size={20} color="#6366f1" />
            <Text style={styles.activityText}>New order $125.00</Text>
            <Text style={styles.activityTime}>5m ago</Text>
          </View>
          <View style={styles.activityItem}>
            <Ionicons name="checkmark-circle" size={20} color="#10b981" />
            <Text style={styles.activityText}>Ticket #TKT-8271 checked in</Text>
            <Text style={styles.activityTime}>8m ago</Text>
          </View>
        </View>
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Sales by Ticket Type</Text>
        <View style={styles.chartPlaceholder}>
          <Ionicons name="bar-chart-outline" size={48} color="#d1d5db" />
          <Text style={styles.placeholderText}>Chart visualization</Text>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f9fafb',
  },
  header: {
    padding: 20,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e5e7eb',
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
  },
  headerSubtitle: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    padding: 8,
  },
  statCard: {
    width: '50%',
    padding: 8,
  },
  statIcon: {
    width: 48,
    height: 48,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  statValue: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
  },
  statLabel: {
    fontSize: 14,
    color: '#6b7280',
    marginTop: 4,
  },
  section: {
    padding: 16,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 12,
  },
  activityCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 16,
  },
  activityItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f3f4f6',
  },
  activityText: {
    flex: 1,
    marginLeft: 12,
    fontSize: 14,
    color: '#374151',
  },
  activityTime: {
    fontSize: 12,
    color: '#9ca3af',
  },
  chartPlaceholder: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  placeholderText: {
    color: '#9ca3af',
    marginTop: 12,
  },
});
