import * as SQLite from 'expo-sqlite';
import { Platform } from 'react-native';
import { Ticket } from '../types';

const DB_NAME = 'epas_offline.db';

class OfflineDatabase {
  private db: SQLite.SQLiteDatabase | null = null;

  async init(): Promise<void> {
    if (Platform.OS === 'web') {
      console.log('SQLite not available on web, using memory store');
      return;
    }

    this.db = await SQLite.openDatabaseAsync(DB_NAME);

    // Create tables
    await this.db.execAsync(`
      CREATE TABLE IF NOT EXISTS cached_tickets (
        id TEXT PRIMARY KEY,
        event_id TEXT NOT NULL,
        code TEXT UNIQUE NOT NULL,
        qr_data TEXT,
        status TEXT DEFAULT 'valid',
        customer_email TEXT,
        customer_name TEXT,
        ticket_type TEXT,
        seat_label TEXT,
        event_title TEXT,
        event_date TEXT,
        venue_name TEXT,
        checked_in_at TEXT,
        gate_ref TEXT,
        synced INTEGER DEFAULT 1,
        cached_at TEXT DEFAULT CURRENT_TIMESTAMP
      );

      CREATE TABLE IF NOT EXISTS pending_sync (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT NOT NULL,
        ticket_code TEXT NOT NULL,
        timestamp TEXT NOT NULL,
        gate_ref TEXT,
        device_id TEXT,
        synced INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
      );

      CREATE INDEX IF NOT EXISTS idx_tickets_code ON cached_tickets(code);
      CREATE INDEX IF NOT EXISTS idx_tickets_event ON cached_tickets(event_id);
      CREATE INDEX IF NOT EXISTS idx_sync_synced ON pending_sync(synced);
    `);
  }

  async cacheTickets(eventId: string, tickets: any[]): Promise<void> {
    if (!this.db) return;

    const stmt = await this.db.prepareAsync(`
      INSERT OR REPLACE INTO cached_tickets
      (id, event_id, code, qr_data, status, customer_email, ticket_type, seat_label,
       event_title, event_date, venue_name, cached_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    `);

    try {
      for (const ticket of tickets) {
        await stmt.executeAsync([
          ticket.id,
          eventId,
          ticket.code,
          ticket.qr_data || `TKT:${ticket.code}`,
          ticket.status,
          ticket.customer_email || '',
          ticket.ticket_type?.name || '',
          ticket.seat_label || '',
          ticket.event?.title || '',
          ticket.event?.event_date || '',
          ticket.event?.venue_name || '',
        ]);
      }
    } finally {
      await stmt.finalizeAsync();
    }
  }

  async findTicketByCode(code: string): Promise<any | null> {
    if (!this.db) return null;

    const result = await this.db.getFirstAsync<any>(
      'SELECT * FROM cached_tickets WHERE code = ?',
      [code]
    );

    return result;
  }

  async findTicketByQRData(qrData: string): Promise<any | null> {
    if (!this.db) return null;

    // Try direct match first
    let result = await this.db.getFirstAsync<any>(
      'SELECT * FROM cached_tickets WHERE qr_data = ?',
      [qrData]
    );

    if (!result) {
      // Try to extract code from QR data (format: TKT:CODE or INV:CODE:REF:CHECKSUM)
      const match = qrData.match(/(?:TKT:|INV:)([A-Z0-9-]+)/i);
      if (match) {
        result = await this.db.getFirstAsync<any>(
          'SELECT * FROM cached_tickets WHERE code = ?',
          [match[1]]
        );
      }
    }

    return result;
  }

  async markTicketUsed(code: string, gateRef?: string): Promise<void> {
    if (!this.db) return;

    await this.db.runAsync(
      `UPDATE cached_tickets
       SET status = 'used', checked_in_at = CURRENT_TIMESTAMP, gate_ref = ?, synced = 0
       WHERE code = ?`,
      [gateRef || '', code]
    );

    // Add to sync queue
    await this.db.runAsync(
      `INSERT INTO pending_sync (action, ticket_code, timestamp, gate_ref)
       VALUES ('CHECK_IN', ?, CURRENT_TIMESTAMP, ?)`,
      [code, gateRef || '']
    );
  }

