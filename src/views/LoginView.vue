<template>
  <div class="min-h-screen flex flex-col bg-gray-900 text-white overflow-x-hidden">
    <!-- Header -->
    <header class="p-6 flex justify-between items-center">
      <div class="text-xl font-bold">SmartScribe</div>
      <div class="space-x-2">
        <router-link to="/signup" class="px-4 py-2 border border-white rounded-md hover:bg-gray-800 transition">Sign Up</router-link>
        <router-link to="/contact" class="px-4 py-2 bg-white text-gray-900 rounded-md hover:bg-gray-200 transition">Contact</router-link>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:px-8" style="min-height: calc(100vh - 200px);">
      <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 sm:p-10 w-full max-w-md border border-gray-700">
        <!-- Logo Section -->
        <div class="flex justify-center mb-8">
          <div class="w-24 h-24 sm:w-28 sm:h-28 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full overflow-hidden flex items-center justify-center shadow-xl">
            <img :src="logo" alt="SmartScribe Logo" class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-full" />
          </div>
        </div>

        <!-- Header -->
        <div class="text-center mb-8">
          <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Welcome Back</h1>
          <p class="text-gray-400 text-sm sm:text-base">Sign in to your SmartScribe account</p>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-lg mb-6">
          <div class="flex items-center">
            <font-awesome-icon :icon="['fas', 'exclamation-circle']" class="mr-2" />
            <span>{{ errorMessage }}</span>
          </div>
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="space-y-6">
          <!-- Email Field -->
          <div class="space-y-2">
            <label for="email" class="block text-sm font-medium text-gray-300">
              Email Address
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <font-awesome-icon :icon="['fas', 'envelope']" class="text-gray-500 text-sm" />
              </div>
              <input
                id="email"
                type="email"
                v-model="email"
                placeholder="Enter your email"
                class="w-full pl-10 pr-4 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
                required
              />
            </div>
          </div>

          <!-- Password Field -->
          <div class="space-y-2">
            <label for="password" class="block text-sm font-medium text-gray-300">
              Password
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <font-awesome-icon :icon="['fas', 'lock']" class="text-gray-500 text-sm" />
              </div>
              <input
                id="password"
                :type="passwordVisible ? 'text' : 'password'"
                v-model="password"
                placeholder="Enter your password"
                class="w-full pl-10 pr-12 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
                required
              />
              <button
                type="button"
                @click="passwordVisible = !passwordVisible"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-300 transition-colors"
              >
                <font-awesome-icon :icon="['fas', passwordVisible ? 'eye-slash' : 'eye']" class="text-sm" />
              </button>
            </div>
          </div>

          <!-- Forgot Password Link -->
          <div class="flex justify-end">
            <router-link to="/forgot-password" class="text-sm text-blue-400 hover:text-blue-300 hover:underline transition-colors">
              Forgot your password?
            </router-link>
          </div>

          <!-- Sign In Button -->
          <button
            type="submit"
            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="flex items-center justify-center">
              <font-awesome-icon :icon="['fas', 'spinner']" spin class="mr-2" />
              Signing in...
            </span>
            <span v-else class="flex items-center justify-center">
              <font-awesome-icon :icon="['fas', 'sign-in-alt']" class="mr-2" />
              Sign In
            </span>
          </button>
        </form>
        
        <!-- Divider -->
        <div class="relative my-8">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-gray-600"></div>
          </div>
          <div class="relative flex justify-center text-sm">
            <span class="px-4 bg-gray-800 text-gray-400">Or continue with</span>
          </div>
        </div>

        <!-- Social Login Buttons -->
        <div class="grid grid-cols-3 gap-3 mb-8">
          <button
            @click="handleGoogleLogin"
            class="w-full flex items-center justify-center px-4 py-3 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors duration-200"
            :disabled="isLoading"
          >
            <font-awesome-icon :icon="['fab', 'google']" class="text-red-400 text-lg" />
          </button>
          <button
            @click="handleFacebookLogin"
            class="w-full flex items-center justify-center px-4 py-3 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors duration-200"
            :disabled="isLoading"
          >
            <font-awesome-icon :icon="['fab', 'facebook-f']" class="text-blue-400 text-lg" />
          </button>
          <button class="w-full flex items-center justify-center px-4 py-3 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors duration-200">
            <font-awesome-icon :icon="['fab', 'apple']" class="text-gray-300 text-lg" />
          </button>
        </div>

        <!-- Sign Up Link -->
        <div class="text-center">
          <p class="text-gray-400 text-sm">
            Don't have an account?
            <router-link to="/signup" class="text-blue-400 hover:text-blue-300 font-medium hover:underline transition-colors">
              Sign up for free
            </router-link>
          </p>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <AppFooter
      :links="footerLinks"
      :copyright="copyrightText"
    />
