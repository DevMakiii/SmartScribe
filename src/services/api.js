// src/services/api.js
import axios from 'axios'
import { isTokenExpired } from '../utils/authUtils'

const api = axios.create({
  baseURL: process.env.NODE_ENV === 'production' ? '/' : '/SmartScribe/api/',
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
  console.log('API Request URL:', config.baseURL + config.url)
  console.log('API Request Method:', config.method)
  console.log('API Request Headers:', config.headers)

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
  // Auth
  login(credentials) {
    return api.post('?resource=auth&action=login', JSON.stringify(credentials), {
      headers: {
        'Content-Type': 'application/json'
      }
    })
  },
  googleLogin(accessToken) {
    return api.post('?resource=auth&action=google', JSON.stringify({ access_token: accessToken }), {
      headers: {
        'Content-Type': 'application/json'
      }
    })
  },
  requestPasswordReset(email) {
    const formData = new FormData()
    formData.append('email', email)
    return api.post('?resource=auth&action=request-password-reset', formData)
  },
  resetPassword(token, newPassword) {
    const formData = new FormData()
    formData.append('token', token)
    formData.append('password', newPassword)
    return api.post('?resource=auth&action=reset-password', formData)
  },
  validateResetToken(token) {
    return api.get(`?resource=auth&action=validate-reset-token&token=${encodeURIComponent(token)}`)
  },
  register(userData) {
    return api.post('?resource=auth&action=register', JSON.stringify(userData), {
      headers: {
        'Content-Type': 'application/json'
      }
    })
  },
  logout() {
    return api.post('?resource=auth&action=logout')
  },
  updatePassword(passwordData) {
    return api.put('?resource=auth&action=update-password', JSON.stringify(passwordData), {
      headers: {
        'Content-Type': 'application/json'
      }
    })
  },
  getUser() {
    return api.get('?resource=auth&action=profile')
  },
  updateProfile(profileData) {
    return api.put('?resource=auth&action=profile', JSON.stringify(profileData), {
      headers: {
        'Content-Type': 'application/json'
      }
    })
  },
  uploadProfilePicture(formData) {
    return api.post('?resource=auth&action=upload-profile-picture', formData)
  },
  deleteAccount() {
    return api.delete('?resource=auth&action=delete-account')
  },
  
  // Notes
  getNotes() {
    // Add cache-busting parameter to prevent browser caching
    const cacheBust = Date.now();
    return api.get(`?resource=notes&_t=${cacheBust}`, {
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    })
  },
  getNote(id) {
    return api.get(`?resource=notes&id=${id}`)
  },
  createNote(noteData) {
    // If there's an image file, we need to send FormData
    if (noteData.image && noteData.image instanceof File) {
      const formData = new FormData()
      formData.append('title', noteData.title)
      formData.append('text', noteData.text)
      formData.append('image', noteData.image)

      // Don't manually set Content-Type for FormData - let axios handle it
      return api.post('?resource=notes', formData)
    } else {
      // For text-only notes, send as JSON
      return api.post('?resource=notes', {
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
    return api.post(`?resource=notes&id=${id}`, formData, {
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache'
      }
    })
  },
  deleteNote(id) {
    return api.delete(`?resource=notes&id=${id}`)
  },
  
  // OCR
  ocr: {
    processImage(formData) {
      return api.post('?resource=ocr&action=processImage', formData)
    }
  },
  
  // Summaries
  generateSummary(noteId, options) {
    return api.post(`?resource=summaries&action=generate&note_id=${noteId}`, options)
  },

  // Summaries
  getSummaries() {
    return api.get('?resource=summaries')
  },
  getSummary(id) {
    return api.get(`?resource=summaries&id=${id}`)
  },
  createSummary(noteId, options) {
    return api.post('?resource=summaries', { note_id: noteId, format: 'paragraph', ...options })
  },

  // Progress
  getProgressStats() {
    return api.get('?resource=progress&action=stats')
  },
  progress: {
    startStudySession(sessionData) {
      return api.post('?resource=progress&action=startStudySession', sessionData)
    },
    endStudySession(sessionData) {
      return api.post('?resource=progress&action=endStudySession', sessionData)
    },
    getStats() {
      return api.get('?resource=progress&action=stats')
    }
  },

  // Dashboard
  getDashboardStats() {
    return api.get('?resource=dashboard&action=stats')
  },

  // Settings
  getSettings() {
    return api.get('?resource=settings')
  },
  updateSettings(settings) {
    // Explicitly stringify the data to ensure proper JSON format
    return api.put('?resource=settings', JSON.stringify(settings), {
      headers: {
        'Content-Type': 'application/json'
      }
    })
  },

  // GPT AI Services
  gpt: {
    generateSummary(text, options = { length: 'medium' }) {
      return api.post('?resource=gpt&action=generateSummary', { text, ...options })
    },
    generateQuiz(text, options = { difficulty: 'medium', questionCount: 5 }) {
      return api.post('?resource=gpt&action=generateQuiz', { text, ...options })
    },
    extractKeywords(text, count = 5) {
      return api.post('?resource=gpt&action=extractKeywords', { text, count })
    }
  },

  // Quizzes
  getQuizzes() {
    return api.get('?resource=quizzes')
  },
  getQuiz(id) {
    console.log('🔄 API getQuiz: Starting with quizId:', id)
    const result = api.get(`?resource=quizzes&id=${id}`)
    console.log('🔄 API getQuiz: Request promise created')
    return result
  },
  createQuiz(noteId, options) {
    console.log('🔄 API createQuiz: Starting with noteId:', noteId)
    console.log('🔄 API createQuiz: Options:', options)
    const requestData = { note_id: noteId, ...options }
    console.log('🔄 API createQuiz: Full request data:', requestData)
    const result = api.post('?resource=quizzes', requestData)
    console.log('🔄 API createQuiz: Request promise created')
    return result
  },
  updateQuiz(id, data) {
    return api.put(`?resource=quizzes&id=${id}`, data)
  },
  deleteQuiz(id) {
    return api.delete(`?resource=quizzes&id=${id}`)
  },
  generateQuiz(noteId, options) {
    return api.post(`?resource=quizzes&action=generate&note_id=${noteId}`, options)
  },
  saveQuiz(quizData) {
    return api.post('?resource=quizzes', quizData)
  },

  // Export
  exportNote(noteId, format) {
    return api.get(`?resource=export&id=${noteId}&format=${format}`, {
      responseType: 'blob' // Important for file downloads
    })
  },

  // Study Sessions
  startStudySession(sessionData) {
    return api.post('?resource=study-sessions&action=start', sessionData)
  },
  endStudySession(sessionData) {
    return api.post('?resource=study-sessions&action=end', sessionData)
  },
  updateStudySessionActivity(sessionData) {
    return api.post('?resource=study-sessions&action=update-activity', sessionData)
  },
  getActiveStudySession() {
    return api.get('?resource=study-sessions&action=active')
  },
  getStudySessionStats(startDate = null, endDate = null) {
    let url = '?resource=study-sessions&action=stats'
    if (startDate) url += `&start_date=${startDate}`
    if (endDate) url += `&end_date=${endDate}`
    return api.get(url)
  },
  getDailyStudyStats(startDate, endDate) {
    return api.get(`?resource=study-sessions&action=daily-stats&start_date=${startDate}&end_date=${endDate}`)
  },
  getStudyStreak() {
    return api.get('?resource=study-sessions&action=streak')
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