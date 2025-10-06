import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../services/api'

export function useStudyTime() {
  const route = useRoute()

  // Reactive state
  const currentSession = ref(null)
  const isTracking = ref(false)
  const elapsedTime = ref(0)
  const startTime = ref(null)
  const timer = ref(null)
  const sessionStats = ref({
    totalSessions: 0,
    totalMinutes: 0,
    totalHours: 0,
    avgSessionMinutes: 0,
    totalNotesStudied: 0,
    totalQuizzesTaken: 0,
    avgQuizScore: null,
    lastSessionDate: null,
    currentStreak: 0
  })

  // Activity tracking
  const currentActivity = ref('general_study')
  const activities = ref([])
  const notesStudied = ref(0)
  const quizzesTaken = ref(0)
  const averageScore = ref(null)

  // Computed properties
  const formattedElapsedTime = computed(() => {
    const hours = Math.floor(elapsedTime.value / 3600)
    const minutes = Math.floor((elapsedTime.value % 3600) / 60)
    const seconds = elapsedTime.value % 60

    if (hours > 0) {
      return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
    }
    return `${minutes}:${seconds.toString().padStart(2, '0')}`
  })

  const isActiveSession = computed(() => {
    return currentSession.value && !currentSession.value.duration_minutes
  })
  
  // Page visibility API for pause/resume
  const handleVisibilityChange = () => {
    if (document.hidden) {
      // Pause timer when tab becomes hidden
      console.log('🔄 Pausing timer - tab hidden')
      stopTimer()
    } else if (!document.hidden && isTracking.value) {
      // Resume timer when tab becomes visible
      console.log('🔄 Resuming timer - tab visible')
      startTimer()
    }
  }

  // User activity detection
  const lastActivity = ref(Date.now())
  const activityTimeout = ref(null)
  const INACTIVITY_TIMEOUT = 5 * 60 * 1000 // 5 minutes

  const resetActivityTimer = () => {
    lastActivity.value = Date.now()

    // Clear existing timeout
    if (activityTimeout.value) {
      clearTimeout(activityTimeout.value)
    }

    // Set new timeout to auto-pause after inactivity
    activityTimeout.value = setTimeout(() => {
      if (isTracking.value) {
        console.log('🔄 Auto-pausing timer due to inactivity')
        stopTimer()
      }
    }, INACTIVITY_TIMEOUT)

    // Also reset the auto-end timer since there's activity
    resetAutoEndTimer()
  }

  const addActivityListeners = () => {
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart']
    events.forEach(event => {
      document.addEventListener(event, resetActivityTimer, true)
    })
  }

  const removeActivityListeners = () => {
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart']
    events.forEach(event => {
      document.removeEventListener(event, resetActivityTimer, true)
    })
  }

  // Timer functions
  const startTimer = () => {
    if (timer.value) return

    startTime.value = Date.now()
    timer.value = setInterval(() => {
      elapsedTime.value = Math.floor((Date.now() - startTime.value) / 1000)
    }, 1000)
  }

  const stopTimer = () => {
    if (timer.value) {
      clearInterval(timer.value)
      timer.value = null
    }
  }

  const resetTimer = () => {
    stopTimer()
    elapsedTime.value = 0
    startTime.value = null
  }

  // API functions
  const startStudySession = async (activity = 'general_study') => {
    try {
      const response = await api.startStudySession({
        activity: activity
      })

      if (response.data.success) {
        currentSession.value = response.data.data
        currentActivity.value = activity
        isTracking.value = true
        startTimer()

        // Add activity to activities array
        activities.value.push({
          type: activity,
          timestamp: new Date(),
          sessionId: currentSession.value.id
        })

        return { success: true, session: currentSession.value }
      } else {
        throw new Error(response.data.message || 'Failed to start session')
      }
    } catch (error) {
      console.error('Error starting study session:', error)
      return { success: false, error: error.message }
    }
  }

  const endStudySession = async (sessionData = {}) => {
    if (!currentSession.value) {
      return { success: false, error: 'No active session' }
    }

    try {
      const endData = {
        session_id: currentSession.value.id,
        notes_studied: sessionData.notesStudied || notesStudied.value,
        quizzes_taken: sessionData.quizzesTaken || quizzesTaken.value,
        average_score: sessionData.averageScore || averageScore.value,
        focus_level: sessionData.focusLevel || 'medium'
      }

      const response = await api.endStudySession(endData)

      if (response.data.success) {
        const endedSession = response.data.data
        currentSession.value = null
        isTracking.value = false
        stopTimer()

        // Reset activity counters
        notesStudied.value = 0
        quizzesTaken.value = 0
        averageScore.value = null

        return { success: true, session: endedSession }
      } else {
        throw new Error(response.data.message || 'Failed to end session')
      }
    } catch (error) {
      console.error('Error ending study session:', error)
      return { success: false, error: error.message }
    }
  }

  const updateActivity = async (activity) => {
    if (!currentSession.value) return

    try {
      const response = await api.updateStudySessionActivity({
        session_id: currentSession.value.id,
        activity: activity
      })

      if (response.data.success) {
        currentActivity.value = activity
        activities.value.push({
          type: activity,
          timestamp: new Date(),
          sessionId: currentSession.value.id
        })
      }
    } catch (error) {
      console.error('Error updating activity:', error)
    }
  }

  const getActiveSession = async () => {
    try {
      const response = await api.getActiveStudySession()

      if (response.data.success && response.data.data) {
        currentSession.value = response.data.data
        isTracking.value = true

        // Resume timer if session is active
        if (currentSession.value.start_time) {
          const sessionStart = new Date(currentSession.value.start_time).getTime()
          const now = Date.now()
          elapsedTime.value = Math.floor((now - sessionStart) / 1000)
          startTimer()
        }

        return { success: true, session: currentSession.value }
      } else {
        currentSession.value = null
        isTracking.value = false
        return { success: true, session: null }
      }
    } catch (error) {
      console.error('Error getting active session:', error)
      return { success: false, error: error.message }
    }
  }

  const getStudyStats = async (startDate = null, endDate = null) => {
    try {
      const response = await api.getStudySessionStats(startDate, endDate)

      if (response.data.success) {
        sessionStats.value = response.data.data
        return { success: true, stats: sessionStats.value }
      } else {
        throw new Error(response.data.message || 'Failed to get stats')
      }
    } catch (error) {
      console.error('Error getting study stats:', error)
      return { success: false, error: error.message }
    }
  }

  const getDailyStats = async (startDate, endDate) => {
    try {
      const response = await api.getDailyStudyStats(startDate, endDate)

      if (response.data.success) {
        return { success: true, dailyStats: response.data.data }
      } else {
        throw new Error(response.data.message || 'Failed to get daily stats')
      }
    } catch (error) {
      console.error('Error getting daily stats:', error)
      return { success: false, error: error.message }
    }
  }

  const getStudyStreak = async () => {
    try {
      const response = await api.getStudyStreak()

      if (response.data.success) {
        return { success: true, streak: response.data.data }
      } else {
        throw new Error(response.data.message || 'Failed to get streak')
      }
    } catch (error) {
      console.error('Error getting study streak:', error)
      return { success: false, error: error.message }
    }
  }

  // Activity tracking helpers
  const incrementNotesStudied = () => {
    notesStudied.value++
  }

  const incrementQuizzesTaken = (score = null) => {
    quizzesTaken.value++
    if (score !== null) {
      // Update average score
      if (averageScore.value === null) {
        averageScore.value = score
      } else {
        averageScore.value = (averageScore.value + score) / 2
      }
    }
  }

  const setCurrentActivity = (activity) => {
    currentActivity.value = activity
    if (isTracking.value) {
      updateActivity(activity)
    }
  }

  // Auto-start/stop based on routes
  const studyRoutes = ['/notes', '/quizzes']

  watch(() => route.path, async (newPath, oldPath) => {
    const isOnStudyPage = studyRoutes.some(route => newPath.startsWith(route))
    const wasOnStudyPage = studyRoutes.some(route => oldPath && oldPath.startsWith(route))

    if (isOnStudyPage && !isTracking.value) {
      // Auto-start session based on route
      const activity = newPath.startsWith('/notes') ? 'reading_notes' : 'taking_quiz'
      console.log('🔄 Auto-starting study session for activity:', activity)
      await startStudySession(activity)
    } else if (!isOnStudyPage && wasOnStudyPage && isTracking.value) {
      // Auto-end session when leaving study pages
      console.log('🔄 Auto-ending study session due to route change')
      await endStudySession()
    }
  }, { immediate: true })

  // Auto-save functionality
  const autoSaveInterval = ref(null)
  const autoEndTimeout = ref(null)

  const startAutoSave = () => {
    if (autoSaveInterval.value) return

    autoSaveInterval.value = setInterval(async () => {
      if (isTracking.value && currentSession.value) {
        // Update session with current activity data
        try {
          await api.updateStudySessionActivity({
            session_id: currentSession.value.id,
            activity: currentActivity.value
          })
        } catch (error) {
          console.error('Auto-save failed:', error)
        }
      }
    }, 30000) // Auto-save every 30 seconds
  }

  const stopAutoSave = () => {
    if (autoSaveInterval.value) {
      clearInterval(autoSaveInterval.value)
      autoSaveInterval.value = null
    }
  }

  // Auto-end session after period of inactivity
  const startAutoEndTimer = () => {
    if (autoEndTimeout.value) {
      clearTimeout(autoEndTimeout.value)
    }

    autoEndTimeout.value = setTimeout(async () => {
      if (isTracking.value) {
        console.log('🔄 Auto-ending study session due to extended inactivity')
        await endStudySession()
      }
    }, 2 * 60 * 60 * 1000) // Auto-end after 2 hours of continuous tracking
  }

  const resetAutoEndTimer = () => {
    if (isTracking.value) {
      startAutoEndTimer()
    }
  }

  const stopAutoEndTimer = () => {
    if (autoEndTimeout.value) {
      clearTimeout(autoEndTimeout.value)
      autoEndTimeout.value = null
    }
  }

  // Window beforeunload handler
  const handleBeforeUnload = async () => {
    if (isTracking.value) {
      console.log('🔄 Ending study session due to page unload')
      // End session synchronously if possible
      try {
        await endStudySession()
      } catch (error) {
        console.error('Failed to end session on unload:', error)
      }
    }
  }

  // Lifecycle
  onMounted(async () => {
    // Check for active session on component mount
    await getActiveSession()

    // Start auto-save if tracking
    if (isTracking.value) {
      startAutoSave()
      startAutoEndTimer()
    }

    // Add visibility change listener
    document.addEventListener('visibilitychange', handleVisibilityChange)

    // Add beforeunload listener to end sessions when page is closed/refreshed
    window.addEventListener('beforeunload', handleBeforeUnload)

    // Add activity listeners
    addActivityListeners()
    resetActivityTimer() // Start the activity timer
  })

  onUnmounted(() => {
    stopTimer()
    stopAutoSave()
    stopAutoEndTimer()

    // Remove visibility change listener
    document.removeEventListener('visibilitychange', handleVisibilityChange)

    // Remove beforeunload listener
    window.removeEventListener('beforeunload', handleBeforeUnload)

    // Remove activity listeners
    removeActivityListeners()
    if (activityTimeout.value) {
      clearTimeout(activityTimeout.value)
    }
  })

  // Watch for tracking state changes
  watch(isTracking, (newValue) => {
    if (newValue) {
      startAutoSave()
      startAutoEndTimer()
    } else {
      stopAutoSave()
      stopAutoEndTimer()
    }
  })

  return {
    // State
    currentSession,
    isTracking,
    elapsedTime,
    sessionStats,
    currentActivity,
    activities,
    notesStudied,
    quizzesTaken,
    averageScore,

    // Computed
    formattedElapsedTime,
    isActiveSession,

    // Methods
    startStudySession,
    endStudySession,
    updateActivity,
    getActiveSession,
    getStudyStats,
    getDailyStats,
    getStudyStreak,

    // Activity helpers
    incrementNotesStudied,
    incrementQuizzesTaken,
    setCurrentActivity,

    // Timer controls
    startTimer,
    stopTimer,
    resetTimer,

    // Auto-end controls
    startAutoEndTimer,
    resetAutoEndTimer,
    stopAutoEndTimer
  }
}