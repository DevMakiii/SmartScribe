import api from '@/services/api';

const state = {
  stats: {
    totalNotes: 0,
    notesThisWeek: 0,
    studyHours: 0,
    studyHoursThisWeek: 0,
    quizAverage: 0,
    quizzesCompleted: 0
  },
  weeklyActivity: [],
  subjects: [],
  recentActivity: [],
  goals: [],
  isLoading: false,
  error: null
};

const getters = {
  getStats: state => state.stats,
  getWeeklyActivity: state => state.weeklyActivity,
  getSubjects: state => state.subjects,
  getRecentActivity: state => state.recentActivity,
  getGoals: state => state.goals,
  isLoading: state => state.isLoading,
  getError: state => state.error
};

const actions = {
  async fetchStats({ commit }) {
    try {
      commit('SET_LOADING', true);
      const response = await api.progress.getStats();
      commit('SET_STATS', response.data);
      return response.data;
    } catch (error) {
      console.error('Fetch stats error:', error);
      commit('SET_ERROR', 'Failed to fetch progress statistics.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async fetchActivity({ commit }) {
    try {
      commit('SET_LOADING', true);
      const response = await api.progress.getActivity();
      
      commit('SET_WEEKLY_ACTIVITY', response.data.weeklyActivity);
      commit('SET_SUBJECTS', response.data.subjects);
      commit('SET_RECENT_ACTIVITY', response.data.recentActivity);
      commit('SET_GOALS', response.data.goals);
      
      return response.data;
    } catch (error) {
      console.error('Fetch activity error:', error);
      commit('SET_ERROR', 'Failed to fetch activity data.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async updateProgress({ commit }, progressData) {
    try {
      commit('SET_LOADING', true);
      const response = await api.progress.updateProgress(progressData);
      
      // Update relevant state based on the type of progress update
      if (progressData.type === 'quiz') {
        commit('UPDATE_QUIZ_STATS', progressData);
      } else if (progressData.type === 'study') {
        commit('UPDATE_STUDY_HOURS', progressData);
      }
      
      return response.data;
    } catch (error) {
      console.error('Update progress error:', error);
      commit('SET_ERROR', 'Failed to update progress.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async addGoal({ commit }, goalData) {
    try {
      commit('SET_LOADING', true);
      // In a real app, this would be an API call
      // For now, we'll simulate it
      const newGoal = {
        id: Date.now(),
        ...goalData,
        progress: 0
      };
      
      commit('ADD_GOAL', newGoal);
      return newGoal;
    } catch (error) {
      console.error('Add goal error:', error);
      commit('SET_ERROR', 'Failed to add goal.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async updateGoal({ commit }, { id, progress }) {
    try {
      commit('SET_LOADING', true);
      // In a real app, this would be an API call
      // For now, we'll simulate it
      
      commit('UPDATE_GOAL_PROGRESS', { id, progress });
    } catch (error) {
      console.error('Update goal error:', error);
      commit('SET_ERROR', 'Failed to update goal.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async deleteGoal({ commit }, goalId) {
    try {
      commit('SET_LOADING', true);
      // In a real app, this would be an API call
      // For now, we'll simulate it
      
      commit('REMOVE_GOAL', goalId);
    } catch (error) {
      console.error('Delete goal error:', error);
      commit('SET_ERROR', 'Failed to delete goal.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  }
};

const mutations = {
  SET_STATS(state, stats) {
    state.stats = stats;
  },
  
  SET_WEEKLY_ACTIVITY(state, weeklyActivity) {
    state.weeklyActivity = weeklyActivity;
  },
  
  SET_SUBJECTS(state, subjects) {
    state.subjects = subjects;
  },
  
  SET_RECENT_ACTIVITY(state, recentActivity) {
    state.recentActivity = recentActivity;
  },
  
  SET_GOALS(state, goals) {
    state.goals = goals;
  },
  
  UPDATE_QUIZ_STATS(state, { score }) {
    state.stats.quizzesCompleted++;
    
    // Update average score
    const totalScores = state.stats.quizAverage * (state.stats.quizzesCompleted - 1);
    state.stats.quizAverage = Math.round((totalScores + score) / state.stats.quizzesCompleted);
  },
  
  UPDATE_STUDY_HOURS(state, { hours }) {
    state.stats.studyHours += hours;
    state.stats.studyHoursThisWeek += hours;
  },
  
  ADD_GOAL(state, goal) {
    state.goals.push(goal);
  },
  
  UPDATE_GOAL_PROGRESS(state, { id, progress }) {
    const goal = state.goals.find(g => g.id === id);
    if (goal) {
      goal.progress = progress;
    }
  },
  
  REMOVE_GOAL(state, goalId) {
    state.goals = state.goals.filter(goal => goal.id !== goalId);
  },
  
  SET_LOADING(state, isLoading) {
    state.isLoading = isLoading;
  },
  
  SET_ERROR(state, error) {
    state.error = error;
  }
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations
};