// src/services/api.js
import axios from 'axios'
import { isTokenExpired } from '../utils/authUtils'

// Log API configuration on module load
console.log('🔧 API Configuration:')
console.log('  - NODE_ENV:', process.env.NODE_ENV)
console.log('  - VUE_APP_API_BASE_URL:', process.env.VUE_APP_API_BASE_URL || 'NOT SET')
console.log('  - Computed baseURL:', process.env.NODE_ENV === 'production' ? (process.env.VUE_APP_API_BASE_URL || '/') : '/api/')
console.log('  - All VUE_APP_* env vars:', Object.keys(process.env).filter(key => key.startsWith('VUE_APP_')).reduce((acc, key) => ({ ...acc, [key]: process.env[key] }), {}))
console.log('  - Current location:', window.location.href)
console.log('  - Current origin:', window.location.origin)

const api = axios.create({
  baseURL: process.env.NODE_ENV === 'production' ? (process.env.VUE_APP_API_BASE_URL || '/') : 'http://localhost:8000/',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json'
  }
})

// Add auth token to requests
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token && !isTokenExpired(token)) {
    config.headers.Authorization = `Bearer ${token}`
  }

  // Add user ID from localStorage for authentication
  const userData = localStorage.getItem('user')
  if (userData) {
    try {
      const user = JSON.parse(userData)
      if (user && user.id) {
        config.headers['X-User-ID'] = user.id
      }
    } catch (error) {
      // Error parsing user data
    }
  }

  // Don't set Content-Type for FormData - let axios handle it
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  } else if (config.data && typeof config.data === 'object') {
    // Ensure JSON requests have proper Content-Type
    config.headers['Content-Type'] = 'application/json'
  }

  // Debug logging
  console.log('🔄 API Request Details:')
  console.log('  - Environment:', process.env.NODE_ENV)
  console.log('  - Base URL:', config.baseURL)
  console.log('  - Request URL:', config.url)
  console.log('  - Full URL:', config.baseURL + config.url)
  console.log('  - Method:', config.method)
  console.log('  - Headers:', config.headers)
  console.log('  - VUE_APP_API_BASE_URL:', process.env.VUE_APP_API_BASE_URL || 'NOT SET')
  console.log('  - Current page URL:', window.location.href)
  console.log('  - Request timestamp:', new Date().toISOString())

  return config
})

// Add response interceptor
api.interceptors.response.use(
  response => {
    console.log('✅ API Response:', response.config.method?.toUpperCase(), response.config.url, '- Status:', response.status);
    return response
  },
  error => {
    console.error('❌ API Error:', error.config?.method?.toUpperCase(), error.config?.url);

    if (error.response) {
      // Server responded with error status
      console.error('❌ Response status:', error.response.status);
      console.error('❌ Response data:', error.response.data);
    } else if (error.request) {
      // Request was made but no response received
      console.error('❌ No response received:', error.request);
    } else {
      // Something else happened
      console.error('❌ Request setup error:', error.message);
    }

    return Promise.reject(error)
  }
)

