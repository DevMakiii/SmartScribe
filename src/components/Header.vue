<template>
  <div :class="themeClasses.main" class="min-h-screen flex flex-col">
    <header :class="themeClasses.header" class="p-4 flex justify-between items-center">
      <div class="flex items-center space-x-3">
        <!-- Classic Sidebar Toggle Button -->
        <button @click="toggleSidebar"
                class="group relative flex flex-col items-center justify-center w-12 h-12 rounded-xl transition-all duration-300 ease-out transform hover:scale-105 active:scale-95 overflow-hidden shadow-lg hover:shadow-xl"
                :class="store.getters['app/getCurrentTheme'] === 'dark'
                  ? 'bg-gradient-to-br from-slate-800 to-gray-900 hover:from-blue-600 hover:to-blue-700 text-gray-300 hover:text-white hover:shadow-blue-500/25'
                  : 'bg-gradient-to-br from-slate-200 to-slate-300 hover:from-blue-600 hover:to-blue-700 text-slate-700 hover:text-white hover:shadow-blue-500/25'"
                :title="sidebarVisible ? 'Hide sidebar' : 'Show sidebar'">

          <!-- Hamburger Menu Lines -->
          <div class="flex flex-col space-y-1 relative z-10">
            <!-- Top line -->
            <div class="w-6 h-0.5 bg-current transition-all duration-300 ease-out group-hover:w-7 group-hover:bg-white rounded-full"></div>
            <!-- Middle line -->
            <div class="w-5 h-0.5 bg-current transition-all duration-300 ease-out group-hover:w-6 group-hover:bg-white rounded-full"></div>
            <!-- Bottom line -->
            <div class="w-4 h-0.5 bg-current transition-all duration-300 ease-out group-hover:w-5 group-hover:bg-white rounded-full"></div>
          </div>

          <!-- Subtle background glow -->
          <div class="absolute inset-0 rounded-xl bg-gradient-to-br from-blue-500/0 to-blue-600/0 group-hover:from-blue-500/10 group-hover:to-blue-600/10 transition-all duration-300"></div>

          <!-- Tooltip -->
          <div class="absolute -bottom-12 left-1/2 transform -translate-x-1/2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap border border-gray-700">
            {{ sidebarVisible ? 'Hide Menu' : 'Show Menu' }}
            <div class="absolute -top-1 left-1/2 transform -translate-x-1/2 w-2 h-2 bg-gray-900 border-l border-t border-gray-700 rotate-45"></div>
          </div>
        </button>
        <div :class="themeClasses.text" class="text-lg md:text-xl font-bold">SmartScribe</div>
      </div>
      <div class="flex items-center space-x-2 md:space-x-4">

        <div class="relative">
          <button @click="toggleNotifications" class="relative transition-colors duration-300"
                  :class="store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'text-gray-400 hover:text-white'
                    : 'text-slate-500 hover:text-slate-900'">
            <font-awesome-icon :icon="['fas', 'bell']" />
            <span v-if="unreadNotifications > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
              {{ unreadNotifications }}
            </span>
          </button>
          <div v-if="showNotifications" class="absolute right-0 mt-2 w-80 bg-gray-800 rounded-md shadow-lg z-50 max-h-96 overflow-y-auto">
            <div class="p-4 border-b border-gray-700">
              <h3 class="text-lg font-semibold">Notifications</h3>
            </div>
            <div v-if="notifications.length > 0">
              <div v-for="(notification, index) in notifications" :key="notification.id || index"
                   class="p-4 border-b border-gray-700 hover:bg-gray-700 transition-colors"
                   :class="{
                     'bg-gray-700': !notification.read,
                     'cursor-pointer': !notification.read
                   }"
                   @click="markAsRead(notification)">
                <div class="flex items-start space-x-3">
                  <div class="flex-shrink-0 mt-1">
                    <div :class="[
                      'w-8 h-8 rounded-full flex items-center justify-center',
                      notification.bgColor
                    ]">
                      <font-awesome-icon :icon="notification.icon" class="text-white text-sm" />
                    </div>
                  </div>
                  <div class="flex-grow">
                    <div class="flex items-start justify-between">
                      <div class="flex-grow">
                        <p class="text-sm font-medium text-white">{{ notification.title }}</p>
                        <p class="text-xs text-gray-300 mt-1">{{ notification.message }}</p>
                        <p class="text-xs text-gray-500 mt-2">{{ notification.time }}</p>
                      </div>
                      <div class="flex items-center space-x-2 ml-2">
                        <div v-if="!notification.read" class="flex-shrink-0">
                          <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                        </div>
                        <button v-if="notification.persistent"
                                @click.stop="removeNotification(notification.id)"
                                class="text-gray-400 hover:text-red-400 text-xs">
                          <font-awesome-icon :icon="['fas', 'times']" />
                        </button>
                      </div>
                    </div>

                    <!-- Notification Actions -->
                    <div v-if="notification.actions && notification.actions.length > 0" class="mt-3 flex space-x-2">
                      <button v-for="(action, actionIndex) in notification.actions" :key="actionIndex"
                              @click.stop="executeAction(notification, action)"
                              class="px-2 py-1 text-xs rounded transition-colors"
                              :class="{
                                'bg-blue-600 text-white hover:bg-blue-700': action.action === 'navigate',
                                'bg-gray-600 text-white hover:bg-gray-700': action.action === 'dismiss',
                                'bg-green-600 text-white hover:bg-green-700': action.action === 'callback'
                              }">
                        {{ action.label }}
                      </button>
                    </div>

                    <!-- Priority Indicator -->
                    <div v-if="notification.priority === 'urgent'" class="mt-2 flex items-center">
                      <div class="w-2 h-2 bg-red-500 rounded-full mr-2 animate-pulse"></div>
                      <span class="text-xs text-red-400 font-medium">URGENT</span>
                    </div>
                    <div v-else-if="notification.priority === 'high'" class="mt-2 flex items-center">
                      <div class="w-2 h-2 bg-orange-500 rounded-full mr-2"></div>
                      <span class="text-xs text-orange-400 font-medium">HIGH PRIORITY</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="p-4 text-center text-gray-400">
              <font-awesome-icon :icon="['fas', 'bell-slash']" class="text-2xl mb-2" />
              <p>No notifications yet</p>
            </div>
            <div v-if="notifications.length > 0" class="p-3 border-t border-gray-700">
              <button @click="markAllAsRead" class="text-sm text-blue-400 hover:text-blue-300">
                Mark all as read
              </button>
            </div>
          </div>
          <!-- Backdrop to close notifications when clicking outside -->
          <div v-if="showNotifications" class="fixed inset-0 z-0" @click="closeNotifications"></div>
        </div>
        <div class="relative">
          <button @click="toggleUserMenu" class="flex items-center space-x-2 transition-colors duration-300">
            <div class="w-8 h-8 rounded-full overflow-hidden"
                 :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-600' : 'bg-slate-300'">
              <img
                v-if="user && user.profilePicture"
                :key="user.profilePicture"
                :src="getProfilePictureUrl(user.profilePicture)"
                alt="Profile"
                class="w-full h-full object-cover"
                @error="handleImageError"
                @load="handleImageLoad"
              />
              <div v-else class="w-full h-full flex items-center justify-center"
                   :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-600' : 'bg-slate-300'">
                <font-awesome-icon :icon="['fas', 'user']"
                                   :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-slate-700'"
                                   class="text-sm" />
              </div>
            </div>
            <span class="text-sm transition-colors duration-300"
                  :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-slate-700'">
              {{ user?.name || 'User' }}
            </span>
            <font-awesome-icon :icon="['fas', 'chevron-down']"
                               :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-slate-700'"
                               class="text-xs" />
          </button>
          <div v-if="showUserMenu" class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 z-10 transition-colors duration-300"
               :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-slate-200'">
            <button @click="openProfileModal" class="w-full text-left block px-4 py-2 transition-colors duration-300"
                    :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700' : 'hover:bg-slate-100'">
              Profile
            </button>
            <router-link to="/settings" @click="closeUserMenu" class="block px-4 py-2 transition-colors duration-300"
                         :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700' : 'hover:bg-slate-100'">
              Settings
            </router-link>
            <button @click="logout" class="w-full text-left block px-4 py-2 transition-colors duration-300"
                    :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700 text-red-400 hover:text-red-300' : 'hover:bg-slate-100 text-red-600 hover:text-red-700'">
              Logout
            </button>
          </div>
        </div>
      </div>
    </header>

    <div class="flex flex-grow transition-all duration-300">
      <!-- Sidebar -->
      <aside
        v-show="sidebarVisible"
        :style="{ display: sidebarVisible ? 'block' : 'none' }"
        class="w-48 p-4"
        :class="themeClasses.sidebar"
      >
        <nav>
          <ul class="space-y-2">
            <li>
              <router-link to="/dashboard" class="flex items-center space-x-2 p-2 rounded-md transition-colors duration-300"
                           :class="[
                             isDashboardActive
                               ? (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-blue-500 text-white' : 'bg-blue-600 text-white')
                               : (store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700 text-white' : 'hover:bg-slate-200 text-gray-900'),
                             store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
                           ]">
                <font-awesome-icon :icon="['fas', 'home']" />
                <span>Dashboard</span>
              </router-link>
            </li>
            <li>
              <router-link to="/notes" class="flex items-center space-x-2 p-2 rounded-md transition-colors duration-300"
                           :class="[
                             isNotesActive
                               ? (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-blue-500 text-white' : 'bg-blue-600 text-white')
                               : (store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700 text-white' : 'hover:bg-slate-200 text-gray-900'),
                             store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
                           ]">
                <font-awesome-icon :icon="['fas', 'book']" />
                <span>My Notes</span>
              </router-link>
            </li>
            <li>
              <router-link to="/quizzes" class="flex items-center space-x-2 p-2 rounded-md transition-colors duration-300"
                           :class="[
                             isQuizzesActive
                               ? (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-blue-500 text-white' : 'bg-blue-600 text-white')
                               : (store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700 text-white' : 'hover:bg-slate-200 text-gray-900'),
                             store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
                           ]">
                <font-awesome-icon :icon="['fas', 'book']" />
                <span>Quizzes</span>
              </router-link>
            </li>
            <li>
              <router-link to="/goals" class="flex items-center space-x-2 p-2 rounded-md transition-colors duration-300"
                           :class="[
                             isGoalsActive
                               ? (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-blue-500 text-white' : 'bg-blue-600 text-white')
                               : (store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700 text-white' : 'hover:bg-slate-200 text-gray-900'),
                             store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
                           ]">
                <font-awesome-icon :icon="['fas', 'bullseye']" />
                <span>Goal</span>
              </router-link>
            </li>
            <li>
              <router-link to="/settings" class="flex items-center space-x-2 p-2 rounded-md transition-colors duration-300"
                           :class="[
                             isSettingsActive
                               ? (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-blue-500 text-white' : 'bg-blue-600 text-white')
                               : (store.getters['app/getCurrentTheme'] === 'dark' ? 'hover:bg-gray-700 text-white' : 'hover:bg-slate-200 text-gray-900'),
                             store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
                           ]">
                <font-awesome-icon :icon="['fas', 'cog']" />
                <span>Settings</span>
              </router-link>
            </li>
          </ul>
        </nav>
      </aside>

      <slot />
    </div>
  </div>
</template>

<script>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useStore } from 'vuex';
import { useNotifications } from '@/composables/useNotifications';
import { useUserProfile } from '@/composables/useUserProfile';

export default {
  name: 'AppHeader',
  emits: ['open-profile-modal'],
  setup(props, { emit }) {
    const router = useRouter();
    const route = useRoute();
    const store = useStore();

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
      showWarning
    } = useNotifications();

    // =====================================
    // USER PROFILE SYSTEM
    // =====================================
    const {
      user: userProfile,
      getProfilePictureUrl
    } = useUserProfile();

    // =====================================
    // COMPUTED PROPERTIES
    // =====================================

    // Use user from composable
    const user = userProfile;

    // Sidebar visibility from store
    const sidebarVisible = computed(() => store.getters['app/getSidebarVisible']);

    // Active navigation states
    const isDashboardActive = computed(() => route.path === '/dashboard' || route.path === '/');
    const isNotesActive = computed(() => route.path.startsWith('/notes'));
    const isQuizzesActive = computed(() => route.path.startsWith('/quizzes'));
    const isGoalsActive = computed(() => route.path.startsWith('/goals'));
    const isSettingsActive = computed(() => route.path.startsWith('/settings'));

    // Use global theme classes from store with computed property
    const themeClasses = computed(() => {
      try {
        // Check if store and getters are available
        if (!store || !store.getters) {
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

    // =====================================
    // UI STATE
    // =====================================
    const showUserMenu = ref(false);

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
      emit('open-profile-modal');
      showUserMenu.value = false; // Close the dropdown menu
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

    /**
     * Execute notification action
     */
    const executeAction = (notification, action) => {
      if (action.action === 'navigate' && action.route) {
        router.push(action.route);
      } else if (action.action === 'callback' && action.callback) {
        action.callback(notification);
      }
      // Mark as read when action is executed
      markAsRead(notification);
    };

    /**
     * Remove notification
     */
    const removeNotification = (notificationId) => {
      // This would be implemented in the notifications composable
      // For now, just mark as read
      markAsRead({ id: notificationId });
    };

    // Handle image loading errors
    const handleImageError = () => {
      // Profile picture failed to load
    };

    // Handle successful image loading
    const handleImageLoad = () => {
      // Profile picture loaded successfully
    };

    return {
      // UI State
      showUserMenu,

      // Data
      user,
      sidebarVisible,

      // Active navigation states
      isDashboardActive,
      isNotesActive,
      isQuizzesActive,
      isGoalsActive,
      isSettingsActive,

      // Notification functions
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      markAsRead,
      markAllAsRead,
      closeNotifications,
      showSuccess,
      showInfo,
      showWarning,

      // User interaction functions
      toggleSidebar,
      getProfilePictureUrl,
      handleImageError,
      handleImageLoad,

      // User menu functions
      toggleUserMenu,
      closeUserMenu,
      openProfileModal,
      logout,
      executeAction,
      removeNotification,

      // Theme classes
      themeClasses,

      // Store for theme access
      store
    };
  }
}
</script>

<style scoped>
/* Additional styles can be added here if needed */
</style>