import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';
import { apiClient } from '../api';
import { useAppStore } from '../stores/appStore';
import { QueuedOperation } from '../types';

const QUEUE_KEY = 'tixello_offline_queue';
const MAX_RETRIES = 3;

class OfflineQueueService {
  private queue: QueuedOperation[] = [];
  private isProcessing = false;
  private unsubscribe: (() => void) | null = null;

  /**
   * Initialize the offline queue service
   */
  async init() {
    // Load persisted queue
    await this.loadQueue();

    // Listen for network changes
    this.unsubscribe = NetInfo.addEventListener((state) => {
      const { setOnline, setPendingSyncCount } = useAppStore.getState();
      setOnline(state.isConnected ?? true);

      if (state.isConnected && this.queue.length > 0) {
        this.processQueue();
      }

      setPendingSyncCount(this.queue.length);
    });
  }

  /**
   * Clean up listeners
   */
  destroy() {
    if (this.unsubscribe) {
      this.unsubscribe();
    }
  }

  /**
   * Load queue from persistent storage
   */
  private async loadQueue() {
    try {
      const stored = await AsyncStorage.getItem(QUEUE_KEY);
      if (stored) {
        this.queue = JSON.parse(stored);
        useAppStore.getState().setPendingSyncCount(this.queue.length);
      }
    } catch (error) {
      console.error('Error loading offline queue:', error);
    }
  }

  /**
   * Persist queue to storage
   */
  private async persistQueue() {
    try {
      await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(this.queue));
      useAppStore.getState().setPendingSyncCount(this.queue.length);
    } catch (error) {
      console.error('Error persisting offline queue:', error);
    }
  }

  /**
   * Add an operation to the queue
   */
  async addToQueue(
    operation: Omit<QueuedOperation, 'id' | 'timestamp' | 'retries'>
  ): Promise<string> {
    const id = `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

    const item: QueuedOperation = {
      ...operation,
      id,
      timestamp: Date.now(),
      retries: 0,
    };

    this.queue.push(item);
    await this.persistQueue();

    // Try to process immediately if online
    const state = await NetInfo.fetch();
    if (state.isConnected) {
      this.processQueue();
    }

    return id;
  }

  /**
   * Process the queue
   */
  async processQueue() {
    if (this.isProcessing || this.queue.length === 0) {
      return;
    }

    this.isProcessing = true;

    while (this.queue.length > 0) {
      const operation = this.queue[0];

      try {
        await this.executeOperation(operation);

        // Success - remove from queue
        this.queue.shift();
        await this.persistQueue();

        // Notify success
        useAppStore.getState().addNotification({
          type: 'success',
          message: `Synced: ${operation.type}`,
          time: 'Just now',
          unread: false,
        });
      } catch (error) {
        console.error('Error processing queue item:', error);

        operation.retries++;

        if (operation.retries >= MAX_RETRIES) {
          // Move to failed - remove from queue
          this.queue.shift();
          await this.persistQueue();

          // Notify failure
          useAppStore.getState().addNotification({
            type: 'alert',
            message: `Failed to sync: ${operation.type}`,
            time: 'Just now',
            unread: true,
          });
        } else {
          // Update retry count
          await this.persistQueue();
        }

        // Check if still online before continuing
        const state = await NetInfo.fetch();
        if (!state.isConnected) {
          break;
        }
      }
    }

    this.isProcessing = false;
  }

  /**
   * Execute a single queued operation
   */
  private async executeOperation(operation: QueuedOperation): Promise<void> {
    const { method, endpoint, data } = operation;

    switch (method) {
      case 'POST':
        await apiClient.post(endpoint, data);
        break;
      case 'DELETE':
        await apiClient.delete(endpoint);
        break;
      default:
        throw new Error(`Unknown method: ${method}`);
    }
  }

  /**
   * Get pending items count
   */
  getPendingCount(): number {
    return this.queue.length;
  }

  /**
   * Clear the queue (use with caution)
   */
  async clearQueue() {
    this.queue = [];
    await this.persistQueue();
  }

  /**
   * Queue a check-in for offline processing
   */
  async queueCheckIn(eventId: number, barcode: string): Promise<string> {
    return this.addToQueue({
      type: 'check-in',
      endpoint: `/api/marketplace-client/organizer/events/${eventId}/check-in/${barcode}`,
      method: 'POST',
      data: {},
    });
  }

  /**
   * Queue a door sale for offline processing
   */
  async queueDoorSale(saleData: Record<string, any>): Promise<string> {
    return this.addToQueue({
      type: 'door-sale',
      endpoint: '/api/door-sales/process',
      method: 'POST',
      data: saleData,
    });
  }
}

// Singleton instance
export const offlineQueue = new OfflineQueueService();
export default offlineQueue;
