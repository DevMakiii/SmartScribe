<template>
  <Header @open-profile-modal="openProfileModal">

      <!-- Note Detail Main Content -->
      <main :class="`${themeClasses.mainContent} flex-1 p-4 sm:p-6 transition-all duration-300 ease-in-out`">
        <div v-if="isLoading" class="flex justify-center items-center h-full">
          <font-awesome-icon :icon="['fas', 'spinner']" spin class="text-3xl sm:text-4xl text-blue-500" />
        </div>
        
        <div v-else-if="note && !error">
          <div class="flex flex-col justify-between items-start mb-6 space-y-4" style="flex-direction: column !important;">
            <div>
              <h1 :class="`${themeClasses.text} font-bold ${fontSizeClasses.heading}`">{{ note.title }}</h1>
              <p :class="`${themeClasses.tertiaryText} ${fontSizeClasses.label}`">Last edited: {{ note.lastEdited }}</p>
            </div>
            <div class="flex space-x-2 sm:space-x-3 w-full sm:w-auto">
              <button @click="editNote" class="flex-1 sm:flex-none px-3 py-2 sm:px-4 sm:py-2 text-sm sm:text-base bg-blue-600 rounded-md hover:bg-blue-700 transition">
                <font-awesome-icon :icon="['fas', 'edit']" class="mr-2" /> Edit
              </button>
              <button @click="showExportOptions = !showExportOptions" :class="`flex-1 sm:flex-none px-3 py-2 sm:px-4 sm:py-2 text-sm sm:text-base ${themeClasses.button} rounded-md transition relative`">
                <font-awesome-icon :icon="['fas', 'file-export']" class="mr-2" /> Export
                
                <!-- Export Options Dropdown -->
                <div v-if="showExportOptions" :class="`absolute right-0 mt-2 w-48 sm:w-56 ${themeClasses.card} rounded-md shadow-lg py-1 z-10 max-w-full`">
                  <button @click="exportNote('pdf')" :class="`block w-full text-left px-4 py-2 ${themeClasses.hover}`">
                    <font-awesome-icon :icon="['fas', 'file-code']" class="mr-2" /> HTML for PDF conversion
                  </button>
                  <button @click="exportNote('word')" :class="`block w-full text-left px-4 py-2 ${themeClasses.hover}`">
                    <font-awesome-icon :icon="['fas', 'file-word']" class="mr-2" /> Word Document (.doc)
                  </button>
                  <button @click="exportNote('text')" :class="`block w-full text-left px-4 py-2 ${themeClasses.hover}`">
                    <font-awesome-icon :icon="['fas', 'file-alt']" class="mr-2" /> Plain Text (.txt)
                  </button>
                </div>
              </button>
            </div>
          </div>

          <!-- Study Time Tracker -->
          <div :class="`${themeClasses.card} rounded-lg p-4 sm:p-6 mb-6`">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 space-y-2 sm:space-y-0">
              <h2 :class="`${themeClasses.text} font-semibold ${fontSizeClasses.body}`">Study Session</h2>
              <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-2 w-full sm:w-auto">
                <div class="flex items-center space-x-2 text-sm">
                  <font-awesome-icon :icon="['fas', 'clock']" class="text-blue-400" />
                  <span :class="themeClasses.secondaryText">{{ formattedElapsedTime }}</span>
                </div>
                <div v-if="isTracking" class="flex items-center space-x-2 text-sm text-green-400">
                  <font-awesome-icon :icon="['far', 'circle']" class="animate-pulse" />
                  <span>Auto-tracking study time</span>
                </div>
                <div v-else :class="`text-sm ${themeClasses.tertiaryText}`">
                  Study time tracking will start automatically
                </div>
              </div>
            </div>
            <div v-if="isActiveSession" class="bg-green-900 border border-green-700 rounded-lg p-3 mb-4">
              <div class="flex items-center space-x-2 text-sm text-green-300">
                <font-awesome-icon :icon="['far', 'circle']" class="text-green-400 animate-pulse" />
                <span>Study session active - Tracking your reading time</span>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:gap-6 mb-6" style="grid-template-columns: 1fr;">
            <!-- Original Text -->
            <div :class="`${themeClasses.card} rounded-lg p-4 sm:p-6`">
              <h2 :class="`${themeClasses.text} font-semibold mb-4 ${fontSizeClasses.body}`">Original Text</h2>
              <div
                :class="`${themeClasses.input} rounded-lg p-3 sm:p-4 ${themeClasses.text} h-64 sm:h-96 overflow-y-auto overflow-x-hidden text-sm sm:text-base break-words`"
                @scroll="onNoteScroll"
              >
                {{ note.originalText }}
              </div>
            </div>

            <!-- AI Summary -->
            <div :class="`${themeClasses.card} rounded-lg p-4 sm:p-6`">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 space-y-2 sm:space-y-0">
                <h2 :class="`${themeClasses.text} font-semibold ${fontSizeClasses.body}`">AI Summary</h2>
                <button @click="generateSummary" class="w-full sm:w-auto px-3 py-2 sm:px-3 sm:py-1 bg-blue-600 rounded text-sm hover:bg-blue-700 transition" :disabled="isGeneratingSummary">
                  <font-awesome-icon :icon="['fas', 'sync-alt']" class="mr-1" :spin="generatingSummary" /> {{ generatingSummary ? 'Generating...' : 'Generate Summary' }}
                </button>
              </div>
              <div :class="`${themeClasses.input} rounded-lg p-3 sm:p-4 ${themeClasses.text} h-64 sm:h-96 overflow-y-auto overflow-x-hidden text-sm sm:text-base break-words whitespace-pre-line`">
                {{ note.summary || 'No summary available. Click "Generate Summary" to create one.' }}
              </div>
            </div>
          </div>

          <!-- Keywords and Tags -->
          <div :class="`${themeClasses.card} rounded-lg p-4 sm:p-6 mb-6`">
            <h2 :class="`${themeClasses.text} font-semibold mb-4 ${fontSizeClasses.body}`">Keywords & Tags</h2>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="(keyword, index) in note.keywords"
                :key="`keyword-${index}`"
                class="px-2 py-1 sm:px-3 sm:py-1 bg-blue-600 rounded-full text-xs sm:text-sm"
              >
                {{ keyword }}
              </span>
            </div>
          </div>

        </div>
        
        <div v-else-if="error" class="flex flex-col items-center justify-center h-full">
           <font-awesome-icon :icon="['fas', 'times']" class="text-4xl text-red-400 mb-4" />
           <h2 :class="`${themeClasses.text} font-medium mb-2 ${fontSizeClasses.heading}`">Error Loading Note</h2>
           <p :class="`${themeClasses.tertiaryText} mb-4`">{{ error }}</p>
           <router-link to="/notes" class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 transition">
             Back to Notes
           </router-link>
         </div>

         <div v-else class="flex flex-col items-center justify-center h-full">
           <font-awesome-icon :icon="['fas', 'exclamation-circle']" class="text-4xl text-gray-400 mb-4" />
           <h2 :class="`${themeClasses.text} font-medium mb-2 ${fontSizeClasses.heading}`">Note Not Found</h2>
           <p :class="`${themeClasses.tertiaryText} mb-4`">The note you're looking for doesn't exist or has been deleted.</p>
           <router-link to="/notes" class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 transition">
             Back to Notes
           </router-link>
         </div>
      </main>
  </Header>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter, useRoute } from 'vue-router';
