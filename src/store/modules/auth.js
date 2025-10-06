import api from '@/services/api';

const state = {
  user: (() => {
    const userData = localStorage.getItem('user');
    if (userData && userData !== 'undefined' && userData !== 'null') {
      try {
        const user = JSON.parse(userData);
        return user || null;
      } catch (e) {
        console.warn('Failed to parse user data from localStorage:', e);
        return null;
      }
    }
    return null;
  })(),
  token: localStorage.getItem('token') || null,
  isAuthenticated: !!localStorage.getItem('token'),
  userType: (() => {
    const userData = localStorage.getItem('user');
    if (userData && userData !== 'undefined' && userData !== 'null') {
      try {
        const user = JSON.parse(userData);
        return user && user.google_id ? 'oauth' : user ? 'traditional' : null;
      } catch (e) {
        return null;
      }
    }
    return null;
  })()
};

const getters = {
  getUser: state => state.user,
  isAuthenticated: state => state.isAuthenticated,
  getUserType: state => state.userType
};

const actions = {
  async login({ commit }, credentials) {
    try {
      console.log('🔐 Auth store login called with:', {
        email: credentials.email,
        passwordLength: credentials.password ? credentials.password.length : 0,
        hasEmail: !!credentials.email,
        hasPassword: !!credentials.password
      });

      const response = await api.login(credentials);

      if (!response.data || !response.data.success) {
        const errorMessage = response.data?.message || 'Login failed. Please check your credentials.';
        console.error('❌ Backend login error:', errorMessage);

        // Check for specific error types
        if (errorMessage.includes('Invalid credentials')) {
          console.error('🔍 Diagnosis: Invalid credentials - user may not exist or password is wrong');
        }

        throw new Error(errorMessage);
      }

      console.log('🔐 Backend login successful:', response.data);

      // Extract user data from backend response
      const user = response.data.data.user;
      const token = response.data.data.token;

      console.log('🔐 Extracted user:', user);
      console.log('🔐 Extracted token:', token ? '[PRESENT]' : '[MISSING]');

      // Save to localStorage (ensure no undefined values)
      if (token) {
        localStorage.setItem('token', token);
        console.log('🔐 Token saved to localStorage');
      }
      if (user) {
        localStorage.setItem('user', JSON.stringify(user));
        console.log('🔐 User data saved to localStorage');
      }

      // Update state
      commit('SET_AUTH', { user, token });
      console.log('🔐 Auth state updated in Vuex');
      return user;
    } catch (error) {
      console.error('❌ Login error:', error);
      throw error;
    }
  },

  async googleLogin({ commit }, accessToken) {
    try {
      console.log('🔐 Attempting Google login with backend API');

      const response = await api.googleLogin(accessToken);

      if (!response.data || !response.data.success) {
        const errorMessage = response.data?.message || 'Google login failed. Please try again.';
        console.error('❌ Backend Google login error:', errorMessage);
        throw new Error(errorMessage);
      }

      console.log('🔐 Backend Google login successful:', response.data);

      // Extract user data from backend response
      const user = response.data.data.user;
      const token = response.data.data.token;

      console.log('🔐 Extracted user:', user);
      console.log('🔐 Extracted token:', token ? '[PRESENT]' : '[MISSING]');

      // Save to localStorage
      if (token) {
        localStorage.setItem('token', token);
        console.log('🔐 Token saved to localStorage');
      }
      if (user) {
        localStorage.setItem('user', JSON.stringify(user));
        console.log('🔐 User data saved to localStorage');
      }

      // Update state
      commit('SET_AUTH', { user, token });
      console.log('🔐 Auth state updated in Vuex');
      return user;
    } catch (error) {
      console.error('❌ Google login error:', error);
      throw error;
    }
  },

  async facebookLogin() {
    throw new Error('Facebook login is not currently supported. Please use email/password or Google login.');
  },
  
  async register({ commit }, userData) {
    try {
      console.log('🔐 Attempting registration with backend API:', { email: userData.email, name: userData.name });

      const response = await api.register(userData);

      if (!response.data || !response.data.success) {
        const errorMessage = response.data?.message || 'Registration failed. Please try again.';
        console.error('❌ Backend registration error:', errorMessage);
        throw new Error(errorMessage);
      }

      console.log('🔐 Backend registration successful:', response.data);

      // Extract user data from backend response
      const user = response.data.data.user;
      const token = response.data.data.token;

      console.log('🔐 Extracted user:', user);
      console.log('🔐 Extracted token:', token ? '[PRESENT]' : '[MISSING]');

      // Save to localStorage (ensure no undefined values)
      if (token) localStorage.setItem('token', token);
      if (user) localStorage.setItem('user', JSON.stringify(user));

      // Update state
      commit('SET_AUTH', { user, token });
      console.log('🔐 Auth state updated in Vuex');
      return user;
    } catch (error) {
      console.error('❌ Registration error:', error);
      throw error;
    }
  },
  
  async logout({ commit }) {
    try {
      console.log('🔐 Attempting logout with backend API');
      const response = await api.logout();

      if (response.data && response.data.success) {
        console.log('🔐 Backend logout successful');
      } else {
        console.log('🔐 Backend logout completed (response may not indicate success)');
      }
    } catch (error) {
      console.error('❌ Backend logout error:', error);
      // Don't throw error for logout, just log it
    } finally {
      // Clear localStorage
      localStorage.removeItem('token');
      localStorage.removeItem('user');

      // Update state
      commit('CLEAR_AUTH');
      console.log('🔐 Auth state cleared');
    }
  },
  
  async fetchUser({ commit }) {
    try {
      console.log('🔐 Fetching current user from backend API');

      // Check if we have a token in localStorage
      const token = localStorage.getItem('token');
      if (!token) {
        console.log('🔐 No token found in localStorage');
        return null;
      }

      const response = await api.getUser();

      if (!response.data || !response.data.success) {
        console.error('❌ Backend fetch user error:', response.data?.message);
        throw new Error(response.data?.message || 'Failed to fetch user data.');
      }

      console.log('🔐 Backend user fetched:', response.data);

      // Extract user data from backend response
      const user = response.data.data.user;

      // Update localStorage
      localStorage.setItem('user', JSON.stringify(user));

      // Update state
      commit('SET_USER', user);
      console.log('🔐 User data updated in state');
      return user;
    } catch (error) {
      console.error('❌ Fetch user error:', error);
      throw error;
    }
  },

  // Initialize auth state (no longer needed for Supabase, but kept for compatibility)
  initializeAuthListener() {
    console.log('🔐 Auth listener initialized (backend authentication)');

    // For backend authentication, we don't need a real-time listener
    // The authentication state is managed through API calls and localStorage
    // This method is kept for compatibility with existing code

    return null; // No subscription to return
  }
};

const mutations = {
  SET_AUTH(state, { user, token }) {
    state.user = user;
    state.token = token;
    state.isAuthenticated = true;
    state.userType = user && user.google_id ? 'oauth' : 'traditional';
  },

  SET_USER(state, user) {
    state.user = user;
    state.userType = user.google_id ? 'oauth' : 'traditional';
  },

  CLEAR_AUTH(state) {
    state.user = null;
    state.token = null;
    state.isAuthenticated = false;
    state.userType = null;
  }
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations
};