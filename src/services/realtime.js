// src/services/realtime.js
import api from './api.js';

class RealtimeService {
  constructor() {
    this.subscribers = new Map();
    this.intervals = new Map();
    this.isConnected = false;
    this.lastUpdate = null;
  }

  // Subscribe to real-time updates for a specific data type
  subscribe(dataType, callback, interval = 10000) {
    if (!this.subscribers.has(dataType)) {
      this.subscribers.set(dataType, new Set());
    }

    this.subscribers.get(dataType).add(callback);

    // Start polling if not already started
    if (!this.intervals.has(dataType)) {
      this.startPolling(dataType, interval);
    }

    // Return unsubscribe function
    return () => this.unsubscribe(dataType, callback);
  }

  // Unsubscribe from real-time updates
  unsubscribe(dataType, callback) {
    if (this.subscribers.has(dataType)) {
      this.subscribers.get(dataType).delete(callback);

      // Stop polling if no subscribers left
      if (this.subscribers.get(dataType).size === 0) {
        this.stopPolling(dataType);
      }
    }
  }

  // Start polling for a data type
  startPolling(dataType, interval) {
    this.stopPolling(dataType); // Ensure no duplicate intervals

    const pollFunction = async () => {
      try {
        await this.fetchAndNotify(dataType);
      } catch (error) {
        console.error(`Error polling ${dataType}:`, error);
      }
    };

    // Initial fetch
    pollFunction();

    // Set up interval
    const intervalId = setInterval(pollFunction, interval);
    this.intervals.set(dataType, intervalId);
  }

  // Stop polling for a data type
  stopPolling(dataType) {
    if (this.intervals.has(dataType)) {
      clearInterval(this.intervals.get(dataType));
      this.intervals.delete(dataType);
    }
  }

  // Fetch data and notify subscribers
  async fetchAndNotify(dataType) {
    try {
      let data;

      switch (dataType) {
        case 'notes':
          data = await api.getNotes();
          break;
        case 'dashboard':
          data = await api.getDashboardStats();
          break;
        case 'progress':
          data = await api.getProgressStats();
          break;
        case 'user':
          data = await api.getUser();
          break;
        default:
          throw new Error(`Unknown data type: ${dataType}`);
      }

      // Update connection status
      this.isConnected = true;
      this.lastUpdate = new Date();

      // Notify all subscribers
      if (this.subscribers.has(dataType)) {
        this.subscribers.get(dataType).forEach(callback => {
          try {
            callback(data, null);
          } catch (error) {
            console.error(`Error in ${dataType} subscriber callback:`, error);
          }
        });
      }
    } catch (error) {
      // Update connection status
      this.isConnected = false;

      // Notify subscribers of error
      if (this.subscribers.has(dataType)) {
        this.subscribers.get(dataType).forEach(callback => {
          try {
            callback(null, error);
          } catch (callbackError) {
            console.error(`Error in ${dataType} error callback:`, callbackError);
          }
        });
      }
    }
  }

  // Manual trigger update
  async triggerUpdate(dataType) {
    await this.fetchAndNotify(dataType);
  }

  // Get connection status
  getStatus() {
    return {
      isConnected: this.isConnected,
      lastUpdate: this.lastUpdate,
      activeSubscriptions: Array.from(this.subscribers.keys()),
      subscriberCounts: Object.fromEntries(
        Array.from(this.subscribers.entries()).map(([key, set]) => [key, set.size])
      )
    };
  }

  // Cleanup all subscriptions and intervals
  cleanup() {
    this.subscribers.clear();
    this.intervals.forEach(intervalId => clearInterval(intervalId));
    this.intervals.clear();
  }
}

// Create singleton instance
const realtimeService = new RealtimeService();

export default realtimeService;