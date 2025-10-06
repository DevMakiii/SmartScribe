// src/store/index.js
import { createStore } from 'vuex'

// Import modules
import auth from './modules/auth'
import notes from './modules/notes'
import app from './modules/app'

export default createStore({
  state: {
    user: null,
    notes: [],
    isAuthenticated: false
  },
  getters: {
    getUser: state => state.user,
    getNotes: state => state.notes,
    isAuthenticated: state => state.isAuthenticated
  },
  mutations: {
    setUser(state, user) {
      state.user = user;
      state.isAuthenticated = !!user;
    },
    setNotes(state, notes) {
      state.notes = notes;
    },
    addNote(state, note) {
      state.notes.push(note);
    },
    updateNote(state, updatedNote) {
      const index = state.notes.findIndex(note => note.id === updatedNote.id);
      if (index !== -1) {
        state.notes.splice(index, 1, updatedNote);
      }
    },
    deleteNote(state, noteId) {
      state.notes = state.notes.filter(note => note.id !== noteId);
    }
  },
  actions: {
    login({ commit }, user) {
      // In a real app, you would call an API here
      commit('setUser', user);
      return Promise.resolve(user);
    },
    logout({ commit }) {
      commit('setUser', null);
      return Promise.resolve();
    },
    fetchNotes({ commit }) {
      // In a real app, you would call an API here
      const notes = [
        {
          id: 1,
          title: 'Biology Notes - Chapter 5',
          date: 'May 14, 2025',
          summary: 'Cell structure and function. The cell is the basic unit of life.',
          tags: ['Biology', 'Chapter 5', 'Cells']
        },
        {
          id: 2,
          title: 'History - World War II',
          date: 'May 12, 2025',
          summary: 'World War II was a global war that lasted from 1939 to 1945.',
          tags: ['History', 'WWII']
        }
      ];
      commit('setNotes', notes);
      return Promise.resolve(notes);
    }
  },
  modules: {
    auth,
    notes,
    app
  }
})