</div>
</template>

<script>
import logo from '@/assets/image/logo.jpg'
import AppFooter from '@/components/Footer.vue'
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useStore } from 'vuex';

export default {
  name: 'LoginView',
  components: {
    AppFooter
  },
  setup() {
    const router = useRouter();
    const store = useStore();

    const email = ref('');
    const password = ref('');
    const isLoading = ref(false);
    const errorMessage = ref('');

    const passwordVisible = ref(false);

    // Footer configuration
    const footerLinks = [
      { text: 'Privacy Policy', href: '#' },
      { text: 'Terms of Service', href: '#' },
      { text: 'Help Center', href: '#' }
    ];

    const copyrightText = '© 2025 SmartScribe. All rights reserved.';

    const handleLogin = async () => {
      try {
        isLoading.value = true;
        errorMessage.value = '';

        console.log('🔐 LoginView handleLogin called with:', {
          email: email.value,
          passwordLength: password.value ? password.value.length : 0,
          emailValid: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value),
          hasCredentials: !!(email.value && password.value)
        });

        // Use Vuex store for authentication
        await store.dispatch('auth/login', {
          email: email.value,
          password: password.value
        });

        console.log('🔐 Login successful - Redirecting to dashboard');

        // Redirect to dashboard on successful login
        router.push('/dashboard');

      } catch (error) {
        console.error('❌ LoginView login failed:', {
          message: error.message,
          stack: error.stack,
          name: error.name
        });
        errorMessage.value = error.message || 'Failed to login. Please try again.';
      } finally {
        isLoading.value = false;
      }
    };

    const handleGoogleLogin = async () => {
      try {
        console.log('🔐 Attempting Google login with Google Identity Services');
        isLoading.value = true;
        errorMessage.value = '';

        // Check if Google Identity Services is loaded
        if (!window.google || !window.google.accounts) {
          errorMessage.value = 'Google Sign-In is not available. Please refresh the page and try again.';
          isLoading.value = false;
          return;
        }

        // Initialize Google Sign-In
        const clientId = process.env.VUE_APP_GOOGLE_OAUTH_CLIENT_ID || 'your_production_google_oauth_client_id';

        if (clientId === 'your_production_google_oauth_client_id') {
          errorMessage.value = 'Google OAuth client ID not configured. Please check your environment variables.';
          isLoading.value = false;
          return;
        }

        // Create Google Sign-In client
        const googleClient = window.google.accounts.oauth2.initTokenClient({
          client_id: clientId,
          scope: 'openid email profile',
          callback: async (response) => {
            if (response.error) {
              console.error('❌ Google OAuth error:', response);
              errorMessage.value = 'Google login failed. Please try again.';
              isLoading.value = false;
              return;
            }

            try {
              console.log('🔐 Google OAuth successful, sending to backend');

              // Send the access token to backend for verification
              await store.dispatch('auth/googleLogin', response.access_token);

              console.log('🔐 Google login successful - Redirecting to dashboard');
              router.push('/dashboard');
            } catch (error) {
              console.error('❌ Backend Google login failed:', error);
              errorMessage.value = error.message || 'Failed to authenticate with Google. Please try again.';
            } finally {
              isLoading.value = false;
            }
          }
        });

        // Request access token
        googleClient.requestAccessToken();

      } catch (error) {
        console.error('❌ Google login failed:', error);
        errorMessage.value = error.message || 'Google login failed. Please try again.';
        isLoading.value = false;
      }
    };

    const handleFacebookLogin = async () => {
      try {
        console.log('🔐 Attempting Facebook login');
        isLoading.value = true;
        errorMessage.value = '';

        // Facebook login is not currently implemented
        alert('Facebook login is not currently supported. Please use email/password login.');

      } catch (error) {
        console.error('❌ Facebook login failed:', error);
        errorMessage.value = error.message || 'Facebook login failed. Please try again.';
        isLoading.value = false;
      }
    };

    return {
      email,
      password,
      isLoading,
      errorMessage,
      handleLogin,
      handleGoogleLogin,
      handleFacebookLogin,
      passwordVisible,
      logo,
      footerLinks,
      copyrightText
    };
  }
}
</script>