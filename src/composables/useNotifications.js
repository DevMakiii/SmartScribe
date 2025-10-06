import { ref, computed, onMounted, onUnmounted } from 'vue';

export function useNotifications() {
  const showNotifications = ref(false);
  const notifications = ref([]);
  const isPolling = ref(false);
  const pollingInterval = ref(null);

  // Notification types with professional styling
  const NOTIFICATION_TYPES = {
    SUCCESS: {
      icon: ['fas', 'check-circle'],
      color: 'text-green-400',
      bgColor: 'bg-green-500'
    },
    ERROR: {
      icon: ['fas', 'triangle-exclamation'],
      color: 'text-red-400',
      bgColor: 'bg-red-500'
    },
    WARNING: {
      icon: ['fas', 'exclamation-circle'],
      color: 'text-yellow-400',
      bgColor: 'bg-yellow-500'
    },
    INFO: {
      icon: ['fas', 'info-circle'],
      color: 'text-blue-400',
      bgColor: 'bg-blue-500'
    },
    NOTE_CREATED: {
      icon: ['fas', 'file-alt'],
      color: 'text-purple-400',
      bgColor: 'bg-purple-500'
    },
    NOTE_UPDATED: {
      icon: ['fas', 'edit'],
      color: 'text-indigo-400',
      bgColor: 'bg-indigo-500'
    },
    GOAL_ACHIEVED: {
      icon: ['fas', 'trophy'],
      color: 'text-yellow-500',
      bgColor: 'bg-yellow-600'
    },
    SYSTEM: {
      icon: ['fas', 'cog'],
      color: 'text-gray-400',
      bgColor: 'bg-gray-500'
    }
  };

  const unreadNotifications = computed(() => {
    return notifications.value.filter(notification => !notification.read).length;
  });

  // Load notifications from localStorage
  const loadNotifications = () => {
    try {
      const saved = localStorage.getItem('smartscribe_notifications');
      if (saved) {
        const parsed = JSON.parse(saved);
        notifications.value = parsed.map(notification => ({
          ...notification,
          timestamp: new Date(notification.timestamp)
        }));
      } else {
        // Add welcome notification for new users
        addNotification({
          type: 'INFO',
          title: 'Welcome to SmartScribe!',
          message: 'Start by scanning your first note or creating a new one.',
          actions: [
            {
              label: 'Get Started',
              action: 'navigate',
              data: '/dashboard'
            }
          ]
        });
      }
    } catch (error) {
      console.error('Error loading notifications:', error);
    }
  };

  // Save notifications to localStorage
  const saveNotifications = () => {
    try {
      localStorage.setItem('smartscribe_notifications', JSON.stringify(notifications.value));
    } catch (error) {
      console.error('Error saving notifications:', error);
    }
  };


  // Format time ago
  const formatTimeAgo = (timestamp) => {
    const now = new Date();
    const diff = now - timestamp;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return timestamp.toLocaleDateString();
  };

  // Add new notification
  const addNotification = (notificationData) => {
    const type = NOTIFICATION_TYPES[notificationData.type] || NOTIFICATION_TYPES.INFO;

    const newNotification = {
      id: Date.now() + Math.random(),
      timestamp: new Date(),
      read: false,
      priority: notificationData.priority || 'normal', // low, normal, high, urgent
      category: notificationData.category || 'general',
      actions: notificationData.actions || [],
      persistent: notificationData.persistent || false,
      ...type,
      ...notificationData
    };

    // Add time display
    newNotification.time = formatTimeAgo(newNotification.timestamp);

    // Play sound for new notifications (disabled - sound files not available)
    // if (!notificationData.silent) {
    //   playSound(type.sound);
    // }

    // Add to beginning of array
    notifications.value.unshift(newNotification);

    // Limit to 50 notifications
    if (notifications.value.length > 50) {
      notifications.value = notifications.value.slice(0, 50);
    }

    // Save to localStorage
    saveNotifications();

    // Auto-mark as read after 30 seconds for low priority
    if (newNotification.priority === 'low') {
      setTimeout(() => {
        markAsRead(newNotification);
      }, 30000);
    }

    return newNotification;
  };

  // Professional notification methods
  const showSuccess = (title, message, options = {}) => {
    return addNotification({
      type: 'SUCCESS',
      title,
      message,
      ...options
    });
  };

  const showError = (title, message, options = {}) => {
    return addNotification({
      type: 'ERROR',
      title,
      message,
      priority: 'high',
      ...options
    });
  };

  const showWarning = (title, message, options = {}) => {
    return addNotification({
      type: 'WARNING',
      title,
      message,
      ...options
    });
  };

  const showInfo = (title, message, options = {}) => {
    return addNotification({
      type: 'INFO',
      title,
      message,
      ...options
    });
  };

  // App-specific notifications
  const notifyNoteCreated = (noteTitle) => {
    return addNotification({
      type: 'NOTE_CREATED',
      title: 'Note Created',
      message: `"${noteTitle}" has been created successfully.`,
      actions: [
        {
          label: 'View Note',
          action: 'navigate',
          data: `/notes/${noteTitle}`
        }
      ]
    });
  };

  const notifyNoteUpdated = (noteTitle) => {
    return addNotification({
      type: 'NOTE_UPDATED',
      title: 'Note Updated',
      message: `"${noteTitle}" has been updated.`,
      priority: 'low'
    });
  };

  const notifyGoalAchieved = (goalTitle) => {
    return addNotification({
      type: 'GOAL_ACHIEVED',
      title: 'Goal Achieved! 🎉',
      message: `Congratulations! You've completed "${goalTitle}".`,
      persistent: true,
      actions: [
        {
          label: 'Set New Goal',
          action: 'navigate',
          data: '/goals'
        }
      ]
    });
  };

  const notifySystemUpdate = (message) => {
    return addNotification({
      type: 'SYSTEM',
      title: 'System Update',
      message,
      priority: 'low'
    });
  };

  // Real-time polling for new notifications
  const startPolling = () => {
    if (isPolling.value) return;

    isPolling.value = true;
    pollingInterval.value = setInterval(async () => {
      try {
        // For demo purposes, we'll simulate occasional notifications
        // In a real app, this would connect to your backend API
        const shouldAddNotification = Math.random() < 0.1; // 10% chance every 30 seconds

        if (shouldAddNotification && notifications.value.length < 10) {
          const demoNotifications = [
            {
              type: 'INFO',
              title: 'System Status',
              message: 'All systems are running smoothly.',
              priority: 'low'
            },
            {
              type: 'SUCCESS',
              title: 'Backup Complete',
              message: 'Your data has been backed up successfully.',
              priority: 'low'
            },
            {
              type: 'WARNING',
              title: 'Storage Update',
              message: `You have used ${Math.floor(Math.random() * 20 + 60)}% of your storage.`,
              priority: 'normal',
              actions: [
                {
                  label: 'Manage Storage',
                  action: 'navigate',
                  data: '/settings'
                }
              ]
            }
          ];

          const randomNotification = demoNotifications[Math.floor(Math.random() * demoNotifications.length)];

          addNotification({
            ...randomNotification,
            silent: true // Don't play sound for polled notifications
          });
        }
      } catch (error) {
        // Silently fail polling - don't spam console
        console.debug('Notification polling failed:', error.message);
      }
    }, 30000); // Poll every 30 seconds
  };

  const stopPolling = () => {
    if (pollingInterval.value) {
      clearInterval(pollingInterval.value);
      pollingInterval.value = false;
    }
    isPolling.value = false;
  };

  // Notification management
  const toggleNotifications = () => {
    showNotifications.value = !showNotifications.value;
  };

  const closeNotifications = () => {
    showNotifications.value = false;
  };

  const markAsRead = (notification) => {
    notification.read = true;
    saveNotifications();
  };

  const markAllAsRead = () => {
    notifications.value.forEach(notification => {
      notification.read = true;
    });
    saveNotifications();
  };

  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id);
    if (index > -1) {
      notifications.value.splice(index, 1);
      saveNotifications();
    }
  };

  const clearAllNotifications = () => {
    notifications.value = [];
    saveNotifications();
  };

  // Execute notification action
  const executeAction = (notification, action) => {
    switch (action.action) {
      case 'navigate':
        // Use window.location for navigation since we don't have direct router access
        if (action.data.startsWith('/')) {
          window.location.href = action.data;
        } else {
          window.open(action.data, '_blank');
        }
        break;
      case 'dismiss':
        removeNotification(notification.id);
        break;
      case 'callback':
        if (typeof action.callback === 'function') {
          action.callback(notification);
        }
        break;
      case 'markAsRead':
        markAsRead(notification);
        break;
    }
  };

  // Update notification times periodically
  const updateTimes = () => {
    notifications.value.forEach(notification => {
      notification.time = formatTimeAgo(notification.timestamp);
    });
  };

  // Initialize on mount
  onMounted(() => {
    loadNotifications();
    startPolling();
    updateTimes();

    // Update times every minute
    const timeInterval = setInterval(updateTimes, 60000);

    // Cleanup on unmount
    onUnmounted(() => {
      stopPolling();
      clearInterval(timeInterval);
    });
  });

  return {
    // State
    showNotifications,
    notifications,
    unreadNotifications,

    // Basic methods
    toggleNotifications,
    closeNotifications,
    markAsRead,
    markAllAsRead,
    removeNotification,
    clearAllNotifications,

    // Professional notification methods
    addNotification,
    showSuccess,
    showError,
    showWarning,
    showInfo,

    // App-specific notifications
    notifyNoteCreated,
    notifyNoteUpdated,
    notifyGoalAchieved,
    notifySystemUpdate,

    // Actions
    executeAction,

    // Real-time control
    startPolling,
    stopPolling
  };
}