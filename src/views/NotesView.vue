<template>
  <Header @open-profile-modal="openProfileModal">

      <!-- Notes Main Content -->
      <main class="flex-1 p-4 md:p-6 transition-all duration-300 ease-in-out min-h-screen"
            :style="{ backgroundColor: store.getters['app/getCurrentTheme'] === 'dark' ? '#111827' : '#ffffff' }">
        <div class="flex justify-between items-center mb-6">
          <div class="flex items-center space-x-4">
            <h1 :class="[
              'font-bold',
              fontSizeClasses.heading,
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
            ]">My Notes</h1>
          </div>
          <div class="flex space-x-3">
            <div class="relative">
              <input
                type="text"
                placeholder="Search notes..."
                :class="themeClasses.input"
                class="pl-10 pr-4 py-2 rounded-md focus:outline-none"
              />
              <font-awesome-icon :icon="['fas', 'search']" :class="themeClasses.secondaryText" class="absolute left-3 top-3" />
            </div>
            <button
              @click="refreshNotes()"
              :disabled="loadingNotes"
              :class="themeClasses.button"
              class="flex items-center space-x-1 px-3 py-1 rounded-md transition disabled:opacity-50"
            >
              <font-awesome-icon
                :icon="['fas', loadingNotes ? 'spinner' : 'sync-alt']"
                :class="loadingNotes ? 'animate-spin' : ''"
                class="text-sm"
              />
            </button>
            <button @click="openCamera" :class="themeClasses.buttonPrimary" class="px-4 py-2 rounded-md transition">
              <font-awesome-icon :icon="['fas', 'camera']" class="mr-2" /> Scan New
            </button>
          </div>
        </div>

        <!-- Notes Filter -->
        <div class="flex mb-6 space-x-2">
          <button
            @click="activeFilter = 'all'"
            :class="[
              'px-3 py-1 rounded-md transition-colors',
              activeFilter === 'all' ? themeClasses.buttonPrimary : themeClasses.button
            ]"
          >
            All Notes
          </button>
          <button
            @click="activeFilter = 'recent'"
            :class="[
              'px-3 py-1 rounded-md transition-colors flex items-center space-x-1',
              activeFilter === 'recent' ? themeClasses.buttonPrimary : themeClasses.button
            ]"
          >
            <font-awesome-icon :icon="['fas', 'clock']" class="text-xs" />
            <span>Recent</span>
          </button>
          <button
            @click="activeFilter = 'favorites'"
            :class="[
              'px-3 py-1 rounded-md transition-colors flex items-center space-x-1',
              activeFilter === 'favorites' ? themeClasses.buttonPrimary : themeClasses.button
            ]"
          >
            <font-awesome-icon :icon="['fas', 'star']" class="text-xs text-yellow-500" />
            <span>Favorites</span>
          </button>
        </div>

        <!-- Error State -->
        <div v-if="notesError" :class="[
          'rounded-lg p-6 text-center',
          store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-red-800' : 'bg-red-50 border border-red-200'
        ]">
          <div class="mb-4">
            <font-awesome-icon :icon="['fas', 'times']" class="text-4xl text-red-400" />
          </div>
          <h3 :class="[
            'text-xl font-medium mb-2',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
          ]">Unable to Load Notes</h3>
          <p :class="[
            'mb-4',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-red-300' : 'text-red-700'
          ]">{{ notesError || 'An error occurred while loading your notes.' }}</p>
          <div class="flex justify-center space-x-3">
            <button @click="refreshNotes()" class="px-4 py-2 bg-red-600 rounded-md hover:bg-red-700 transition">
              <font-awesome-icon :icon="['fas', 'redo']" class="mr-2" />
              Try Again
            </button>
            <button @click="clearError()" :class="[
              'px-4 py-2 rounded-md transition',
              store.getters['app/getCurrentTheme'] === 'dark'
                ? 'bg-gray-700 hover:bg-gray-600'
                : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
            ]">
              Dismiss
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loadingNotes && notes.length === 0" :class="[
          'text-center py-8',
          store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
        ]">
          <font-awesome-icon :icon="['fas', 'spinner']" class="animate-spin text-3xl mb-3" />
          <p class="text-lg">Loading your notes...</p>
        </div>

        <!-- Notes Grid -->
        <div v-else-if="notes.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
          <div
            v-for="note in filteredNotes"
            :key="`${note.id}-${note.isFavorite}-${note._lastUpdated || 0}`"
            :class="themeClasses.card"
            class="rounded-lg overflow-hidden transition cursor-pointer relative"
            @click="viewNote(note.id)"
          >
            <div class="p-4">
              <div class="flex justify-between items-start mb-2">
                <h3 :class="themeClasses.text" class="font-medium">{{ note.title }}</h3>
                <div class="flex space-x-2">
                  <button @click.stop="toggleFavorite(note.id)" :class="themeClasses.secondaryText" class="hover:text-yellow-500 transition-colors">
                    <font-awesome-icon :icon="['fas', 'star']" :class="note.isFavorite ? 'text-yellow-500' : themeClasses.secondaryText + ' opacity-50'" />
                  </button>
                  <div class="relative">
                    <button @click.stop="showNoteMenu(note.id)" :class="[
                      themeClasses.secondaryText,
                      'p-2 rounded-md transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center',
                      store.getters['app/getCurrentTheme'] === 'dark'
                        ? 'hover:text-white hover:bg-gray-700'
                        : 'hover:text-gray-900 hover:bg-gray-200'
                    ]">
                      <font-awesome-icon :icon="['fas', 'ellipsis-v']" />
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div
                      v-if="activeMenu === note.id"
                      :class="themeClasses.card"
                      class="absolute right-0 top-full mt-1 w-32 rounded-md shadow-lg z-10"
                      @click.stop
                    >
                      <button
                        @click="editNote(note.id)"
                        :class="themeClasses.hover"
                        class="w-full text-left px-3 py-2 text-sm rounded-t-md flex items-center space-x-2"
                      >
                        <font-awesome-icon :icon="['fas', 'edit']" class="text-xs" />
                        <span :class="themeClasses.text">Edit</span>
                      </button>
                      <button
                        @click="duplicateNote(note.id)"
                        :class="themeClasses.hover"
                        class="w-full text-left px-3 py-2 text-sm flex items-center space-x-2"
                      >
                        <font-awesome-icon :icon="['fas', 'copy']" class="text-xs" />
                        <span :class="themeClasses.text">Duplicate</span>
                      </button>
                      <button
                        @click="confirmDelete(note.id)"
                        class="w-full text-left px-3 py-2 text-sm text-red-400 hover:text-white hover:bg-red-600 rounded-b-md flex items-center space-x-2"
                      >
                        <font-awesome-icon :icon="['fas', 'trash']" class="text-xs" />
                        <span>Delete</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              <p :class="themeClasses.secondaryText" class="text-sm mb-2">
                {{ activeFilter === 'recent' && (Date.now() - note.timestamp) < 604800000 ? getTimeAgo(note.createdAt) : note.date }}
              </p>
              <p :class="themeClasses.secondaryText" class="text-sm line-clamp-3 mb-3">{{ note.original_text }}</p>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="(tag, tagIndex) in note.tags"
                  :key="tagIndex"
                  :class="themeClasses.button"
                  class="px-2 py-1 rounded-full text-xs"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else :class="themeClasses.card" class="rounded-lg p-8 text-center">
          <div class="mb-4">
            <font-awesome-icon :icon="['fas', 'book']" :class="themeClasses.secondaryText" class="text-4xl" />
          </div>
          <h3 :class="[themeClasses.text, fontSizeClasses.heading, 'font-medium mb-2']">No Notes Yet</h3>
          <p :class="themeClasses.secondaryText" class="mb-4">Start by scanning your study materials or creating a new note.</p>
          <div class="flex justify-center space-x-4">
            <button @click="openCamera" :class="themeClasses.buttonPrimary" class="px-4 py-2 rounded-md transition">
              <font-awesome-icon :icon="['fas', 'camera']" class="mr-2" /> Scan Notes
            </button>
            <button @click="createNewNote" :class="themeClasses.button" class="px-4 py-2 rounded-md transition">
              <font-awesome-icon :icon="['fas', 'plus']" class="mr-2" /> Create New
            </button>
          </div>
        </div>
      </main>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click="closeDeleteModal"
    >
      <div
        :class="[
          'rounded-lg p-6 w-full max-w-sm mx-4',
          store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-200'
        ]"
        @click.stop
      >
        <div class="flex items-center mb-4">
          <font-awesome-icon :icon="['fas', 'times']" class="text-red-400 text-xl mr-3" />
          <h3 :class="[
            'text-lg font-medium',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
          ]">Delete Note</h3>
        </div>
        <p :class="[
          'mb-6',
          store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
        ]">
          Are you sure you want to delete this note? This action cannot be undone.
        </p>
        <div class="flex justify-end space-x-3">
          <button
            @click="closeDeleteModal"
            :class="[
              'px-4 py-2 rounded-md transition',
              store.getters['app/getCurrentTheme'] === 'dark'
                ? 'bg-gray-700 hover:bg-gray-600'
                : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
            ]"
          >
            Cancel
          </button>
          <button
            @click="deleteNote"
            class="px-4 py-2 bg-red-600 rounded-md hover:bg-red-700 transition"
          >
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Profile Modal -->
    <div v-if="showProfileModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div :class="[
        'rounded-lg p-6 w-full max-w-sm mx-4',
        store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-200'
      ]">
        <div class="flex items-center mb-4">
          <font-awesome-icon :icon="['fas', 'user']" class="text-blue-400 text-xl mr-3" />
          <h3 :class="[
            'text-lg font-medium',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
          ]">User Profile</h3>
        </div>

        <!-- Profile Picture Section -->
        <div class="flex flex-col items-center mb-6">
          <div :class="[
            'w-24 h-24 rounded-full overflow-hidden mb-3',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-600' : 'bg-gray-200'
          ]">
            <img
              v-if="user && user.profilePicture"
              :key="user.profilePicture"
              :src="getProfilePictureUrl(user.profilePicture)"
              alt="Profile"
              class="w-full h-full object-cover"
              @error="handleImageError"
              @load="handleImageLoad"
            />
            <div v-else :class="[
              'w-full h-full flex items-center justify-center',
              store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-600' : 'bg-gray-200'
            ]">
              <font-awesome-icon :icon="['fas', 'user']" :class="[
                'text-2xl',
                store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-600'
              ]" />
            </div>
          </div>
          <p :class="[
            'text-sm mb-2',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
          ]">Profile picture can be changed in Settings</p>
        </div>

        <div class="space-y-4">
          <div>
            <label :class="[
              'block text-sm font-medium mb-2',
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
            ]">Full Name</label>
            <div :class="[
              'w-full px-3 py-2 rounded-md',
              store.getters['app/getCurrentTheme'] === 'dark'
                ? 'bg-gray-700 border border-gray-600 text-gray-300'
                : 'bg-gray-100 border border-gray-300 text-gray-900'
            ]">
              {{ user?.firstName && user?.lastName ? `${user.firstName} ${user.lastName}` : (user?.name || 'User') }}
            </div>
          </div>
          <div>
            <label :class="[
              'block text-sm font-medium mb-2',
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
            ]">Email</label>
            <div :class="[
              'w-full px-3 py-2 rounded-md',
              store.getters['app/getCurrentTheme'] === 'dark'
                ? 'bg-gray-700 border border-gray-600 text-gray-300'
                : 'bg-gray-100 border border-gray-300 text-gray-900'
            ]">
              {{ user?.email || 'user@example.com' }}
            </div>
          </div>
          <div>
            <label :class="[
              'block text-sm font-medium mb-2',
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
            ]">Member Since</label>
            <div :class="[
              'w-full px-3 py-2 rounded-md',
              store.getters['app/getCurrentTheme'] === 'dark'
                ? 'bg-gray-700 border border-gray-600 text-gray-300'
                : 'bg-gray-100 border border-gray-300 text-gray-900'
            ]">
              {{ user?.memberSince || new Date().toLocaleDateString() }}
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-3 mt-6">
          <button @click="closeProfileModal" :class="[
            'px-4 py-2 rounded-md transition',
            store.getters['app/getCurrentTheme'] === 'dark'
              ? 'bg-gray-700 hover:bg-gray-600'
              : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
          ]">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Backdrop to close menu when clicking outside -->
    <div
      v-if="activeMenu"
      class="fixed inset-0 z-0"
      @click="closeMenu"
    ></div>
