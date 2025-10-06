<template>
  <div class="min-h-screen flex flex-col bg-gray-900 text-white">
    <!-- Header -->
    <header class="p-4 flex justify-between items-center">
      <router-link to="/" class="text-xl font-bold">SmartScribe</router-link>
      <div class="space-x-2">
        <router-link to="/login" class="px-4 py-2 border border-white rounded-md hover:bg-gray-800 transition">Sign In</router-link>
        <router-link to="/signup" class="px-4 py-2 bg-white text-gray-900 rounded-md hover:bg-gray-200 transition">Sign Up</router-link>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:px-8" style="min-height: calc(100vh - 200px);">
      <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 sm:p-10 w-full max-w-md border border-gray-700 text-center">
        <!-- Error Icon -->
        <div class="flex justify-center mb-8">
          <div class="w-24 h-24 sm:w-28 sm:h-28 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center shadow-xl">
            <font-awesome-icon :icon="['fas', 'exclamation-triangle']" class="text-white text-3xl sm:text-4xl" />
          </div>
        </div>

        <!-- Error Content -->
        <div class="mb-8">
          <h1 class="text-6xl sm:text-7xl font-bold text-gray-600 mb-4">404</h1>
          <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Page Not Found</h2>
          <p class="text-gray-400 text-sm sm:text-base">The page you are looking for doesn't exist or has been moved.</p>
        </div>

        <!-- Back to Home Button -->
        <router-link to="/" class="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-105 active:scale-95">
          <font-awesome-icon :icon="['fas', 'home']" />
          <span>Back to Home</span>
        </router-link>
      </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 bg-gray-800 text-gray-400 flex justify-between items-center text-sm">
      <div class="flex space-x-4">
        <a href="#" class="hover:text-white">Docs</a>
        <a href="#" class="hover:text-white">Guides</a>
        <a href="#" class="hover:text-white">Help</a>
        <a href="#" class="hover:text-white">Contact</a>
      </div>
      <div>© 2025 SmartScribe Inc.</div>
    </footer>
  </div>
</template>

<script>
export default {
  name: 'NotFoundView'
}
</script>