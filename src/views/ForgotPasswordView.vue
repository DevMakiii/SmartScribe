<template>
  <div class="min-h-screen flex flex-col bg-gray-900 text-white overflow-x-hidden">
    <!-- Header -->
    <header class="p-6 flex justify-between items-center">
      <router-link to="/" class="text-xl sm:text-2xl font-bold text-white hover:text-blue-400 transition-colors">
        SmartScribe
      </router-link>
      <div class="flex space-x-3">
        <router-link to="/contact" class="px-4 py-2 text-sm sm:text-base text-gray-400 hover:text-white transition-colors">
          Contact
        </router-link>
        <router-link to="/login" class="px-4 py-2 text-sm sm:text-base bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
          Sign In
        </router-link>
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
          <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Reset Your Password</h1>
          <p class="text-gray-400 text-sm sm:text-base">Enter your email address and we'll send you a link to reset your password</p>
        </div>

        <!-- Success Message -->
        <div v-if="successMessage" class="bg-green-900 border border-green-700 text-green-200 px-4 py-3 rounded-lg mb-6">
          <div class="flex items-center">
            <font-awesome-icon :icon="['fas', 'check-circle']" class="mr-2" />
            <span>{{ successMessage }}</span>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded-lg mb-6">
          <div class="flex items-center">
            <font-awesome-icon :icon="['fas', 'exclamation-circle']" class="mr-2" />
            <span>{{ errorMessage }}</span>
          </div>
        </div>

        <!-- Reset Form -->
        <form v-if="!emailSent" @submit.prevent="handlePasswordReset" class="space-y-6">
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

          <!-- Reset Button -->
          <button
            type="submit"
            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="flex items-center justify-center">
              <font-awesome-icon :icon="['fas', 'spinner']" spin class="mr-2" />
              Sending Reset Link...
            </span>
            <span v-else class="flex items-center justify-center">
              <font-awesome-icon :icon="['fas', 'paper-plane']" class="mr-2" />
              Send Reset Link
            </span>
          </button>
        </form>

        <!-- Success State -->
        <div v-else class="text-center space-y-6">
          <div class="bg-green-900/20 border border-green-700/50 rounded-lg p-6">
            <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
              <font-awesome-icon :icon="['fas', 'check-circle']" class="text-white text-2xl" />
            </div>
            <h3 class="text-lg font-semibold text-white mb-2">Check Your Email</h3>
            <p class="text-gray-400 text-sm mb-4">
              We've sent a password reset link to <strong>{{ email }}</strong>
            </p>

            <!-- Development: Show reset link directly -->
            <div v-if="resetLink" class="bg-blue-900/20 border border-blue-700/50 rounded-lg p-4 mb-4">
              <p class="text-blue-300 text-sm mb-2">Development Mode - Click the link below:</p>
              <a :href="resetLink" target="_blank" class="text-blue-400 hover:text-blue-300 underline text-sm break-all">
                {{ resetLink }}
              </a>
            </div>

            <p class="text-gray-500 text-xs">
              The link will expire in 1 hour. If you don't see it, check your spam folder.
            </p>
          </div>

          <!-- Resend Button -->
          <button
            @click="handlePasswordReset"
            class="w-full bg-gray-700 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="flex items-center justify-center">
              <font-awesome-icon :icon="['fas', 'spinner']" spin class="mr-2" />
              Resending...
            </span>
            <span v-else class="flex items-center justify-center">
              <font-awesome-icon :icon="['fas', 'redo']" class="mr-2" />
              Resend Reset Link
            </span>
          </button>
        </div>

        <!-- Back to Login -->
        <div class="text-center mt-8">
          <router-link to="/login" class="text-blue-400 hover:text-blue-300 font-medium hover:underline transition-colors">
            ← Back to Sign In
          </router-link>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="p-6 text-center text-gray-400 text-sm">
      <div class="flex flex-col sm:flex-row justify-center items-center space-y-2 sm:space-y-0 sm:space-x-6">
        <a href="#" class="hover:text-gray-300 transition-colors">Privacy Policy</a>
        <a href="#" class="hover:text-gray-300 transition-colors">Terms of Service</a>
        <a href="#" class="hover:text-gray-300 transition-colors">Help Center</a>
      </div>
      <div class="mt-4 text-gray-500">
        © 2025 SmartScribe. All rights reserved.
      </div>
    </footer>
  </div>
</template>

<script>
import logo from '@/assets/image/logo.jpg'
import { ref } from 'vue'
import api from '@/services/api'

export default {
  name: 'ForgotPasswordView',
  setup() {

    const email = ref('')
    const isLoading = ref(false)
    const errorMessage = ref('')
    const successMessage = ref('')
    const emailSent = ref(false)
    const resetLink = ref('')

    const handlePasswordReset = async () => {
      try {
        isLoading.value = true
        errorMessage.value = ''
        successMessage.value = ''

        const response = await api.requestPasswordReset(email.value)

        successMessage.value = response.data.message
        emailSent.value = true

        // Store the reset link for development
        if (response.data.reset_link) {
          resetLink.value = response.data.reset_link
          console.log('🔗 Password Reset Link (Development):', response.data.reset_link)
        }

      } catch (error) {
        console.error('❌ Password reset request failed:', error)
        errorMessage.value = error.response?.data?.error ||
                           error.response?.data?.message ||
                           'Failed to send reset link. Please try again.'
      } finally {
        isLoading.value = false
      }
    }

    return {
      email,
      isLoading,
      errorMessage,
      successMessage,
      emailSent,
      resetLink,
      handlePasswordReset,
      logo
    }
  }
}
</script>