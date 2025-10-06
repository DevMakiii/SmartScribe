// src/store/modules/app.js
const state = {
  // Connection status
  isOnline: navigator.onLine,
  lastSync: null,
  connectionStatus: 'connecting',

  // Global loading states
  loadingStates: new Map(),

  // Global error states
  errors: new Map(),

  // Real-time subscriptions
  subscriptions: new Set(),

  // Optimistic updates queue
  optimisticUpdates: [],

  // Global theme settings
  theme: 'light', // 'dark', 'light', 'system'
  fontSize: 16,

  // Sidebar visibility
  sidebarVisible: false,
};

const getters = {
  isLoading: (state) => (key) => state.loadingStates.get(key) || false,
  getError: (state) => (key) => state.errors.get(key),
  hasError: (state) => (key) => state.errors.has(key),
  getConnectionStatus: (state) => state.connectionStatus,
  isConnected: (state) => state.isOnline && state.connectionStatus === 'connected',
  getLastSync: (state) => state.lastSync,

  // Theme getters
  getTheme: (state) => state.theme,
  getFontSize: (state) => state.fontSize,
  getSidebarVisible: (state) => state.sidebarVisible,
  getCurrentTheme: (state) => {
    if (state.theme === 'system' && typeof window !== 'undefined') {
      return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    return state.theme;
  },

  // Get current theme state for debugging
  getThemeState: (state) => ({
    theme: state.theme,
    fontSize: state.fontSize,
    currentTheme: state.theme === 'system' && typeof window !== 'undefined'
      ? window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
      : state.theme
  }),
  getThemeClasses: (state) => {
    const isSystemDark = typeof window !== 'undefined' && window.matchMedia
      ? window.matchMedia('(prefers-color-scheme: dark)').matches
      : false;

    const isDark = state.theme === 'dark' ||
                  (state.theme === 'system' && isSystemDark);

    if (isDark) {
      return {
        main: 'bg-gray-900 text-white',
        header: 'bg-gray-800 border-b border-gray-700 text-white',
        sidebar: 'bg-gray-800 border-r border-gray-700 text-white',
        mainContent: 'bg-gray-900',
        card: 'bg-gray-800 border border-gray-700 shadow-lg',
        text: 'text-white',
        secondaryText: 'text-gray-200',
        tertiaryText: 'text-gray-400',
        input: 'bg-gray-700 border-gray-600 text-white placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500',
        button: 'bg-gray-700 hover:bg-gray-600 text-white border border-gray-600',
        buttonPrimary: 'bg-blue-600 hover:bg-blue-700 text-white border border-blue-600',
        buttonSecondary: 'bg-gray-600 hover:bg-gray-500 text-white border border-gray-600',
        link: 'text-blue-400 hover:text-blue-300',
        border: 'border-gray-700',
        hover: 'hover:bg-gray-700',
        focus: 'focus:bg-gray-600 focus:border-blue-500'
      };
    } else {
      // True light theme with proper light colors
      return {
        main: 'bg-white text-gray-900',
        header: 'bg-white border-b border-gray-200 shadow-sm text-gray-900',
        sidebar: 'bg-gray-50 border-r border-gray-200 text-gray-900',
        mainContent: 'bg-white',
        card: 'bg-white border border-gray-200 shadow-sm hover:shadow-md transition-shadow hover:bg-gray-50',
        text: 'text-gray-900',
        secondaryText: 'text-gray-700',
        tertiaryText: 'text-gray-500',
        input: 'bg-white border-gray-300 text-gray-900 placeholder-gray-500 focus:border-blue-500 focus:ring-blue-500 shadow-sm',
        button: 'bg-gray-100 hover:bg-gray-200 text-gray-900 border border-gray-300 hover:border-gray-400',
        buttonPrimary: 'bg-blue-600 hover:bg-blue-700 text-white border border-blue-600 shadow-sm',
        buttonSecondary: 'bg-gray-600 hover:bg-gray-700 text-white border border-gray-600 shadow-sm',
        link: 'text-blue-600 hover:text-blue-700',
        border: 'border-gray-200',
        hover: 'hover:bg-gray-50',
        focus: 'focus:bg-gray-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20'
      };
    }
  },
  getFontSizeClasses: (state) => {
    const size = state.fontSize;
    console.log('🔤 getFontSizeClasses called with size:', size);

    const classes = {
      heading: size >= 20 ? 'text-2xl' : size >= 18 ? 'text-xl' : size >= 16 ? 'text-lg' : 'text-base',
      body: size >= 18 ? 'text-lg' : size >= 16 ? 'text-base' : size >= 14 ? 'text-sm' : 'text-xs',
      label: size >= 16 ? 'text-sm' : 'text-xs',
      small: size >= 14 ? 'text-xs' : 'text-xs' // Keep small consistent but could be adjusted
    };

    console.log('🔤 Computed font size classes:', classes);
    return classes;
  },
};

const mutations = {
  SET_ONLINE_STATUS(state, isOnline) {
    state.isOnline = isOnline;
    state.connectionStatus = isOnline ? 'connected' : 'offline';
  },

  SET_CONNECTION_STATUS(state, status) {
    state.connectionStatus = status;
  },

  SET_LOADING(state, { key, loading }) {
    if (loading) {
      state.loadingStates.set(key, true);
    } else {
      state.loadingStates.delete(key);
    }
  },

  SET_ERROR(state, { key, error }) {
    if (error) {
      state.errors.set(key, {
        message: error.message || error,
        timestamp: new Date(),
        details: error
      });
    } else {
      state.errors.delete(key);
    }
  },

  CLEAR_ERROR(state, key) {
    state.errors.delete(key);
  },

  CLEAR_ALL_ERRORS(state) {
    state.errors.clear();
  },

  SET_LAST_SYNC(state, timestamp) {
    state.lastSync = timestamp;
  },

  ADD_SUBSCRIPTION(state, subscription) {
    state.subscriptions.add(subscription);
  },

  REMOVE_SUBSCRIPTION(state, subscription) {
    state.subscriptions.delete(subscription);
  },

  ADD_OPTIMISTIC_UPDATE(state, update) {
    state.optimisticUpdates.push({
      ...update,
      timestamp: new Date(),
      id: Date.now()
    });
  },

  REMOVE_OPTIMISTIC_UPDATE(state, updateId) {
    state.optimisticUpdates = state.optimisticUpdates.filter(update => update.id !== updateId);
  },

  CLEAR_OPTIMISTIC_UPDATES(state) {
    state.optimisticUpdates = [];
  },

  // Theme mutations
  SET_THEME(state, theme) {
    state.theme = theme;
  },

  SET_FONT_SIZE(state, fontSize) {
    state.fontSize = fontSize;
  },

  SET_SIDEBAR_VISIBLE(state, visible) {
    state.sidebarVisible = visible;
  },
};

const actions = {
  setOnlineStatus({ commit }, isOnline) {
    commit('SET_ONLINE_STATUS', isOnline);
  },

  setConnectionStatus({ commit }, status) {
    commit('SET_CONNECTION_STATUS', status);
  },

  setLoading({ commit }, payload) {
    commit('SET_LOADING', payload);
  },

  setError({ commit }, payload) {
    commit('SET_ERROR', payload);
  },

  clearError({ commit }, key) {
    commit('CLEAR_ERROR', key);
  },

  clearAllErrors({ commit }) {
    commit('CLEAR_ALL_ERRORS');
  },

  updateLastSync({ commit }) {
    commit('SET_LAST_SYNC', new Date());
  },

  addSubscription({ commit }, subscription) {
    commit('ADD_SUBSCRIPTION', subscription);
  },

  removeSubscription({ commit }, subscription) {
    commit('REMOVE_SUBSCRIPTION', subscription);
  },

  addOptimisticUpdate({ commit }, update) {
    commit('ADD_OPTIMISTIC_UPDATE', update);
  },

  removeOptimisticUpdate({ commit }, updateId) {
    commit('REMOVE_OPTIMISTIC_UPDATE', updateId);
  },

  clearOptimisticUpdates({ commit }) {
    commit('CLEAR_OPTIMISTIC_UPDATES');
  },

  // Debug action for theme troubleshooting
  debugTheme({ state, getters }) {
    console.log('🎨 === THEME DEBUG INFO ===');
    console.log('🎨 Current theme state:', state.theme);
    console.log('🎨 Current theme (computed):', getters.getCurrentTheme);
    console.log('🎨 Font size:', state.fontSize);
    console.log('🎨 Sidebar visible:', state.sidebarVisible);
    console.log('🎨 Theme classes:', getters.getThemeClasses);
    console.log('🎨 Font size classes:', getters.getFontSizeClasses);

    // Check DOM classes
    if (typeof document !== 'undefined') {
      console.log('🎨 Document root classes:', document.documentElement.className);
      console.log('🎨 Body classes:', document.body.className);
    }

    console.log('🎨 === END THEME DEBUG ===');
    return {
      theme: state.theme,
      currentTheme: getters.getCurrentTheme,
      fontSize: state.fontSize,
      sidebarVisible: state.sidebarVisible,
      themeClasses: getters.getThemeClasses
    };
  },

  // Theme actions
  setTheme({ commit, dispatch }, theme) {
    console.log('🎨 setTheme called with:', theme);
    commit('SET_THEME', theme);
    dispatch('applyTheme', theme);
    dispatch('saveThemeSettings');
    console.log('🎨 Theme set to:', theme);
  },

  setFontSize({ commit, dispatch }, fontSize) {
    console.log('🔤 setFontSize called with:', fontSize, 'Type:', typeof fontSize);
    console.log('🔤 Previous font size:', state.fontSize);

    if (typeof fontSize !== 'number' || fontSize < 12 || fontSize > 24) {
      console.error('🔤 Invalid font size:', fontSize, 'Must be number between 12-24');
      return;
    }

    commit('SET_FONT_SIZE', fontSize);
    console.log('🔤 Font size committed to:', fontSize);

    dispatch('saveThemeSettings');
    console.log('🔤 Font size change completed');
  },

  setSidebarVisible({ commit, dispatch }, visible) {
    commit('SET_SIDEBAR_VISIBLE', visible);
    dispatch('saveThemeSettings');
  },

  toggleSidebar({ commit, dispatch, state }) {
    const newVisibility = !state.sidebarVisible;
    commit('SET_SIDEBAR_VISIBLE', newVisibility);
    dispatch('saveThemeSettings');
  },

  applyTheme(theme) {
    // Only run on client-side
    if (typeof window === 'undefined' || typeof document === 'undefined') {
      return;
    }

    const root = document.documentElement;
    const body = document.body;

    // Remove all theme classes first
    root.classList.remove('theme-dark', 'theme-light', 'theme-system');
    body.classList.remove('theme-dark', 'theme-light', 'theme-system');

    // Apply the appropriate theme class
    if (theme === 'dark') {
      root.classList.add('theme-dark');
      body.classList.add('theme-dark');
      console.log('🎨 Applied dark theme');
    } else if (theme === 'light') {
      root.classList.add('theme-light');
      body.classList.add('theme-light');
      console.log('🎨 Applied light theme');
    } else if (theme === 'system') {
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const systemTheme = prefersDark ? 'theme-dark' : 'theme-light';
      root.classList.add('theme-system', systemTheme);
      body.classList.add('theme-system', systemTheme);
      console.log('🎨 Applied system theme:', systemTheme);
    }

    // Force a repaint to ensure theme changes are visible
    document.body.style.display = 'none';
    document.body.offsetHeight; // Trigger reflow
    document.body.style.display = '';
  },

  saveThemeSettings({ state }) {
    console.log('🎨 saveThemeSettings: Saving theme settings to localStorage');
    // Save to localStorage
    const settings = {
      theme: state.theme,
      fontSize: state.fontSize,
      sidebarVisible: state.sidebarVisible
    };
    console.log('🎨 saveThemeSettings: Settings to save:', settings);
    try {
      localStorage.setItem('smartscribe_theme_settings', JSON.stringify(settings));
      console.log('🎨 saveThemeSettings: Settings saved to localStorage successfully');
      console.log('🔤 Font size saved:', state.fontSize);
    } catch (error) {
      console.error('🎨 saveThemeSettings: Error saving to localStorage:', error);
    }
  },

  loadThemeSettings({ commit, dispatch }) {
    console.log('🎨 loadThemeSettings: Loading theme settings from localStorage');
    // Load from localStorage
    const savedSettings = localStorage.getItem('smartscribe_theme_settings');
    console.log('🎨 loadThemeSettings: Raw localStorage data:', savedSettings);

    if (savedSettings) {
      try {
        const settings = JSON.parse(savedSettings);
        console.log('🎨 loadThemeSettings: Parsed settings:', settings);

        if (settings.theme) {
          console.log('🎨 loadThemeSettings: Setting theme to:', settings.theme);
          commit('SET_THEME', settings.theme);
        }
        if (settings.fontSize) {
          console.log('🎨 loadThemeSettings: Setting font size to:', settings.fontSize);
          console.log('🔤 Loading font size from localStorage:', settings.fontSize, 'Type:', typeof settings.fontSize);
          commit('SET_FONT_SIZE', settings.fontSize);
          console.log('🔤 Font size loaded and committed');
        } else {
          console.log('🔤 No font size found in saved settings, using default');
        }
        if (typeof settings.sidebarVisible === 'boolean') {
          console.log('🎨 loadThemeSettings: Setting sidebar visible to:', settings.sidebarVisible);
          commit('SET_SIDEBAR_VISIBLE', settings.sidebarVisible);
        }
        // Apply the loaded theme
        const themeToApply = settings.theme || 'light';
        console.log('🎨 loadThemeSettings: Applying theme:', themeToApply);
        dispatch('applyTheme', themeToApply);
      } catch (error) {
        console.error('🎨 loadThemeSettings: Error loading theme settings:', error);
      }
    } else {
      console.log('🎨 loadThemeSettings: No saved settings found, applying default theme');
      // Apply default theme
      dispatch('applyTheme', 'light');
    }
  },

  // Initialize app state
  initialize({ dispatch }) {
    // Set up online/offline detection
    if (typeof window !== 'undefined') {
      window.addEventListener('online', () => {
        dispatch('setOnlineStatus', true);
        dispatch('setConnectionStatus', 'connected');
      });

      window.addEventListener('offline', () => {
        dispatch('setOnlineStatus', false);
        dispatch('setConnectionStatus', 'offline');
      });

      // Initial status
      dispatch('setOnlineStatus', navigator.onLine);

      // Load and apply theme settings
      dispatch('loadThemeSettings');
    }
  },

  // Cleanup on app destroy
  cleanup({ state, dispatch }) {
    // Clean up subscriptions
    state.subscriptions.forEach(subscription => {
      if (typeof subscription.cleanup === 'function') {
        subscription.cleanup();
      }
    });

    // Clear all errors
    dispatch('clearAllErrors');

    // Clear optimistic updates
    dispatch('clearOptimisticUpdates');
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};