  async getPendingSyncItems(): Promise<any[]> {
    if (!this.db) return [];

    return await this.db.getAllAsync(
      'SELECT * FROM pending_sync WHERE synced = 0 ORDER BY created_at ASC'
    );
  }

  async markSynced(ids: number[]): Promise<void> {
    if (!this.db || ids.length === 0) return;

    const placeholders = ids.map(() => '?').join(',');
    await this.db.runAsync(
      `UPDATE pending_sync SET synced = 1 WHERE id IN (${placeholders})`,
      ids
    );
  }

  async getTicketsForEvent(eventId: string): Promise<any[]> {
    if (!this.db) return [];

    return await this.db.getAllAsync(
      'SELECT * FROM cached_tickets WHERE event_id = ? ORDER BY code',
      [eventId]
    );
  }

  async getOfflineStats(): Promise<{ cached: number; pending: number }> {
    if (!this.db) return { cached: 0, pending: 0 };

    const cached = await this.db.getFirstAsync<{ count: number }>(
      'SELECT COUNT(*) as count FROM cached_tickets'
    );
    const pending = await this.db.getFirstAsync<{ count: number }>(
      'SELECT COUNT(*) as count FROM pending_sync WHERE synced = 0'
    );

    return {
      cached: cached?.count || 0,
      pending: pending?.count || 0,
    };
  }

  async clearEventCache(eventId: string): Promise<void> {
    if (!this.db) return;

    await this.db.runAsync(
      'DELETE FROM cached_tickets WHERE event_id = ?',
      [eventId]
    );
  }

  async clearAllCache(): Promise<void> {
    if (!this.db) return;

    await this.db.runAsync('DELETE FROM cached_tickets');
    await this.db.runAsync('DELETE FROM pending_sync');
  }
}

export const offlineDb = new OfflineDatabase();

// Web fallback with in-memory storage
class WebOfflineStore {
  private tickets: Map<string, any> = new Map();
  private syncQueue: any[] = [];

  async cacheTickets(eventId: string, tickets: any[]): Promise<void> {
    for (const ticket of tickets) {
      this.tickets.set(ticket.code, { ...ticket, event_id: eventId });
    }
  }

  async findTicketByCode(code: string): Promise<any | null> {
    return this.tickets.get(code) || null;
  }

  async findTicketByQRData(qrData: string): Promise<any | null> {
    const match = qrData.match(/(?:TKT:|INV:)([A-Z0-9-]+)/i);
    if (match) {
      return this.tickets.get(match[1]) || null;
    }
    return null;
  }

  async markTicketUsed(code: string, gateRef?: string): Promise<void> {
    const ticket = this.tickets.get(code);
    if (ticket) {
      ticket.status = 'used';
      ticket.checked_in_at = new Date().toISOString();
      ticket.gate_ref = gateRef;
      this.syncQueue.push({
        action: 'CHECK_IN',
        ticket_code: code,
        timestamp: new Date().toISOString(),
        gate_ref: gateRef,
      });
    }
  }

  async getPendingSyncItems(): Promise<any[]> {
    return this.syncQueue;
  }

  async markSynced(ids: number[]): Promise<void> {
    this.syncQueue = [];
  }

  async getOfflineStats(): Promise<{ cached: number; pending: number }> {
    return {
      cached: this.tickets.size,
      pending: this.syncQueue.length,
    };
  }
}

export const webOfflineStore = new WebOfflineStore();

// Export unified interface
export const getOfflineStore = () => {
  if (Platform.OS === 'web') {
    return webOfflineStore;
  }
  return offlineDb;
};