</Header>
</template>

<script>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useStore } from 'vuex';
import { useNotifications } from '@/composables/useNotifications';
import { useUserProfile } from '@/composables/useUserProfile';
import api from '@/services/api';
import Header from '@/components/Header.vue';

export default {
  name: 'NotesView',
  components: {
    Header
  },
  setup() {
    const store = useStore();
    const router = useRouter();
    const route = useRoute();

    // Use the shared notifications composable
    const {
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      closeNotifications,
      markAsRead,
      markAllAsRead,
      showSuccess,
      showWarning
    } = useNotifications();

    // =====================================
    // SIMPLE DATA MANAGEMENT
    // =====================================

    // Use the user profile composable
    const {
      user: userProfile,
      loading: loadingUser,
      loadUserProfile,
      getProfilePictureUrl
    } = useUserProfile();

    // Simple reactive data
    const notesResponse = ref(null);
    const loadingNotes = ref(false);
    const isConnected = ref(true);
    const connectionStatus = ref('connected');
    const lastSync = ref(new Date());

    // =====================================
    // UI STATE
    // =====================================
    const activeFilter = ref('all');
    const activeMenu = ref(null);
    const showDeleteModal = ref(false);
    const noteToDelete = ref(null);
    const ocrText = ref('');
    const notesError = ref('');
    const showUserMenu = ref(false);
    const showProfileModal = ref(false);

    // =====================================
    // COMPUTED PROPERTIES
    // =====================================

    // Process notes data
    const notes = computed(() => {
      if (!notesResponse.value?.data) {
        console.log('🔄 Notes computed: No data in notesResponse');
        return [];
      }

      console.log('🔄 Notes computed: Processing', notesResponse.value.data.length, 'notes from database');

      const processedNotes = notesResponse.value.data.map(note => {
        const rawFavorite = note.is_favorite;
        const processedFavorite = note.is_favorite === null || note.is_favorite === undefined
          ? false
          : Boolean(note.is_favorite && note.is_favorite !== '0' && note.is_favorite !== 0);

        console.log('🔄 Notes processing - Note ID:', note.id, 'Raw is_favorite:', rawFavorite, '(type:', typeof rawFavorite, ') -> Processed:', processedFavorite);

        return {
          id: note.id,
          title: note.title || 'Untitled Note',
          original_text: note.original_text || '',
          date: new Date(note.created_at).toLocaleDateString(),
          createdAt: new Date(note.created_at), // Keep original Date object for sorting
          timestamp: new Date(note.created_at).getTime(), // Timestamp for precise sorting
          isFavorite: processedFavorite, // Properly convert to boolean
          _lastUpdated: note._lastUpdated || 0, // Preserve the update timestamp
          tags: [] // You can add tags logic here
        };
      });

      console.log('🔄 Notes computed: Final processed notes:', processedNotes.map(n => ({ id: n.id, isFavorite: n.isFavorite })));
      return processedNotes;
    });

    // Use user from composable
    const user = userProfile;

    // Sidebar visibility from store
    const sidebarVisible = computed(() => store.getters['app/getSidebarVisible']);

    // Use global theme classes from store
    const themeClasses = computed(() => {
      return store.getters['app/getThemeClasses'];
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


    // Format date as "time ago" for recent notes
    const getTimeAgo = (date) => {
      const now = new Date();
      const diffInSeconds = Math.floor((now - date) / 1000);

      if (diffInSeconds < 60) return 'Just now';
      if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
      if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
      if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`;

      return date.toLocaleDateString();
    };

    // Handle image loading errors
    const handleImageError = (event) => {
      const imgSrc = event.target.src;
      console.error('Profile picture failed to load:', imgSrc);
      console.error('Image naturalWidth:', event.target.naturalWidth);
      console.error('Image naturalHeight:', event.target.naturalHeight);
      console.error('User profile picture path:', user?.profilePicture || 'null');
    };

    // Handle successful image loading
    const handleImageLoad = (event) => {
      const imgSrc = event.target.src;
      console.log('Profile picture loaded successfully:', imgSrc);
      console.log('Image dimensions:', event.target.naturalWidth, 'x', event.target.naturalHeight);
    };

    const filteredNotes = computed(() => {
      console.log('🔄 filteredNotes computed: Recalculating with activeFilter:', activeFilter.value);
      console.log('🔄 filteredNotes computed: notes.value length:', notes.value.length);

      let result;
      if (activeFilter.value === 'all') {
        result = notes.value;
        console.log('🔄 filteredNotes computed: Returning all notes');
      } else if (activeFilter.value === 'recent') {
        // Sort by timestamp (most recent first) for better precision
        const sorted = [...notes.value].sort((a, b) => b.timestamp - a.timestamp);
        result = sorted;
        console.log('🔄 filteredNotes computed: Returning sorted recent notes');
      } else if (activeFilter.value === 'favorites') {
        const favorites = notes.value.filter(note => note.isFavorite);
        result = favorites;
        console.log('🔄 filteredNotes computed: Returning favorites, count:', favorites.length);
      } else {
        result = notes.value;
        console.log('🔄 filteredNotes computed: Returning default (all notes)');
      }

      console.log('🔄 filteredNotes computed: Final result length:', result.length);
      return result;
    });

    // =====================================
    // SIMPLE API FUNCTIONS
    // =====================================

    // Fetch notes from API
    const fetchNotes = async () => {
      try {
        loadingNotes.value = true;

        // Ensure we have user authentication
        const userData = localStorage.getItem('user');
        if (!userData) {
          console.error('❌ No user data found in localStorage');
          notesError.value = 'Please log in to view your notes.';
          loadingNotes.value = false;
          return;
        }

        console.log('🔍 Fetching notes for user:', userData);

        // Parse user data to get user ID
        let user;
        try {
          user = JSON.parse(userData);
          console.log('✅ Parsed user data:', user);
          console.log('✅ User ID:', user.id);
        } catch (parseError) {
          console.error('❌ Error parsing user data:', parseError);
          notesError.value = 'Invalid user session. Please log in again.';
          loadingNotes.value = false;
          return;
        }

        console.log('🚀 Making API call to getNotes...');
        const response = await api.getNotes();
        console.log('📡 API call completed. Response status:', response.status);
        console.log('📡 Response headers:', response.headers);
        console.log('📡 Response data type:', typeof response.data);
        console.log('📡 Response data preview:', response.data?.substring ? response.data.substring(0, 200) : response.data);

        if (response.data && response.data.success) {
          console.log('✅ Notes loaded successfully:', response.data.data?.length || 0, 'notes');
        } else {
          console.log('❌ Notes API returned error:', response.data?.error);
        }

        if (response.data && response.data.success) {
          notesResponse.value = response.data;
          notesError.value = ''; // Clear any previous errors
          console.log('✅ Notes loaded successfully:', response.data.data?.length || 0, 'notes');
        } else {
          const errorMessage = response.data?.error || response.data?.message || 'Failed to load notes.';
          notesError.value = errorMessage;
          console.error('❌ Notes API error:', errorMessage);
          console.error('❌ Full response:', response.data);
        }
      } catch (error) {
        console.error('❌ Error fetching notes:', error);

        // Provide more specific error messages based on error type
        let errorMessage = 'Failed to load notes. Please check your connection and try again.';

        if (error.code === 'NETWORK_ERROR' || error.message?.includes('Network Error')) {
          errorMessage = 'Network connection error. Please check your internet connection.';
        } else if (error.code === 'TIMEOUT' || error.message?.includes('timeout')) {
          errorMessage = 'Request timed out. Please try again.';
        } else if (error.response?.status === 401) {
          errorMessage = 'Authentication failed. Please log in again.';
        } else if (error.response?.status === 403) {
          errorMessage = 'Access denied. You may not have permission to view these notes.';
        } else if (error.response?.status === 404) {
          errorMessage = 'Notes service not found. Please contact support.';
        } else if (error.response?.status >= 500) {
          errorMessage = 'Server error. Please try again later.';
        }

        notesError.value = errorMessage;
      } finally {
        loadingNotes.value = false;
      }
    };

    // Fetch user profile using composable
    const fetchUser = async () => {
      await loadUserProfile();
    };

    // Refresh notes
    const refreshNotes = async () => {
      await fetchNotes();
    };

    // Refresh user profile using composable
    const refreshUser = async () => {
      await loadUserProfile();
    };

    // Clear error
    const clearError = () => {
      notesError.value = '';
    };

    // =====================================
    // LIFECYCLE HOOKS
    // =====================================

    onMounted(async () => {
      // Fetch initial data
      await Promise.all([
        fetchNotes(),
        fetchUser()
      ]);
    });

    // Watch for route changes to refresh notes when navigating back to notes page
    watch(() => route.fullPath, async (newPath) => {
      if (newPath.startsWith('/notes')) {
        console.log('Navigated to notes page, checking if refresh needed...');

        // Check if we need to refresh (either from query param or just navigating to notes)
        const shouldRefresh = route.query.refresh === 'true' || newPath === '/notes';

        if (shouldRefresh) {
          console.log('Refreshing notes...');
          await fetchNotes();

          // Show success notification if this was a refresh after saving
          if (route.query.refresh) {
            showSuccess('Notes updated', 'Your notes have been refreshed.');
          }

          // Clean up the refresh query parameter
          if (route.query.refresh) {
            router.replace('/notes');
          }
        }
      }
    });

    const viewNote = (noteId) => {
      closeMenu();
      router.push(`/notes/${noteId}`);
    };

    const toggleFavorite = async (noteId) => {
      const noteIndex = notes.value.findIndex(n => n.id === noteId);

      if (noteIndex !== -1) {
        const note = notes.value[noteIndex];
        const newFavoriteState = !note.isFavorite;

        // Optimistically update the local state - ensure Vue detects the change
        // Create a completely new data structure to ensure Vue reactivity
        const updatedNotes = notesResponse.value.data.map((n, index) => {
          if (index === noteIndex) {
            // Create a completely new object for the updated note
            return {
              ...n, // Spread the original note properties
              is_favorite: newFavoriteState,
              // Force Vue to see this as a new object by adding a timestamp
              _lastUpdated: Date.now()
            };
          }
          // Return unchanged notes as new objects to avoid shared references
          return { ...n };
        });

        // Replace the entire notesResponse object to trigger reactivity
        console.log('🔄 TOGGLE FAVORITE: About to update notesResponse');
        console.log('🔄 TOGGLE FAVORITE: Old notesResponse.data length:', notesResponse.value.data.length);
        console.log('🔄 TOGGLE FAVORITE: Updated notes length:', updatedNotes.length);

        notesResponse.value = {
          ...notesResponse.value,
          data: updatedNotes
        };

        console.log('🔄 TOGGLE FAVORITE: notesResponse updated, new data length:', notesResponse.value.data.length);

        // Force DOM update with nextTick
        await nextTick();
        console.log('🔄 TOGGLE FAVORITE: nextTick completed');

        // Verify the change is reflected
        const updatedNoteInNotes = notes.value.find(n => n.id === noteId);
        console.log('🔄 TOGGLE FAVORITE: Updated note in notes computed:', updatedNoteInNotes?.isFavorite);

        try {
          // Update the favorite status on the server
          await api.updateNote(noteId, {
            title: note.title,
            text: note.original_text,
            is_favorite: newFavoriteState
          });
        } catch (error) {
          console.error('Error updating favorite status:', error);
          // Revert the local change if server update fails
          const revertedNotes = notesResponse.value.data.map((n, index) => {
            if (index === noteIndex) {
              // Create a completely new object for the reverted note
              return {
                ...n, // Spread the original note properties
                is_favorite: !newFavoriteState,
                // Force Vue to see this as a new object by adding a timestamp
                _lastUpdated: Date.now()
              };
            }
            // Return unchanged notes as new objects to avoid shared references
            return { ...n };
          });

          // Replace the entire notesResponse object to trigger reactivity
          notesResponse.value = {
            ...notesResponse.value,
            data: revertedNotes
          };

          // Force DOM update with nextTick
          await nextTick();
          showWarning('Update failed', 'Failed to update favorite status. Please try again.');
        }
      }
    };

    const showNoteMenu = (noteId) => {
      activeMenu.value = activeMenu.value === noteId ? null : noteId;
    };

    const closeMenu = () => {
      activeMenu.value = null;
    };

    const editNote = (noteId) => {
      closeMenu();
      router.push(`/notes/edit/${noteId}`);
    };

    const duplicateNote = async (noteId) => {
      closeMenu();
      const originalNote = notes.value.find(n => n.id === noteId);
      if (originalNote) {
        const duplicatedNote = {
          title: `${originalNote.title} (Copy)`,
          text: originalNote.original_text
        };

        try {
          const response = await api.createNote(duplicatedNote);
          if (response.data.success) {
            await fetchNotes(); // Refresh notes list
            showSuccess('Note duplicated', 'The note has been successfully duplicated.');
          } else {
            throw new Error(response.data.error || 'Failed to duplicate note');
          }
        } catch (error) {
          console.error('Error duplicating note:', error);
          showWarning('Duplicate failed', 'Failed to duplicate the note. Please try again.');
        }
      }
    };

    const confirmDelete = (noteId) => {
      closeMenu();
      noteToDelete.value = noteId;
      showDeleteModal.value = true;
    };

    const closeDeleteModal = () => {
      showDeleteModal.value = false;
      noteToDelete.value = null;
    };

    const deleteNote = async () => {
      if (noteToDelete.value) {
        try {
          await api.deleteNote(noteToDelete.value);
          await fetchNotes(); // Refresh notes list
          showSuccess('Note deleted', 'The note has been successfully deleted.');
        } catch (error) {
          console.error('Error deleting note:', error);
          showWarning('Delete failed', 'Failed to delete the note. Please try again.');
        }
      }
      closeDeleteModal();
    };

    const openCamera = () => {
      console.log('Opening camera...');
      // In a real app, this would open the camera
      router.push('/notes/edit');
    };

    const createNewNote = () => {
      router.push('/notes/edit');
    };

    // Notes are now handled by the real-time system
    // The getNotes function is replaced by the useRealtime composable


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
      showProfileModal.value = true;
      showUserMenu.value = false; // Close the dropdown menu
    };

    /**
     * Close user profile modal
     */
    const closeProfileModal = () => {
      showProfileModal.value = false;
    };

    /**
     * Logout user and redirect to login
     */
    const logout = async () => {
      try {
        router.push('/login');
      } catch (error) {
        console.error('Error logging out:', error);
      }
    };

    return {
      // Data
      notes,
      user,
      sidebarVisible,

      // UI State
      activeFilter,
      activeMenu,
      showDeleteModal,
      noteToDelete,
      showUserMenu,
      showProfileModal,
      filteredNotes,
      notesError,

      // Loading states
      loadingNotes,
      loadingUser,

      // Connection status
      isConnected,
      connectionStatus,
      lastSync,

      // Functions
      toggleSidebar,
      viewNote,
      toggleFavorite,
      showNoteMenu,
      closeMenu,
      editNote,
      duplicateNote,
      confirmDelete,
      closeDeleteModal,
      deleteNote,
      openCamera,
      createNewNote,
      getProfilePictureUrl,
      handleImageError,
      handleImageLoad,
      getTimeAgo,

      // User menu functions
      toggleUserMenu,
      closeUserMenu,
      openProfileModal,
      closeProfileModal,
      logout,

      // Real-time functions
      refreshNotes,
      refreshUser,
      clearError,

      // Notification functions
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      closeNotifications,
      markAsRead,
      markAllAsRead,

      // User profile composable functions
      loadUserProfile,

      // OCR data
      ocrText,

      // Theme classes
      themeClasses,
      fontSizeClasses,

      // Store for theme access
      store
    };
  }
}
</script>
