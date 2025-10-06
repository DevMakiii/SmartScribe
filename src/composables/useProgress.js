import { ref, computed } from 'vue'
import api from '@/services/api'

export function useProgress() {
  // Reactive state
  const progressStats = ref(null)
  const studySession = ref(null)
  const learningGoals = ref([])
  const achievements = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Computed properties
  const totalStudyTime = computed(() => progressStats.value?.totalStudyTime || 0)
  const studyStreak = computed(() => progressStats.value?.studyStreak || 0)
  const activeGoals = computed(() => progressStats.value?.activeGoals || 0)
  const completedGoals = computed(() => progressStats.value?.completedGoals || 0)
  const totalAchievements = computed(() => progressStats.value?.totalAchievements || 0)

  // Study session management
  const startStudySession = async (activities = []) => {
    // Check if we already have an active session
    if (studySession.value) {
      console.log('useProgress: Study session already active, updating activities')
      studySession.value.activities = [...new Set([...studySession.value.activities, ...activities])]
      return { success: true, sessionId: studySession.value.id }
    }
    try {
      loading.value = true
      error.value = null

      // Record the exact start time on the frontend
      const startTime = new Date()
      console.log('useProgress: Starting study session at:', startTime.toISOString())

      // Check authentication
      const token = localStorage.getItem('token')
      const userData = localStorage.getItem('user')
      console.log('useProgress: Auth token exists:', !!token)
      console.log('useProgress: User data exists:', !!userData)

      if (userData) {
        try {
          const user = JSON.parse(userData)
          console.log('useProgress: User ID:', user.id)
        } catch (e) {
          console.error('useProgress: Error parsing user data:', e)
        }
      }

      const requestData = {
        activities: activities,
        startTime: startTime.toISOString()
      }
      console.log('useProgress: Sending request data:', requestData)

      const response = await api.progress.startStudySession(requestData)
      console.log('useProgress: API response:', response)
      console.log('useProgress: Response data:', response.data)
      console.log('useProgress: Response status:', response.status)

      if (response.data.success) {
        // Use the start time returned from backend for consistency
        const backendStartTime = response.data.start_time ? new Date(response.data.start_time) : startTime;
        console.log('useProgress: Backend start time:', backendStartTime.toISOString())

        studySession.value = {
          id: response.data.session_id,
          startTime: backendStartTime, // Use backend time for consistency
          activities: activities
        }
        console.log('useProgress: Study session object created:', studySession.value)

        return { success: true, sessionId: response.data.session_id }
      } else {
        console.error('useProgress: Backend returned success=false')
        console.error('useProgress: Backend error message:', response.data.error)
        console.error('useProgress: Full backend response:', JSON.stringify(response.data, null, 2))
        throw new Error(response.data.error || 'Failed to start study session')
      }
    } catch (err) {
      error.value = err.message
      console.error('useProgress: Error starting study session:', err)
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  const endStudySession = async () => {
    console.log('useProgress: Ending study session...')
    if (!studySession.value) {
      console.log('useProgress: No active study session found')
      return { success: false, error: 'No active study session' }
    }

    console.log('useProgress: Current study session:', studySession.value)

    try {
      loading.value = true
      error.value = null

      const requestData = {
        session_id: studySession.value.id
      }
      console.log('useProgress: Sending end session request:', requestData)

      const response = await api.progress.endStudySession(requestData)
      console.log('useProgress: End session API response:', response)
      console.log('useProgress: End session response data:', response.data)
      console.log('useProgress: End session response status:', response.status)

      if (response.data.success) {
        const endedSession = { ...studySession.value }
        console.log('useProgress: Clearing study session')
        studySession.value = null

        // Refresh progress stats to get updated metrics
        console.log('useProgress: Refreshing progress stats')
        await loadProgressStats()

        return { success: true, session: endedSession }
      } else {
        throw new Error(response.data.error || 'Failed to end study session')
      }
    } catch (err) {
      error.value = err.message
      console.error('useProgress: Error ending study session:', err)
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  // Automatic session management
  const startActivitySession = async (activityType, activityData = {}) => {
    console.log('useProgress: Starting activity session:', activityType, activityData)

    const activities = [activityType]
    if (activityData.noteId) {
      activities.push(`note_${activityData.noteId}`)
    }
    if (activityData.quizId) {
      activities.push(`quiz_${activityData.quizId}`)
    }

    return await startStudySession(activities)
  }

  const endActivitySession = async (activityType) => {
    console.log('useProgress: Ending activity session:', activityType)

    // Only end session if this is the last activity
    if (studySession.value && studySession.value.activities) {
      const remainingActivities = studySession.value.activities.filter(activity =>
        !activity.includes(activityType.toLowerCase())
      )

      if (remainingActivities.length === 0) {
        // No more activities, end the session
        return await endStudySession()
      } else {
        // Update activities but keep session running
        studySession.value.activities = remainingActivities
        console.log('useProgress: Updated activities, session continues:', remainingActivities)
        return { success: true, session: studySession.value }
      }
    }

    return { success: true, message: 'No active session to end' }
  }

  const isSessionActive = computed(() => !!studySession.value)
  const currentSessionActivities = computed(() => studySession.value?.activities || [])

  // Learning goals management
  const createLearningGoal = async (goalData) => {
    try {
      loading.value = true
      error.value = null

      const response = await api.progress.createLearningGoal(goalData)

      if (response.data.success) {
        // Refresh goals
        await loadProgressStats()
        return { success: true, goalId: response.data.goal_id }
      } else {
        throw new Error(response.data.error || 'Failed to create learning goal')
      }
    } catch (err) {
      error.value = err.message
      console.error('Error creating learning goal:', err)
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  const updateGoalProgress = async (goalId) => {
    try {
      loading.value = true
      error.value = null

      const response = await api.progress.updateGoalProgress({ goal_id: goalId })

      if (response.data.success) {
        // Refresh stats
        await loadProgressStats()
        return {
          success: true,
          currentValue: response.data.current_value,
          targetValue: response.data.target_value,
          completed: response.data.completed
        }
      } else {
        throw new Error(response.data.error || 'Failed to update goal progress')
      }
    } catch (err) {
      error.value = err.message
      console.error('Error updating goal progress:', err)
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  // Progress stats loading
  const loadProgressStats = async () => {
    try {
      loading.value = true
      error.value = null

      const response = await api.progress.getStats()

      if (response.data.success) {
        progressStats.value = response.data.data
        return { success: true, data: response.data.data }
      } else {
        throw new Error(response.data.error || 'Failed to load progress stats')
      }
    } catch (err) {
      error.value = err.message
      console.error('Error loading progress stats:', err)
      return { success: false, error: err.message }
    } finally {
      loading.value = false
    }
  }

  // Utility functions
  const formatStudyTime = (minutes) => {
    if (minutes < 60) {
      return `${minutes}m`
    }
    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60
    return remainingMinutes > 0 ? `${hours}h ${remainingMinutes}m` : `${hours}h`
  }

  const getStreakEmoji = (streak) => {
    if (streak >= 30) return '🔥'
    if (streak >= 7) return '⚡'
    if (streak >= 3) return '⭐'
    return '🌱'
  }

  const getPerformanceLevel = (accuracy) => {
    if (accuracy >= 0.9) return { level: 'Excellent', color: 'text-green-400', bgColor: 'bg-green-600/20' }
    if (accuracy >= 0.8) return { level: 'Very Good', color: 'text-blue-400', bgColor: 'bg-blue-600/20' }
    if (accuracy >= 0.7) return { level: 'Good', color: 'text-yellow-400', bgColor: 'bg-yellow-600/20' }
    if (accuracy >= 0.6) return { level: 'Fair', color: 'text-orange-400', bgColor: 'bg-orange-600/20' }
    return { level: 'Needs Improvement', color: 'text-red-400', bgColor: 'bg-red-600/20' }
  }

  return {
    // State
    progressStats,
    studySession,
    learningGoals,
    achievements,
    loading,
    error,

    // Computed
    totalStudyTime,
    studyStreak,
    activeGoals,
    completedGoals,
    totalAchievements,
    isSessionActive,
    currentSessionActivities,

    // Methods
    startStudySession,
    endStudySession,
    startActivitySession,
    endActivitySession,
    createLearningGoal,
    updateGoalProgress,
    loadProgressStats,

    // Utilities
    formatStudyTime,
    getStreakEmoji,
    getPerformanceLevel
  }
}