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

        <!-- Loading State -->
        <div v-if="isValidating" class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
          <p class="text-gray-400">Validating reset token...</p>
        </div>

        <!-- Invalid Token State -->
        <div v-else-if="!isValidToken" class="text-center">
          <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <font-awesome-icon :icon="['fas', 'times-circle']" class="text-white text-2xl" />
          </div>
          <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Invalid Reset Link</h1>
          <p class="text-gray-400 text-sm sm:text-base mb-6">
            This password reset link is invalid or has expired. Please request a new one.
          </p>
          <router-link
            to="/forgot-password"
            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 inline-block text-center"
          >
            Request New Reset Link
          </router-link>
        </div>

        <!-- Valid Token State -->
        <div v-else>
          <!-- Header -->
          <div class="text-center mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-2">Set New Password</h1>
            <p class="text-gray-400 text-sm sm:text-base">Enter your new password below</p>
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
          <form @submit.prevent="handlePasswordReset" class="space-y-6">
            <!-- Email Display -->
            <div class="bg-gray-700/50 rounded-lg p-4 mb-6">
              <div class="flex items-center space-x-3">
                <font-awesome-icon :icon="['fas', 'envelope']" class="text-gray-400" />
                <div>
                  <p class="text-sm text-gray-400">Resetting password for:</p>
                  <p class="text-white font-medium">{{ userEmail }}</p>
                </div>
              </div>
            </div>

            <!-- New Password Field -->
            <div class="space-y-2">
              <label for="password" class="block text-sm font-medium text-gray-300">
                New Password
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
                  placeholder="Enter new password"
                  class="w-full pl-10 pr-12 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
                  required
                  minlength="6"
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
                Confirm New Password
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
                  placeholder="Confirm new password"
                  class="w-full pl-10 pr-12 py-3 text-sm sm:text-base border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 bg-gray-700 text-white placeholder-gray-400 focus:bg-gray-600"
                  required
                  minlength="6"
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

            <!-- Password Requirements -->
            <div class="text-xs text-gray-400 space-y-1">
              <p>Password requirements:</p>
              <ul class="list-disc list-inside space-y-1 ml-2">
                <li :class="password.length >= 6 ? 'text-green-400' : ''">At least 6 characters</li>
                <li :class="password === confirmPassword && password.length > 0 ? 'text-green-400' : ''">Passwords must match</li>
              </ul>
            </div>

            <!-- Reset Button -->
            <button
              type="submit"
              class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
              :disabled="isLoading || !isFormValid"
            >
              <span v-if="isLoading" class="flex items-center justify-center">
                <font-awesome-icon :icon="['fas', 'spinner']" spin class="mr-2" />
                Resetting Password...
              </span>
              <span v-else class="flex items-center justify-center">
                <font-awesome-icon :icon="['fas', 'key']" class="mr-2" />
                Reset Password
              </span>
            </button>
          </form>

          <!-- Success State -->
          <div v-if="passwordReset" class="text-center mt-8">
            <div class="bg-green-900/20 border border-green-700/50 rounded-lg p-6">
              <div class="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <font-awesome-icon :icon="['fas', 'check-circle']" class="text-white text-2xl" />
              </div>
              <h3 class="text-lg font-semibold text-white mb-2">Password Reset Successful!</h3>
              <p class="text-gray-400 text-sm mb-6">
                Your password has been successfully reset. You can now sign in with your new password.
              </p>
              <router-link
                to="/login"
                class="w-full bg-gradient-to-r from-blue-600 to-purple-600 text-white py-3 px-4 rounded-lg font-semibold text-sm sm:text-base hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 inline-block text-center"
              >
                Sign In Now
              </router-link>
            </div>
          </div>
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
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/services/api'

export default {
  name: 'ResetPasswordView',
  setup() {
    const router = useRouter()
    const route = useRoute()

    const token = ref(route.query.token || '')
    const userEmail = ref('')
    const password = ref('')
    const confirmPassword = ref('')
    const isLoading = ref(false)
    const isValidating = ref(true)
    const isValidToken = ref(false)
    const errorMessage = ref('')
    const successMessage = ref('')
    const passwordReset = ref(false)
    const passwordVisible = ref(false)
    const confirmPasswordVisible = ref(false)

    const passwordInput = ref(null)
    const confirmPasswordInput = ref(null)

    const isFormValid = computed(() => {
      return password.value.length >= 6 &&
             password.value === confirmPassword.value &&
             !isLoading.value
    })

    const validateToken = async () => {
      if (!token.value) {
        isValidating.value = false
        isValidToken.value = false
        return
      }

      try {
        const response = await api.validateResetToken(token.value)
        userEmail.value = response.data.email
        isValidToken.value = true
      } catch (error) {
        console.error('❌ Token validation failed:', error)
        isValidToken.value = false
      } finally {
        isValidating.value = false
      }
    }

    const handlePasswordReset = async () => {
      if (!isFormValid.value) return

      try {
        isLoading.value = true
        errorMessage.value = ''
        successMessage.value = ''

        await api.resetPassword(token.value, password.value)

        successMessage.value = 'Password reset successful! You can now sign in with your new password.'
        passwordReset.value = true

        // Redirect to login after 3 seconds
        setTimeout(() => {
          router.push('/login')
        }, 3000)

      } catch (error) {
        console.error('❌ Password reset failed:', error)
        errorMessage.value = error.response?.data?.error ||
                           error.response?.data?.message ||
                           'Failed to reset password. Please try again.'
      } finally {
        isLoading.value = false
      }
    }

    onMounted(() => {
      validateToken()
    })

    return {
      token,
      userEmail,
      password,
      confirmPassword,
      isLoading,
      isValidating,
      isValidToken,
      errorMessage,
      successMessage,
      passwordReset,
      passwordVisible,
      confirmPasswordVisible,
      passwordInput,
      confirmPasswordInput,
      isFormValid,
      handlePasswordReset,
      logo
    }
  }
}
</script>