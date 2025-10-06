// src/composables/useRealtime.js
import { ref, onMounted, onUnmounted, computed, readonly } from 'vue';
import { useStore } from 'vuex';
import realtimeService from '@/services/realtime.js';

export function useRealtime(dataType, options = {}) {
  const store = useStore();
  const {
    interval = 10000,
    immediate = true,
    enableOptimisticUpdates = true
  } = options;

  const data = ref(null);
  const error = ref(null);
  const loading = ref(false);
  const lastUpdate = ref(null);

  // Computed properties from store
  const isConnected = computed(() => store.getters['app/isConnected']);
  const connectionStatus = computed(() => store.getters['app/getConnectionStatus']);
  const hasError = computed(() => store.getters['app/hasError'](dataType));

  let unsubscribe = null;

  // Data update callback
  const handleDataUpdate = (newData, updateError) => {
    loading.value = false;

    if (updateError) {
      error.value = updateError;
      store.dispatch('app/setError', { key: dataType, error: updateError });

      // Show user-friendly error notification
      if (updateError.message && updateError.message.includes('network')) {
        store.dispatch('notifications/showWarning', {
          title: 'Connection Error',
          message: 'Unable to connect to the server. Please check your internet connection.',
          priority: 'high'
        });
      } else if (updateError.message && updateError.message.includes('timeout')) {
        store.dispatch('notifications/showWarning', {
          title: 'Request Timeout',
          message: 'The request took too long to complete. Please try again.',
          priority: 'normal'
        });
      } else {
        store.dispatch('notifications/showWarning', {
          title: 'Data Update Error',
          message: 'Failed to update data. Please refresh the page.',
          priority: 'normal'
        });
      }
    } else {
      error.value = null;

      // Check if data actually changed for performance optimization
      const hasChanged = JSON.stringify(data.value) !== JSON.stringify(newData);

      if (hasChanged) {
        data.value = newData;
        lastUpdate.value = new Date();

        // Update cache with new data
        setCachedResponse(`${dataType}:`, newData);

        store.dispatch('app/clearError', dataType);
        store.dispatch('app/updateLastSync');

        // Show success notification for first successful load
        if (!hasLoadedOnce) {
          hasLoadedOnce = true;
          store.dispatch('notifications/showSuccess', {
            title: 'Connected',
            message: `${dataType.charAt(0).toUpperCase() + dataType.slice(1)} data loaded successfully.`,
            priority: 'low',
            silent: true
          });
        }
      }
    }
  };

  let hasLoadedOnce = false;

  // Subscribe to real-time updates
  const subscribe = () => {
    if (unsubscribe) return;

    loading.value = true;
    unsubscribe = realtimeService.subscribe(dataType, handleDataUpdate, interval);

    // Track subscription in store
    store.dispatch('app/addSubscription', {
      type: dataType,
      cleanup: unsubscribe
    });
  };

  // Unsubscribe from real-time updates
  const unsubscribeUpdates = () => {
    if (unsubscribe) {
      realtimeService.unsubscribe(dataType, handleDataUpdate);
      store.dispatch('app/removeSubscription', {
        type: dataType,
        cleanup: unsubscribe
      });
      unsubscribe = null;
    }
  };

  // Manual refresh
  const refresh = async () => {
    loading.value = true;
    try {
      // Clear cache before manual refresh
      clearCache();
      await realtimeService.triggerUpdate(dataType);
    } catch (err) {
      error.value = err;
      store.dispatch('app/setError', { key: dataType, error: err });
    } finally {
      loading.value = false;
    }
  };

  // Optimistic update
  const optimisticUpdate = (updateFunction) => {
    if (!enableOptimisticUpdates) return;

    const originalData = data.value;
    const updateId = Date.now();

    try {
      // Apply optimistic update
      data.value = updateFunction(data.value);

      // Track optimistic update
      store.dispatch('app/addOptimisticUpdate', {
        id: updateId,
        type: dataType,
        originalData,
        newData: data.value
      });

      return updateId;
    } catch (err) {
      // Revert on error
      data.value = originalData;
      throw err;
    }
  };

  // Confirm optimistic update (call after successful API response)
  const confirmOptimisticUpdate = (updateId) => {
    store.dispatch('app/removeOptimisticUpdate', updateId);
  };

  // Revert optimistic update (call after failed API response)
  const revertOptimisticUpdate = (updateId) => {
    const optimisticUpdate = store.state.app.optimisticUpdates.find(update => update.id === updateId);
    if (optimisticUpdate) {
      data.value = optimisticUpdate.originalData;
      store.dispatch('app/removeOptimisticUpdate', updateId);
    }
  };

  // Optimistic delete - immediately remove item from UI
  const optimisticDelete = (itemId, deleteFunction) => {
    if (!enableOptimisticUpdates) return deleteFunction();

    const originalData = data.value;
    const updateId = Date.now();

    try {
      // Immediately remove item from UI
      const deleteUpdateFunction = (currentData) => {
        if (Array.isArray(currentData)) {
          return currentData.filter(item => item.id !== itemId);
        }
        return currentData;
      };

      data.value = deleteUpdateFunction(data.value);

      // Track optimistic update
      store.dispatch('app/addOptimisticUpdate', {
        id: updateId,
        type: dataType,
        originalData,
        newData: data.value,
        action: 'delete',
        itemId
      });

      // Perform actual delete
      const result = deleteFunction();

      // If delete is async, handle it properly
      if (result && typeof result.then === 'function') {
        return result.then(() => {
          confirmOptimisticUpdate(updateId);
          // Broadcast the successful delete to other components
          broadcastChange('delete', { id: itemId });
          return result;
        }).catch((error) => {
          revertOptimisticUpdate(updateId);
          throw error;
        });
      } else {
        confirmOptimisticUpdate(updateId);
        // Broadcast the successful delete to other components
        broadcastChange('delete', { id: itemId });
        return result;
      }
    } catch (error) {
      revertOptimisticUpdate(updateId);
      throw error;
    }
  };

  // Optimistic create - immediately add item to UI
  const optimisticCreate = (newItem, createFunction) => {
    if (!enableOptimisticUpdates) return createFunction();

    const originalData = data.value;
    const updateId = Date.now();
    const tempId = `temp_${updateId}`;

    try {
      // Add temporary item to UI with temp ID
      const tempItem = { ...newItem, id: tempId, isOptimistic: true };
      const createUpdateFunction = (currentData) => {
        if (Array.isArray(currentData)) {
          return [tempItem, ...currentData];
        }
        return currentData;
      };

      data.value = createUpdateFunction(data.value);

      // Track optimistic update
      store.dispatch('app/addOptimisticUpdate', {
        id: updateId,
        type: dataType,
        originalData,
        newData: data.value,
        action: 'create',
        tempId,
        newItem
      });

      // Perform actual create
      const result = createFunction();

      // If create is async, handle it properly
      if (result && typeof result.then === 'function') {
        return result.then((response) => {
          // Replace temp item with real item
          const realItem = response.data?.data || response.data;
          data.value = data.value.map(item =>
            item.id === tempId ? { ...realItem, isOptimistic: false } : item
          );
          confirmOptimisticUpdate(updateId);
          // Broadcast the successful create to other components
          broadcastChange('create', realItem);
          return result;
        }).catch((error) => {
          // Remove temp item
          data.value = originalData;
          store.dispatch('app/removeOptimisticUpdate', updateId);
          throw error;
        });
      } else {
        confirmOptimisticUpdate(updateId);
        // Broadcast the successful create to other components
        broadcastChange('create', newItem);
        return result;
      }
    } catch (error) {
      data.value = originalData;
      store.dispatch('app/removeOptimisticUpdate', updateId);
      throw error;
    }
  };

  // Lifecycle hooks
  onMounted(() => {
    if (immediate) {
      subscribe();
    }

    // Start watching connection status
    const connectionWatcher = setInterval(watchConnectionStatus, 2000);

    // Start auto-refresh for critical data
    const autoRefreshTimer = startAutoRefresh();

    // Store for cleanup
    window._connectionWatcher = connectionWatcher;
    window._autoRefreshTimer = autoRefreshTimer;
  });

  onUnmounted(() => {
    unsubscribeUpdates();

    // Cleanup connection watcher
    if (window._connectionWatcher) {
      clearInterval(window._connectionWatcher);
      delete window._connectionWatcher;
    }

    // Cleanup auto-refresh timer
    if (window._autoRefreshTimer) {
      clearInterval(window._autoRefreshTimer);
      delete window._autoRefreshTimer;
    }
  });

  // Retry mechanism
  const retry = async (operation, maxRetries = 3, delay = 1000) => {
    let lastError;

    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error;

        if (attempt === maxRetries) {
          throw error;
        }

        // Exponential backoff
        await new Promise(resolve => setTimeout(resolve, delay * Math.pow(2, attempt - 1)));
      }
    }

    throw lastError;
  };

  // Enhanced refresh with retry
  const refreshWithRetry = async (maxRetries = 3) => {
    loading.value = true;
    error.value = null;

    try {
      await retry(async () => {
        const newData = await realtimeService.fetchData(dataType);
        data.value = newData;
        lastUpdate.value = new Date();
        store.dispatch('app/clearError', dataType);
        store.dispatch('app/updateLastSync');
      }, maxRetries);
    } catch (updateError) {
      error.value = updateError;
      store.dispatch('app/setError', { key: dataType, error: updateError });

      store.dispatch('notifications/showWarning', {
        title: 'Refresh Failed',
        message: `Failed to refresh ${dataType} data after ${maxRetries} attempts.`
      });
    } finally {
      loading.value = false;
    }
  };

  // Loading state management
  const setLoading = (isLoading) => {
    loading.value = isLoading;
  };

  // Error state management
  const clearError = () => {
    error.value = null;
    store.dispatch('app/clearError', dataType);
  };

  // Get error details
  const getErrorDetails = () => {
    if (!error.value) return null;

    return {
      message: error.value.message,
      code: error.value.code,
      timestamp: error.value.timestamp || new Date(),
      retryable: error.value.retryable !== false
    };
  };

  // =====================================
  // PERFORMANCE OPTIMIZATIONS
  // =====================================

  // Request deduplication - prevent duplicate requests
  const pendingRequests = new Map();
  const requestCache = new Map();

  // Cache configuration
  const CACHE_TTL = {
    'notes': 30000, // 30 seconds
    'user': 300000, // 5 minutes
    'progress': 45000, // 45 seconds - synced with dashboard
    'dashboard': 45000 // 45 seconds
  };

  // Deduplicate requests
  const deduplicateRequest = async (requestKey, requestFunction) => {
    if (pendingRequests.has(requestKey)) {
      return pendingRequests.get(requestKey);
    }

    const requestPromise = requestFunction()
      .finally(() => {
        pendingRequests.delete(requestKey);
      });

    pendingRequests.set(requestKey, requestPromise);
    return requestPromise;
  };

  // Cache responses
  const getCachedResponse = (cacheKey) => {
    const cached = requestCache.get(cacheKey);
    if (!cached) return null;

    const now = Date.now();
    const ttl = CACHE_TTL[dataType] || 60000;

    if (now - cached.timestamp > ttl) {
      requestCache.delete(cacheKey);
      return null;
    }

    return cached.data;
  };

  const setCachedResponse = (cacheKey, data) => {
    requestCache.set(cacheKey, {
      data,
      timestamp: Date.now()
    });
  };

  // Optimized fetch with caching and deduplication
  const optimizedFetch = async (endpoint = '') => {
    const cacheKey = `${dataType}:${endpoint}`;

    // Check cache first
    const cachedData = getCachedResponse(cacheKey);
    if (cachedData) {
      return cachedData;
    }

    // Deduplicate request
    return deduplicateRequest(cacheKey, async () => {
      const data = await realtimeService.fetchData(dataType, endpoint);
      setCachedResponse(cacheKey, data);
      return data;
    });
  };

  // Clear cache for specific data type
  const clearCache = (specificKey = null) => {
    if (specificKey) {
      // Clear specific cache entry
      requestCache.forEach((value, key) => {
        if (key.includes(specificKey)) {
          requestCache.delete(key);
        }
      });
    } else {
      // Clear all cache for this data type
      requestCache.forEach((value, key) => {
        if (key.startsWith(`${dataType}:`)) {
          requestCache.delete(key);
        }
      });
    }
  };

  // =====================================
  // AUTO-REFRESH SYSTEM
  // =====================================

  // Auto-refresh configuration based on data type
  const getAutoRefreshConfig = (dataType) => {
    const configs = {
      'notes': {
        interval: 30000, // 30 seconds - frequent updates for user content
        priority: 'high',
        enableSmartRefresh: true
      },
      'user': {
        interval: 300000, // 5 minutes - less frequent for user data
        priority: 'medium',
        enableSmartRefresh: false
      },
      'progress': {
        interval: 45000, // 45 seconds - synced with dashboard for consistency
        priority: 'high',
        enableSmartRefresh: true
      },
      'dashboard': {
        interval: 45000, // 45 seconds - moderate frequency for dashboard stats
        priority: 'high',
        enableSmartRefresh: true
      }
    };

    return configs[dataType] || {
      interval: 60000,
      priority: 'low',
      enableSmartRefresh: false
    };
  };

  // Smart refresh - only refresh if data has actually changed
  const smartRefresh = async () => {
    if (!data.value) return;

    try {
      const newData = await optimizedFetch();
      const hasChanged = JSON.stringify(data.value) !== JSON.stringify(newData);

      if (hasChanged) {
        data.value = newData;
        lastUpdate.value = new Date();
        store.dispatch('app/clearError', dataType);
        store.dispatch('app/updateLastSync');

        // Show subtle notification for smart refresh
        if (dataType === 'notes' || dataType === 'dashboard') {
          store.dispatch('notifications/showInfo', {
            title: 'Data Updated',
            message: `${dataType.charAt(0).toUpperCase() + dataType.slice(1)} has been updated.`,
            priority: 'low',
            silent: true
          });
        }
      }
    } catch (error) {
      // Silently fail for smart refresh to avoid spam
      console.debug(`Smart refresh failed for ${dataType}:`, error.message);
    }
  };

  // Enhanced auto-refresh with smart features
  const startAutoRefresh = () => {
    const config = getAutoRefreshConfig(dataType);

    if (config.enableSmartRefresh) {
      // Use smart refresh for critical data
      return setInterval(smartRefresh, config.interval);
    } else {
      // Use regular refresh for less critical data
      return setInterval(() => {
        refresh();
      }, config.interval);
    }
  };

  // =====================================
  // CONNECTION STATUS TRACKING
  // =====================================

  // Track connection status changes
  let lastConnectionStatus = null;

  // Watch for connection status changes and show notifications
  const watchConnectionStatus = () => {
    const currentStatus = isConnected.value;

    if (lastConnectionStatus !== null && lastConnectionStatus !== currentStatus) {
      if (currentStatus) {
        // Connection restored
        store.dispatch('notifications/showSuccess', {
          title: 'Connection Restored',
          message: 'You are now connected and receiving live updates.',
          priority: 'normal',
          silent: true
        });
      } else {
        // Connection lost
        store.dispatch('notifications/showWarning', {
          title: 'Connection Lost',
          message: 'You are currently offline. Changes will be synced when connection is restored.',
          priority: 'high',
          persistent: true
        });
      }
    }

    lastConnectionStatus = currentStatus;
  };

  // =====================================
  // DATA SYNCHRONIZATION
  // =====================================

  // Broadcast data changes to other components
  const broadcastChange = (changeType, changeData) => {
    // Store the change in the global state for cross-component sync
    store.dispatch('app/broadcastDataChange', {
      dataType,
      changeType,
      changeData,
      timestamp: new Date()
    });

    // Emit custom event for immediate component updates
    if (typeof window !== 'undefined') {
      window.dispatchEvent(new CustomEvent(`dataChange:${dataType}`, {
        detail: { changeType, changeData, timestamp: new Date() }
      }));

      // Also broadcast to related dataTypes that depend on notes
      if (dataType === 'notes' && (changeType === 'create' || changeType === 'update' || changeType === 'delete')) {
        // When notes change, notify dashboard and progress views to refresh their stats
        window.dispatchEvent(new CustomEvent('dataChange:dashboard', {
          detail: { changeType: 'refresh', changeData: null, timestamp: new Date() }
        }));
        window.dispatchEvent(new CustomEvent('dataChange:progress', {
          detail: { changeType: 'refresh', changeData: null, timestamp: new Date() }
        }));
      }
    }

    // Show real-time notifications for important data changes
    showRealtimeNotification(changeType, changeData);
  };

  // Show real-time notifications for data changes
  const showRealtimeNotification = (changeType, changeData) => {
    const notificationOptions = {
      silent: true, // Don't play sound for real-time notifications
      priority: 'low'
    };

    switch (changeType) {
      case 'create':
        if (dataType === 'notes') {
          store.dispatch('notifications/showSuccess', {
            title: 'Note Created',
            message: `A new note "${changeData.title || 'Untitled'}" has been created.`,
            ...notificationOptions
          });
        }
        break;
      case 'update':
        if (dataType === 'notes') {
          store.dispatch('notifications/showInfo', {
            title: 'Note Updated',
            message: `Note "${changeData.title || 'Untitled'}" has been updated.`,
            ...notificationOptions
          });
        }
        break;
      case 'delete':
        if (dataType === 'notes') {
          store.dispatch('notifications/showWarning', {
            title: 'Note Deleted',
            message: 'A note has been deleted.',
            ...notificationOptions
          });
        }
        break;
    }
  };

  // Listen for data changes from other components
  const listenForChanges = () => {
    if (typeof window !== 'undefined') {
      const handleDataChange = (event) => {
        const { changeType, changeData } = event.detail;

        // Update local data based on the change type
        switch (changeType) {
          case 'create':
            if (Array.isArray(data.value)) {
              data.value = [...data.value, changeData];
            }
            break;
          case 'update':
            if (Array.isArray(data.value)) {
              data.value = data.value.map(item =>
                item.id === changeData.id ? { ...item, ...changeData } : item
              );
            }
            break;
          case 'delete':
            if (Array.isArray(data.value)) {
              data.value = data.value.filter(item => item.id !== changeData.id);
            }
            break;
          case 'refresh':
            // Trigger a refresh of local data
            refresh();
            break;
        }
      };

      window.addEventListener(`dataChange:${dataType}`, handleDataChange);

      // Return cleanup function
      return () => {
        window.removeEventListener(`dataChange:${dataType}`, handleDataChange);
      };
    }
  };

  return {
    // Reactive data
    data: readonly(data),
    error: readonly(error),
    loading: readonly(loading),
    lastUpdate: readonly(lastUpdate),

    // Store computed properties
    isConnected,
    connectionStatus,
    hasError,

    // Methods
    subscribe,
    unsubscribe: unsubscribeUpdates,
    refresh: refreshWithRetry,
    optimisticUpdate,
    confirmOptimisticUpdate,
    revertOptimisticUpdate,
    optimisticDelete,
    optimisticCreate,

    // Enhanced methods
    retry,
    setLoading,
    clearError,
    getErrorDetails,

    // Data synchronization
    broadcastChange,
    listenForChanges,

    // Auto-refresh controls
    startAutoRefresh,
    smartRefresh,

    // Performance optimization controls
    clearCache,
    optimizedFetch,

    // Real-time service status
    getStatus: () => realtimeService.getStatus()
  };
}

// Helper composable for connection status
export function useConnectionStatus() {
  const store = useStore();

  return {
    isConnected: computed(() => store.getters['app/isConnected']),
    connectionStatus: computed(() => store.getters['app/getConnectionStatus']),
    lastSync: computed(() => store.getters['app/getLastSync']),
    status: computed(() => ({
      isConnected: store.getters['app/isConnected'],
      connectionStatus: store.getters['app/getConnectionStatus'],
      lastSync: store.getters['app/getLastSync']
    }))
  };
}

// Helper composable for global loading states
export function useLoading(key) {
  const store = useStore();

  return {
    loading: computed(() => store.getters['app/isLoading'](key)),
    setLoading: (loading) => store.dispatch('app/setLoading', { key, loading }),
    error: computed(() => store.getters['app/getError'](key)),
    hasError: computed(() => store.getters['app/hasError'](key)),
    setError: (error) => store.dispatch('app/setError', { key, error }),
    clearError: () => store.dispatch('app/clearError', key)
  };
}