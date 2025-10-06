<template>
  <div class="min-h-screen flex flex-col bg-gray-900 text-white overflow-x-hidden">
    <!-- Header -->
    <header class="p-6 flex justify-between items-center">
      <div class="text-xl font-bold">SmartScribe</div>
      <div class="space-x-2">
        <router-link to="/login" class="px-4 py-2 border border-white rounded-md hover:bg-gray-800 transition">Sign In</router-link>
        <router-link to="/signup" class="px-4 py-2 bg-white text-gray-900 rounded-md hover:bg-gray-200 transition">Get Started</router-link>
      </div>
    </header>

    <main class="flex-grow flex items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:px-8" style="min-height: calc(100vh - 200px);">
      <div class="bg-gray-800 rounded-2xl shadow-2xl p-8 sm:p-10 w-full max-w-6xl border border-gray-700">
        <div class="text-center mb-12">
          <h1 class="text-3xl sm:text-4xl font-bold mb-4 text-white">SmartScribe: AI Study Helper</h1>
          <p class="text-lg sm:text-xl text-gray-400">Transform your study notes into digital content with OCR and AI</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 sm:gap-8 mb-12">
          <div class="bg-gray-700/50 rounded-2xl p-6 sm:p-8 border border-gray-600 hover:border-gray-500 transition-all duration-300 hover:shadow-lg hover:shadow-gray-900/20">
            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
              <font-awesome-icon :icon="['fas', 'camera']" class="text-white text-xl sm:text-2xl" />
            </div>
            <h2 class="text-xl sm:text-2xl font-semibold mb-3 text-white">OCR-based Note Scanning</h2>
            <p class="text-gray-300 leading-relaxed">Take pictures of your handwritten or printed study notes and convert them to digital text instantly.</p>
          </div>

          <div class="bg-gray-700/50 rounded-2xl p-6 sm:p-8 border border-gray-600 hover:border-gray-500 transition-all duration-300 hover:shadow-lg hover:shadow-gray-900/20">
            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
              <font-awesome-icon :icon="['fas', 'robot']" class="text-white text-xl sm:text-2xl" />
            </div>
            <h2 class="text-xl sm:text-2xl font-semibold mb-3 text-white">AI Summarization</h2>
            <p class="text-gray-300 leading-relaxed">Generate concise summaries of your notes using advanced AI technology.</p>
          </div>

          <div class="bg-gray-700/50 rounded-2xl p-6 sm:p-8 border border-gray-600 hover:border-gray-500 transition-all duration-300 hover:shadow-lg hover:shadow-gray-900/20">
            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
              <font-awesome-icon :icon="['fas', 'chart-line']" class="text-white text-xl sm:text-2xl" />
            </div>
            <h2 class="text-xl sm:text-2xl font-semibold mb-3 text-white">Study Progress Tracker</h2>
            <p class="text-gray-300 leading-relaxed">Track how many notes you've scanned and reviewed to stay on top of your studies.</p>
          </div>

          <div class="bg-gray-700/50 rounded-2xl p-6 sm:p-8 border border-gray-600 hover:border-gray-500 transition-all duration-300 hover:shadow-lg hover:shadow-gray-900/20">
            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center mb-6 shadow-lg">
              <font-awesome-icon :icon="['fas', 'highlighter']" class="text-white text-xl sm:text-2xl" />
            </div>
            <h2 class="text-xl sm:text-2xl font-semibold mb-3 text-white">Keyword Highlighting</h2>
            <p class="text-gray-300 leading-relaxed">Automatically highlight important terms and concepts in your notes.</p>
          </div>
        </div>

        <div class="text-center">
          <router-link to="/signup" class="inline-flex items-center space-x-2 px-8 py-4 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl text-lg font-semibold hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-all duration-200 transform hover:scale-105 active:scale-95 shadow-lg">
            <font-awesome-icon :icon="['fas', 'rocket']" />
            <span>Get Started</span>
          </router-link>
        </div>
      </div>
    </main>

    <AppFooter
      :links="footerLinks"
      :copyright="copyrightText"
    />
</div>
</template>

<script>
import AppFooter from '@/components/Footer.vue'

export default {
  name: 'HomeView',
  components: {
    AppFooter
  },
  setup() {
    // Footer configuration
    const footerLinks = [
      { text: 'Privacy Policy', href: '#' },
      { text: 'Terms of Service', href: '#' },
      { text: 'Help Center', href: '#' }
    ];

    const copyrightText = '© 2025 SmartScribe. All rights reserved.';

    return {
      footerLinks,
      copyrightText
    };
  },
  mounted() {
    // Ensure proper styling is applied
    document.body.classList.add('loaded');
  }
}
</script>

<style scoped>
/* Ensure consistent dark theme styling */
.flex.flex-col.min-h-screen {
  background-color: #111827 !important;
  color: white !important;
}

.bg-gray-800 {
  background-color: #1f2937 !important;
}

.text-white {
  color: white !important;
}

.text-gray-400 {
  color: #9ca3af !important;
}

.text-gray-300 {
  color: #d1d5db !important;
}
</style>