export default {
  // Test method to check API connectivity
  testConnection() {
    console.log('🧪 Testing API connection...');
    console.log('  - Current environment:', process.env.NODE_ENV);
    console.log('  - Base URL being used:', api.defaults?.baseURL || 'undefined');
    console.log('  - Testing with simple request to /auth?action=profile');

    return api.get('/auth?action=profile')
      .then(response => {
        console.log('✅ API connection test successful:', response);
        return response;
      })
      .catch(error => {
        console.error('❌ API connection test failed:', error);
        console.error('  - Error status:', error.response?.status);
        console.error('  - Error data:', error.response?.data);
        console.error('  - Full error:', error);
        return error;
      });
  },

  // Test different API endpoints to identify the issue
  testEndpoints() {
    console.log('🧪 Testing different API endpoints...');

    const endpoints = [
      '/auth?action=profile',
      '/notes',
      '/dashboard?action=stats'
    ];

    const testPromises = endpoints.map((endpoint, index) => {
      console.log(`  - Testing endpoint ${index + 1}: ${endpoint}`);
      return api.get(endpoint)
        .then(response => {
          console.log(`  ✅ Endpoint ${endpoint} successful:`, response.status);
          return { endpoint, success: true, status: response.status };
        })
        .catch(error => {
          console.log(`  ❌ Endpoint ${endpoint} failed:`, error.response?.status || 'Network Error');
          return { endpoint, success: false, status: error.response?.status, error: error.message };
        });
    });

    return Promise.all(testPromises);
  },
  // Auth
  login(credentials) {
    return api.post('/auth?action=login', credentials)
  },
  googleLogin(accessToken) {
    return api.post('/auth?action=google', { access_token: accessToken })
  },
  requestPasswordReset(email) {
    return api.post('/auth?action=requestPasswordReset', { email })
  },
  resetPassword(token, newPassword) {
    return api.post('/auth?action=resetPassword', { token, password: newPassword })
  },
  validateResetToken(token) {
    return api.get(`/auth?action=validateResetToken&token=${encodeURIComponent(token)}`)
  },
  register(userData) {
    console.log('🔄 API Register: Starting registration request');
    console.log('🔄 API Register: User data:', userData);

    // Enhanced debug logging to identify the 404 issue
    console.log('🔄 API Register: Debug - this object:', this);
    console.log('🔄 API Register: Debug - this.defaults:', this.defaults);
    console.log('🔄 API Register: Debug - api object:', api);
    console.log('🔄 API Register: Debug - api.defaults:', api.defaults);
    console.log('🔄 API Register: Debug - api.defaults.baseURL:', api.defaults?.baseURL);

    // Use the correct reference to avoid undefined error
    const baseURL = api.defaults?.baseURL || 'undefined';
    console.log('🔄 API Register: Full URL will be:', baseURL + '/auth?action=register');

    // Fix the undefined baseURL issue by using the correct reference
    if (!api.defaults?.baseURL) {
      console.error('❌ API Register: CRITICAL - api.defaults.baseURL is undefined!');
      console.error('❌ API Register: This suggests the axios instance is not properly configured');
      throw new Error('API configuration error: baseURL is undefined');
    }

    // Test the exact URL that will be called
    const testUrl = baseURL + '/auth?action=register';
    console.log('🔄 API Register: Testing URL format:', testUrl);
    console.log('🔄 API Register: Current window location:', window.location.href);
    console.log('🔄 API Register: Current NODE_ENV:', process.env.NODE_ENV);

    // Try both URL formats to see which one works
    console.log('🔄 API Register: Attempting request to:', '/auth?action=register');
    console.log('🔄 API Register: This should resolve to:', testUrl);

    const request = api.post('/auth?action=register', userData);

    request.then(response => {
      console.log('✅ API Register: Success response:', response);
      console.log('✅ API Register: Success status:', response.status);
      console.log('✅ API Register: Success data:', response.data);
    }).catch(error => {
      console.error('❌ API Register: Error response:', error);
      console.error('❌ API Register: Error config:', error.config);
      console.error('❌ API Register: Error response data:', error.response?.data);
      console.error('❌ API Register: Error response status:', error.response?.status);
      console.error('❌ API Register: Error request details:', error.request);
      console.error('❌ API Register: Full error object:', error);

      // Additional debugging for 404 errors
      if (error.response?.status === 404) {
        console.error('🚨 API Register: 404 ERROR DETECTED!');
        console.error('🚨 API Register: This means the endpoint was not found');
        console.error('🚨 API Register: Expected URL format: /api/?resource=auth&action=register');
        console.error('🚨 API Register: Actual URL attempted:', error.config?.url);
        console.error('🚨 API Register: Base URL used:', error.config?.baseURL);
        console.error('🚨 API Register: Full URL attempted:', error.config?.baseURL + error.config?.url);
      }
    });

    return request;
  },
  logout() {
    return api.post('/auth?action=logout')
  },
  updatePassword(passwordData) {
    return api.put('/auth?action=updatePassword', passwordData)
  },
  getUser() {
    return api.get('/auth?action=profile')
  },
  updateProfile(profileData) {
    return api.put('/auth?action=updateProfile', profileData)
  },
  uploadProfilePicture(formData) {
    return api.post('/auth?action=uploadProfilePicture', formData)
  },
  deleteAccount() {
    return api.delete('/auth?action=deleteAccount')
  },
  
  // Notes
  getNotes() {
    // Add cache-busting parameter to prevent browser caching
    const cacheBust = Date.now();
    return api.get(`/notes?_t=${cacheBust}`, {
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    })
  },
  getNote(id) {
    return api.get(`/notes?id=${id}`)
  },
  createNote(noteData) {
    // If there's an image file, we need to send FormData
    if (noteData.image && noteData.image instanceof File) {
      const formData = new FormData()
      formData.append('title', noteData.title)
      formData.append('text', noteData.text)
      formData.append('image', noteData.image)

      // Don't manually set Content-Type for FormData - let axios handle it
      return api.post('/notes', formData)
    } else {
      // For text-only notes, send as JSON
      return api.post('/notes', {
        title: noteData.title,
        text: noteData.text
      })
    }
  },
  updateNote(id, noteData) {
    // Convert JSON data to FormData to match backend expectations
    const formData = new FormData()
    if (noteData.title !== undefined) {
      formData.append('title', noteData.title)
    }
    if (noteData.text !== undefined) {
      formData.append('text', noteData.text)
    }
    if (noteData.is_favorite !== undefined) {
      const favoriteValue = noteData.is_favorite ? '1' : '0';
      console.log('🔄 API: Setting is_favorite to:', favoriteValue, '(from boolean:', noteData.is_favorite, ')');
      formData.append('is_favorite', favoriteValue);
    }
    if (noteData.summary) {
      formData.append('summary', noteData.summary)
    }
    if (noteData.keywords) {
      formData.append('keywords', noteData.keywords)
    }

    // Add cache-busting headers
    return api.post(`/notes?id=${id}`, formData, {
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    })
  },
  deleteNote(id) {
    return api.delete(`/notes?id=${id}`)
  },
  
  // OCR
  ocr: {
    processImage(formData) {
      return api.post('/ocr?action=processImage', formData)
    }
  },

  // Summaries
  generateSummary(noteId, options) {
    return api.post(`/summaries?action=generate&note_id=${noteId}`, options)
  },

  // Summaries
  getSummaries() {
    return api.get('/summaries')
  },
  getSummary(id) {
    return api.get(`/summaries?id=${id}`)
  },
  createSummary(noteId, options) {
    return api.post('/summaries', { note_id: noteId, format: 'paragraph', ...options })
  },

  // Progress
  getProgressStats() {
    return api.get('/progress?action=stats')
  },
  progress: {
    startStudySession(sessionData) {
      return api.post('/progress?action=startStudySession', sessionData)
    },
    endStudySession(sessionData) {
      return api.post('/progress?action=endStudySession', sessionData)
    },
    getStats() {
      return api.get('/progress?action=stats')
    }
  },

  // Dashboard
  getDashboardStats() {
    return api.get('/dashboard?action=stats')
  },

  // Settings
  getSettings() {
    return api.get('/settings')
  },
  updateSettings(settings) {
    // Explicitly stringify the data to ensure proper JSON format
    return api.put('/settings', settings)
  },

  // GPT AI Services
  gpt: {
    generateSummary(text, options = { length: 'medium' }) {
      return api.post('/gpt?action=generateSummary', { text, ...options })
    },
    generateQuiz(text, options = { difficulty: 'medium', questionCount: 5 }) {
      return api.post('/gpt?action=generateQuiz', { text, ...options })
    },
    extractKeywords(text, count = 5) {
      return api.post('/gpt?action=extractKeywords', { text, count })
    }
  },

  // Quizzes
  getQuizzes() {
    return api.get('/quizzes')
  },
  getQuiz(id) {
    console.log('🔄 API getQuiz: Starting with quizId:', id)
    const result = api.get(`/quizzes?id=${id}`)
    console.log('🔄 API getQuiz: Request promise created')
    return result
  },
  createQuiz(noteId, options) {
    console.log('🔄 API createQuiz: Starting with noteId:', noteId)
    console.log('🔄 API createQuiz: Options:', options)
    const requestData = { note_id: noteId, ...options }
    console.log('🔄 API createQuiz: Full request data:', requestData)
    const result = api.post('/quizzes', requestData)
    console.log('🔄 API createQuiz: Request promise created')
    return result
  },
  updateQuiz(id, data) {
    return api.put(`/quizzes?id=${id}`, data)
  },
  deleteQuiz(id) {
    return api.delete(`/quizzes?id=${id}`)
  },
  generateQuiz(noteId, options) {
    return api.post(`/quizzes?action=generate&note_id=${noteId}`, options)
  },
  saveQuiz(quizData) {
    return api.post('/quizzes', quizData)
  },

  // Export
  exportNote(noteId, format) {
    return api.get(`/export?id=${noteId}&format=${format}`, {
      responseType: 'blob' // Important for file downloads
    })
  },

  // Study Sessions
  startStudySession(sessionData) {
    return api.post('/study-sessions?action=start', sessionData)
  },
  endStudySession(sessionData) {
    return api.post('/study-sessions?action=end', sessionData)
  },
  updateStudySessionActivity(sessionData) {
    return api.post('/study-sessions?action=update-activity', sessionData)
  },
  getActiveStudySession() {
    return api.get('/study-sessions?action=active')
  },
  getStudySessionStats(startDate = null, endDate = null) {
    let url = '/study-sessions?action=stats'
    if (startDate) url += `&start_date=${startDate}`
    if (endDate) url += `&end_date=${endDate}`
    return api.get(url)
  },
  getDailyStudyStats(startDate, endDate) {
    return api.get(`/study-sessions?action=daily-stats&start_date=${startDate}&end_date=${endDate}`)
  },
  getStudyStreak() {
    return api.get('/study-sessions?action=streak')
  },

  // Generic methods for resources that don't have specific methods yet
  get(url, config = {}) {
    return api.get(url, config)
  },
  post(url, data = {}, config = {}) {
    return api.post(url, data, config)
  },
  put(url, data = {}, config = {}) {
    return api.put(url, data, config)
  },
  delete(url, config = {}) {
    return api.delete(url, config)
  }
}