import { useNotifications } from '@/composables/useNotifications';
import { useUserProfile } from '@/composables/useUserProfile';
import { useStudyTime } from '@/composables/useStudyTime';
import api from '@/services/api';
import Header from '@/components/Header.vue';

export default {
  name: 'NoteDetailView',
  components: {
    Header
  },
  setup() {
    const store = useStore();
    const router = useRouter();
    const route = useRoute();

    // =====================================
    // NOTIFICATION SYSTEM
    // =====================================
    const {
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      closeNotifications,
      markAsRead,
      markAllAsRead,
      showSuccess,
      showInfo,
      showWarning,
      showError
    } = useNotifications();

    // =====================================
    // USER PROFILE SYSTEM
    // =====================================
    const {
      user,
      loadUserProfile,
      getProfilePictureUrl
    } = useUserProfile();

    // =====================================
    // STUDY TIME TRACKING SYSTEM
    // =====================================
    const {
      currentSession,
      isTracking,
      formattedElapsedTime,
      isActiveSession,
      endStudySession: endSession,
      setCurrentActivity
    } = useStudyTime();

    const note = ref(null);
    const isLoading = ref(true);
    const showExportOptions = ref(false);
    const error = ref(null);
    const generatingSummary = ref(false);
    const sidebarOpen = ref(false);
    const showUserMenu = ref(false);

    // Computed property to safely access generatingSummary state
    const isGeneratingSummary = computed(() => generatingSummary.value);

    // Sidebar visibility from store
    const sidebarVisible = computed(() => store.getters['app/getSidebarVisible']);

    // =====================================
    // SIDEBAR FUNCTIONS
    // =====================================

    /**
     * Toggle sidebar visibility
     */
    const toggleSidebar = () => {
      store.dispatch('app/toggleSidebar');
    };

    // =====================================
    // USER MENU FUNCTIONS
    // =====================================

    /**
     * Toggle user menu dropdown
     */
    const toggleUserMenu = () => {
      showUserMenu.value = !showUserMenu.value;
    };

    /**
     * Close user menu dropdown
     */
    const closeUserMenu = () => {
      showUserMenu.value = false;
    };

    /**
     * Open user profile modal
     */
    const openProfileModal = () => {
      showUserMenu.value = false;
    };

    /**
     * Logout user and redirect to login
     */
    const logout = async () => {
      try {
        // Clear user data from store
        store.dispatch('auth/logout');

        // Clear localStorage
        localStorage.removeItem('user');
        localStorage.removeItem('token');

        // Close menu
        showUserMenu.value = false;

        // Redirect to login
        router.push('/login');
      } catch (error) {
        showWarning('Error logging out', 'Please try again');
      }
    };

    // =====================================
    // IMAGE HANDLING FUNCTIONS
    // =====================================

    /**
     * Handle image loading errors
     */
    const handleImageError = () => {
      // Profile picture failed to load
    };

    /**
     * Handle successful image loading
     */
    const handleImageLoad = () => {
      // Profile picture loaded successfully
    };

    // Use global theme classes from store
    const themeClasses = computed(() => {
      try {
        const classes = store.getters['app/getThemeClasses'];
        return classes && typeof classes === 'object' ? classes : {
          main: 'bg-gray-900 text-white',
          header: 'bg-gray-800',
          sidebar: 'bg-gray-800',
          mainContent: '',
          card: 'bg-gray-800',
          text: 'text-white',
          secondaryText: 'text-gray-400',
          input: 'bg-gray-700 border-gray-600 text-white',
          button: 'bg-gray-700 hover:bg-gray-600'
        };
      } catch (error) {
        return {
          main: 'bg-gray-900 text-white',
          header: 'bg-gray-800',
          sidebar: 'bg-gray-800',
          mainContent: '',
          card: 'bg-gray-800',
          text: 'text-white',
          secondaryText: 'text-gray-400',
          input: 'bg-gray-700 border-gray-600 text-white',
          button: 'bg-gray-700 hover:bg-gray-600'
        };
      }
    });

    // Use global font size classes from store
    const fontSizeClasses = computed(() => {
      try {
        return store.getters['app/getFontSizeClasses'];
      } catch (error) {
        return {
          heading: 'text-xl',
          body: 'text-base',
          label: 'text-sm',
          small: 'text-xs'
        };
      }
    });

    onMounted(async () => {
      try {
        // Load user profile data first
        await loadUserProfile();

        const noteId = parseInt(route.params.id);

        if (!noteId || isNaN(noteId)) {
          error.value = 'Invalid note ID';
          isLoading.value = false;
          return;
        }

        // Fetch the actual note data from API
        const response = await api.getNote(noteId);

        if (response.data.success && response.data.data) {
          const noteData = response.data.data;

          note.value = {
            id: noteData.id,
            title: noteData.title || 'Untitled Note',
            lastEdited: noteData.last_edited || 'Unknown',
            originalText: noteData.original_text || 'No content available',
            summary: noteData.summary || 'No summary available',
            keywords: noteData.keywords ? noteData.keywords.split(',').map(k => k.trim()) : []
          };
        } else {
          error.value = response.data?.error || 'Note not found';
        }
      } catch (error) {
        console.error('Error loading note:', error);
        error.value = 'Failed to load note. Please try again.';
      } finally {
        isLoading.value = false;
      }
    });

    const editNote = () => {
      if (note.value) {
        router.push(`/notes/edit?id=${note.value.id}`);
      }
    };

    const exportNote = async (format) => {
      if (!note.value || !note.value.id) {
        alert('No note to export');
        return;
      }

      try {
        console.log(`Exporting note ${note.value.id} as ${format}...`);
        showExportOptions.value = false;

        // Map frontend format names to backend format names
        const backendFormat = format === 'word' ? 'docx' : format === 'text' ? 'txt' : format;
        const fileExtension = format === 'pdf' ? 'html' : format === 'word' ? 'doc' : format === 'text' ? 'txt' : format;

        console.log(`Making API call to export with backendFormat: ${backendFormat}`);
        const response = await api.exportNote(note.value.id, backendFormat);
        console.log('API response received:', response);
        console.log('Response data type:', typeof response.data);
        console.log('Response data length:', response.data ? response.data.length : 'N/A');

        // Create blob and download
        const mimeType = backendFormat === 'pdf' ? 'text/html' :
                        backendFormat === 'docx' ? 'application/msword' :
                        'text/plain';

        console.log(`Creating blob with MIME type: ${mimeType}`);
        const blob = new Blob([response.data], { type: mimeType });
        console.log('Blob created:', blob);
        console.log('Blob size:', blob.size);

        const url = window.URL.createObjectURL(blob);
        console.log('Blob URL created:', url);

        const link = document.createElement('a');
        link.href = url;
        const baseFilename = note.value.title.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const filename = format === 'pdf' ? `${baseFilename}_for_pdf.${fileExtension}` : `${baseFilename}_export.${fileExtension}`;
        link.download = filename;
        console.log('Download filename:', filename);

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        console.log(`Note exported as ${format} successfully`);
        alert(`File exported successfully! Check your downloads folder for "${filename}"`);
      } catch (error) {
        console.error('Export error:', error);
        console.error('Error details:', error.response || error.message);
        alert(`Failed to export note as ${format.toUpperCase()}. Please try again. Error: ${error.message}`);
      }
    };

    const generateSummary = async () => {
      if (!note.value || generatingSummary.value) return;

      generatingSummary.value = true;
      try {
        console.log('Starting summary generation for note:', note.value.id);
        console.log('Making API call to createSummary...');
        const response = await api.createSummary(note.value.id, { length: 'auto' });
        console.log('Summary API response received');
        console.log('Response status:', response?.status);
        console.log('Response data:', response?.data);
        console.log('Response data type:', typeof response?.data);
        console.log('Response data success:', response?.data?.success);

        if (response.data.success) {
          console.log('Summary created successfully, refreshing note data...');
          // Refresh the note data to get the updated summary
          const noteResponse = await api.getNote(note.value.id);
          console.log('Note refresh response:', noteResponse.data);

          if (noteResponse.data.success && noteResponse.data.data) {
            const newSummary = noteResponse.data.data.summary;
            console.log('New summary from server:', newSummary);
            note.value.summary = newSummary;
            console.log('Updated note summary in UI:', note.value.summary);
          } else {
            console.error('Failed to refresh note data:', noteResponse.data);
          }
          alert('Summary generated successfully!');
        } else {
          console.error('Summary generation failed:', response.data);
          const errorMessage = response.data?.error || response.data?.message || 'Unknown error';
          alert('Failed to generate summary: ' + errorMessage);
        }
      } catch (error) {
        console.error('Error generating summary:', error);

        // Handle different types of errors
        let errorMessage = 'Failed to generate summary. Please try again.';

        if (error.response) {
          // Server responded with error status
          console.error('Server error response:', error.response.data);
          const serverError = error.response.data?.error || error.response.data?.message;
          if (serverError) {
            errorMessage = 'Failed to generate summary: ' + serverError;
          } else if (error.response.status === 401) {
            errorMessage = 'Authentication failed. Please log in again.';
          } else if (error.response.status === 403) {
            errorMessage = 'Access denied. You may not have permission to modify this note.';
          } else if (error.response.status >= 500) {
            errorMessage = 'Server error. Please try again later.';
          }
        } else if (error.request) {
          // Request was made but no response received
          console.error('Network error - no response received');
          errorMessage = 'Network error. Please check your connection and try again.';
        } else {
          // Something else happened
          console.error('Request setup error:', error.message);
          errorMessage = 'Request error: ' + error.message;
        }

        alert(errorMessage);
      } finally {
        generatingSummary.value = false;
      }
    };

    // =====================================
    // STUDY SESSION FUNCTIONS
    // =====================================


    /**
     * End the current study session
     */
    const endStudySession = async () => {
      const result = await endSession({
        notesStudied: 1, // This note was studied
        focusLevel: 'medium'
      });

      if (result.success) {
        showSuccess('Study session ended!', `You studied for ${formattedElapsedTime.value}`);
      } else {
        showError('Failed to end study session', result.error);
      }
    };

    /**
     * Handle note scrolling to update activity
     */
    const onNoteScroll = () => {
      if (isTracking.value) {
        setCurrentActivity('reading_notes');
      }
    };


    return {
      note,
      isLoading,
      error,
      showExportOptions,
      generatingSummary,
      isGeneratingSummary,
      sidebarOpen,
      themeClasses,
      fontSizeClasses,
      sidebarVisible,
      showUserMenu,
      editNote,
      exportNote,
      generateSummary,
      toggleSidebar,
      toggleUserMenu,
      closeUserMenu,
      openProfileModal,
      logout,
      handleImageError,
      handleImageLoad,
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      closeNotifications,
      markAsRead,
      markAllAsRead,
      showSuccess,
      showInfo,
      showWarning,
      showError,
      user,
      loadUserProfile,
      getProfilePictureUrl,
      // Study time tracking
      currentSession,
      isTracking,
      formattedElapsedTime,
      isActiveSession,
      endStudySession,
      onNoteScroll
    };
  }
}
</script>