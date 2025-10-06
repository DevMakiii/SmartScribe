  <template>
  <div class="min-h-screen flex flex-col">
  <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 sm:p-10 w-full max-w-md border border-gray-700">
        <!-- Logo Section -->
        <div class="flex justify-center mb-8">
          <div class="w-24 h-24 sm:w-28 sm:h-28 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full overflow-hidden flex items-center justify-center shadow-xl">
            <img :src="logo" alt="SmartScribe Logo" class="w-20 h-20 sm:w-24 sm:h-24 object-cover rounded-full" />
          </div>
        </div>
        
        <!-- Header -->
        <div class="text-center mb-8">
          <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Create Your Account</h1>
          <p class="text-gray-400 text-sm sm:text-base">Sign up for SmartScribe to start digitizing your notes</p>
        </div>
        
        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-lg mb-6">
          <div class="flex items-center">
            <font-awesome-icon :icon="['fas', 'exclamation-circle']" class="mr-2" />
            <span>{{ errorMessage }}</span>
          </div>
        </div>

        <!-- Signup Form -->
        <form @submit.prevent="handleSignup" class="space-y-6">
          <!-- Name Fields -->
          <div class="space-y-2">
            <label for="firstName" class="block text-sm font-medium text-gray-300">
              First Name
            </label>
            <input
              id="firstName"
              type='text'
              v-model="firstName"
              placeholder="Enter your first name"
              class="w-full px-4 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
              required
            />
          </div>

          <div class="space-y-2">
            <label for="lastName" class="block text-sm font-medium text-gray-300">
              Last Name
            </label>
            <input
              id="lastName"
              type='text'
              v-model="lastName"
              placeholder="Enter your last name"
              class="w-full px-4 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
              required
            />
          </div>

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
               ref="passwordInput"
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

         <!-- Confirm Password Field -->
         <div class="space-y-2">
           <label for="confirmPassword" class="block text-sm font-medium text-gray-300">
             Confirm Password
           </label>
           <div class="relative">
             <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
               <font-awesome-icon :icon="['fas', 'lock']" class="text-gray-500 text-sm" />
             </div>
             <input
               id="confirmPassword"
               ref="confirmPasswordInput"
               :type="confirmPasswordVisible ? 'text' : 'password'"
               v-model="confirmPassword"
               placeholder="Confirm your password"
               class="w-full pl-10 pr-12 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
               required
             />
             <button
               type="button"
               @click="confirmPasswordVisible = !confirmPasswordVisible"
               class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-300 transition-colors"
             >
               <font-awesome-icon :icon="['fas', confirmPasswordVisible ? 'eye-slash' : 'eye']" class="text-sm" />
             </button>
           </div>
         </div>

         <!-- Sign Up Button -->
         <button
           type="submit"
           class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
           :disabled="isLoading"
         >
           <span v-if="isLoading" class="flex items-center justify-center">
             <font-awesome-icon :icon="['fas', 'spinner']" spin class="mr-2" />
             Creating account...
           </span>
           <span v-else class="flex items-center justify-center">
             <font-awesome-icon :icon="['fas', 'user-plus']" class="mr-2" />
             Create Account
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
          <button id="google-signup-btn" @click="handleGoogleSignup" class="w-full flex items-center justify-center px-4 py-3 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors duration-200">
            <font-awesome-icon :icon="['fab', 'google']" class="text-red-400 text-lg" />
          </button>
          <button @click="handleFacebookSignup" class="w-full flex items-center justify-center px-4 py-3 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors duration-200" :disabled="isLoading">
            <font-awesome-icon :icon="['fab', 'facebook-f']" class="text-blue-400 text-lg" />
          </button>
          <button class="w-full flex items-center justify-center px-4 py-3 border border-gray-600 rounded-lg hover:bg-gray-700 transition-colors duration-200">
            <font-awesome-icon :icon="['fab', 'apple']" class="text-gray-300 text-lg" />
          </button>
        </div>

        <!-- Sign In Link -->
        <div class="text-center">
          <p class="text-gray-400 text-sm">
            Already have an account?
            <router-link to="/login" class="text-blue-400 hover:text-blue-300 font-medium hover:underline transition-colors">
              Sign in here
            </router-link>
          </p>
        </div>
        <!-- Success Modal -->
    <transition name="fade">
    <div v-if="showSuccessModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
      <div class="bg-white text-gray-800 rounded-xl p-6 w-full max-w-sm shadow-xl transform transition-all scale-100">
        <div class="flex justify-center mb-4">
          <div class="bg-green-100 text-green-600 w-16 h-16 flex items-center justify-center rounded-full">
            <font-awesome-icon :icon="['fas', 'check-circle']" class="text-3xl" />
          </div>
        </div>
        <h3 class="text-lg font-semibold text-center mb-2">Account Created!</h3>
        <p class="text-center mb-4">Your account was created successfully. You can now log in.</p>
      </div>
    </div>
    </transition>
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
  name: 'SignupView',
  components: {
    AppFooter
  },
  setup() {
    const router = useRouter();
    const store = useStore();

    const firstName = ref('');
    const lastName = ref('');
    const email = ref('');
    const password = ref('');
    const confirmPassword = ref('');
    const isLoading = ref(false);
    const errorMessage = ref('');
    const showSuccessModal = ref(false);

    const passwordVisible = ref(false);
    const confirmPasswordVisible = ref(false);

    const passwordInput = ref(null);
    const confirmPasswordInput = ref(null);

    // Footer configuration
    const footerLinks = [
      { text: 'Privacy Policy', href: '#' },
      { text: 'Terms of Service', href: '#' },
      { text: 'Help Center', href: '#' }
    ];

    const copyrightText = '© 2025 SmartScribe. All rights reserved.';

    const redirectToLogin = () => {
      showSuccessModal.value = false;
      router.push('/login')
    }

    const togglePasswordField = (inputRef) => {
      if (inputRef === 'passwordInput') {
        passwordVisible.value = !passwordVisible.value;
      } else if (inputRef === 'confirmPasswordInput') {
        confirmPasswordVisible.value = !confirmPasswordVisible.value;
      }
    };

    const handleGoogleSignup = async () => {
      try {
        console.log('🔐 Attempting Google signup with Google Identity Services');
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
          scope: 'openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
          callback: async (response) => {
            if (response.error) {
              console.error('❌ Google OAuth error:', response);
              errorMessage.value = 'Google signup failed. Please try again.';
              isLoading.value = false;
              return;
            }

            try {
              console.log('🔐 Google OAuth successful, sending to backend');
              console.log('🔐 Access token length:', response.access_token.length);

              // Send the access token to backend for verification
              await store.dispatch('auth/googleLogin', response.access_token);

              console.log('🔐 Google signup successful - Redirecting to dashboard');
              router.push('/dashboard');
            } catch (error) {
              console.error('❌ Backend Google signup failed:', error);
              errorMessage.value = error.message || 'Failed to create account with Google. Please try again.';
            } finally {
              isLoading.value = false;
            }
          }
        });

        // Request access token
        googleClient.requestAccessToken();

      } catch (error) {
        console.error('❌ Google signup failed:', error);
        errorMessage.value = error.message || 'Google signup failed. Please try again.';
        isLoading.value = false;
      }
    };

    const handleFacebookSignup = async () => {
      try {
        console.log('🔐 Attempting Facebook signup');
        isLoading.value = true;
        errorMessage.value = '';

        // Facebook signup is not currently implemented
        alert('Facebook signup is not currently supported. Please use email/password registration.');

      } catch (error) {
        console.error('❌ Facebook signup failed:', error);
        errorMessage.value = error.message || 'Facebook signup failed. Please try again.';
        isLoading.value = false;
      }
    };

    const handleSignup = async () => {
      try {
        // Validate passwords match
        if (password.value !== confirmPassword.value) {
          errorMessage.value = 'Passwords do not match';
          return;
        }

        isLoading.value = true;
        errorMessage.value = '';

        // Use Vuex store for registration
        await store.dispatch('auth/register', {
          first_name: firstName.value,
          last_name: lastName.value,
          name: `${firstName.value} ${lastName.value}`,
          email: email.value,
          password: password.value
        });

        console.log('✅ Registration successful - Showing success modal');

        showSuccessModal.value = true;

        // Auto-close modal and redirect after 2.5 seconds
        setTimeout(() => {
          redirectToLogin()
        }, 2500)

      } catch (error) {
        console.error('❌ Registration failed:', error.message);
        errorMessage.value = error.message || 'Failed to create account. Please try again.';
      } finally {
        isLoading.value = false;
      }
    };

    return {
      firstName,
      lastName,
      email,
      password,
      confirmPassword,
      isLoading,
      errorMessage,
      handleSignup,
      passwordInput,
      confirmPasswordInput,
      togglePasswordField,
      showSuccessModal,
      redirectToLogin,
      logo,
      passwordVisible,
      confirmPasswordVisible,
      handleGoogleSignup,
      handleFacebookSignup,
      footerLinks,
      copyrightText
    };
  }
}
</script>