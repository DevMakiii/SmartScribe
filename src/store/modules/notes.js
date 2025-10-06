import api from '@/services/api';
import ocrService from '@/services/ocr';
import gptService from '@/services/gpt';

const state = {
  notes: [],
  currentNote: null,
  isLoading: false,
  error: null,
  tempImageData: null
};

const getters = {
  getNotes: state => state.notes,
  getCurrentNote: state => state.currentNote,
  getRecentNotes: state => state.notes.slice(0, 5),
  isLoading: state => state.isLoading,
  getError: state => state.error,
  getTempImageData: state => state.tempImageData
};

const actions = {
  async fetchNotes({ commit }) {
    try {
      commit('SET_LOADING', true);
      const response = await api.getNotes();
      commit('SET_NOTES', response.data.data);
      return response.data.data;
    } catch (error) {
      console.error('Fetch notes error:', error);
      // Handle new error response format
      const errorMessage = error.response?.data?.error ||
                          error.response?.data?.message ||
                          'Failed to fetch notes.';
      commit('SET_ERROR', errorMessage);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async fetchNote({ commit }, noteId) {
    try {
      commit('SET_LOADING', true);
      const response = await api.getNote(noteId);
      commit('SET_CURRENT_NOTE', response.data.data);
      return response.data.data;
    } catch (error) {
      console.error('Fetch note error:', error);
      // Handle new error response format
      const errorMessage = error.response?.data?.error ||
                          error.response?.data?.message ||
                          'Failed to fetch note details.';
      commit('SET_ERROR', errorMessage);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async createNote({ commit }, noteData) {
    try {
      commit('SET_LOADING', true);
      const response = await api.createNote(noteData);
      // Handle both old and new response formats
      const noteDataResponse = response.data.data || response.data;
      commit('ADD_NOTE', noteDataResponse);
      return noteDataResponse;
    } catch (error) {
      console.error('Create note error:', error);
      // Handle new error response format
      const errorMessage = error.response?.data?.error ||
                          error.response?.data?.message ||
                          'Failed to create note.';
      commit('SET_ERROR', errorMessage);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async updateNote({ commit }, { id, noteData }) {
    try {
      commit('SET_LOADING', true);
      const response = await api.updateNote(id, noteData);
      // Handle both old and new response formats
      const noteDataResponse = response.data.data || response.data;
      commit('UPDATE_NOTE', noteDataResponse);
      return noteDataResponse;
    } catch (error) {
      console.error('Update note error:', error);
      // Handle new error response format
      const errorMessage = error.response?.data?.error ||
                          error.response?.data?.message ||
                          'Failed to update note.';
      commit('SET_ERROR', errorMessage);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async deleteNote({ commit }, noteId) {
    try {
      commit('SET_LOADING', true);
      await api.deleteNote(noteId);
      commit('REMOVE_NOTE', noteId);
    } catch (error) {
      console.error('Delete note error:', error);
      // Handle new error response format
      const errorMessage = error.response?.data?.error ||
                          error.response?.data?.message ||
                          'Failed to delete note.';
      commit('SET_ERROR', errorMessage);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async processImage({ commit }, imageFile) {
    try {
      commit('SET_LOADING', true);
      
      // Process image with OCR
      const ocrResult = await ocrService.processImage(imageFile);
      
      // Store the extracted text and image data temporarily
      commit('SET_TEMP_IMAGE_DATA', {
        originalText: ocrResult.text,
        imagePath: ocrResult.image_path
      });
      
      return ocrResult;
    } catch (error) {
      console.error('Process image error:', error);
      commit('SET_ERROR', 'Failed to process image.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async generateSummary({ state, commit }, options = { length: 'medium' }) {
    try {
      if (!state.currentNote && !state.tempImageData) {
        throw new Error('No note data available for summarization.');
      }
      
      commit('SET_LOADING', true);
      
      // Use current note text or temp image data
      const text = state.currentNote ? state.currentNote.originalText : state.tempImageData.originalText;
      
      // Generate summary using GPT
      const summaryResult = await gptService.generateSummary(text, options);
      
      // If we have a current note, update it
      if (state.currentNote) {
        const updatedNote = {
          ...state.currentNote,
          summary: summaryResult.summary
        };
        commit('SET_CURRENT_NOTE', updatedNote);
      }
      
      return summaryResult;
    } catch (error) {
      console.error('Generate summary error:', error);
      commit('SET_ERROR', 'Failed to generate summary.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
  
  async extractKeywords({ state, commit }) {
    try {
      if (!state.currentNote && !state.tempImageData) {
        throw new Error('No note data available for keyword extraction.');
      }
      
      commit('SET_LOADING', true);
      
      // Use current note text or temp image data
      const text = state.currentNote ? state.currentNote.originalText : state.tempImageData.originalText;
      
      // Extract keywords using GPT
      const keywordsResult = await gptService.extractKeywords(text);
      
      // If we have a current note, update it
      if (state.currentNote) {
        const updatedNote = {
          ...state.currentNote,
          keywords: keywordsResult.keywords
        };
        commit('SET_CURRENT_NOTE', updatedNote);
      }
      
      return keywordsResult;
    } catch (error) {
      console.error('Extract keywords error:', error);
      commit('SET_ERROR', 'Failed to extract keywords.');
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  }
};

const mutations = {
  SET_NOTES(state, notes) {
    state.notes = notes;
  },
  
  SET_CURRENT_NOTE(state, note) {
    state.currentNote = note;
  },
  
  ADD_NOTE(state, note) {
    state.notes.unshift(note);
  },
  
  UPDATE_NOTE(state, updatedNote) {
    const index = state.notes.findIndex(note => note.id === updatedNote.id);
    if (index !== -1) {
      state.notes.splice(index, 1, updatedNote);
    }
    if (state.currentNote && state.currentNote.id === updatedNote.id) {
      state.currentNote = updatedNote;
    }
  },
  
  REMOVE_NOTE(state, noteId) {
    state.notes = state.notes.filter(note => note.id !== noteId);
    if (state.currentNote && state.currentNote.id === noteId) {
      state.currentNote = null;
    }
  },
  
  SET_LOADING(state, isLoading) {
    state.isLoading = isLoading;
  },
  
  SET_ERROR(state, error) {
    state.error = error;
  },
  
  SET_TEMP_IMAGE_DATA(state, data) {
    state.tempImageData = data;
  },
  
  CLEAR_TEMP_IMAGE_DATA(state) {
    state.tempImageData = null;
  }
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations
};