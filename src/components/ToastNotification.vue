<template>
  <div v-if="currentToast" class="fixed top-4 right-4 z-50 max-w-sm w-full">
    <div :class="[
      'rounded-lg p-4 shadow-lg border transition-all duration-300 transform',
      currentToast.type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : '',
      currentToast.type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : '',
      currentToast.type === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' : '',
      currentToast.type === 'info' ? 'bg-blue-50 border-blue-200 text-blue-800' : '',
      isVisible ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'
    ]">
      <div class="flex items-start">
        <div class="flex-shrink-0">
          <font-awesome-icon
            :icon="currentToast.icon"
            class="h-5 w-5"
          />
        </div>
        <div class="ml-3 w-0 flex-1">
          <p class="text-sm font-medium">{{ currentToast.title }}</p>
          <p class="mt-1 text-sm">{{ currentToast.message }}</p>
        </div>
        <div class="ml-4 flex-shrink-0 flex">
          <button
            @click="dismissToast"
            class="inline-flex text-gray-400 hover:text-gray-500 focus:outline-none"
          >
            <font-awesome-icon :icon="['fas', 'times']" class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'

export default {
  name: 'ToastNotification',
  setup() {
    const currentToast = ref(null)
    const isVisible = ref(false)
    const toastQueue = ref([])
    const autoHideTimeout = ref(null)

    const showToast = (toast) => {
      // Add to queue if there's already a toast showing
      if (currentToast.value) {
        toastQueue.value.push(toast)
        return
      }

      currentToast.value = toast
      isVisible.value = true

      // Auto-hide after 4 seconds
      autoHideTimeout.value = setTimeout(() => {
        dismissToast()
      }, 4000)
    }

    const dismissToast = () => {
      if (autoHideTimeout.value) {
        clearTimeout(autoHideTimeout.value)
        autoHideTimeout.value = null
      }

      isVisible.value = false

      // Remove current toast after animation
      setTimeout(() => {
        currentToast.value = null

        // Show next toast in queue
        if (toastQueue.value.length > 0) {
          const nextToast = toastQueue.value.shift()
          setTimeout(() => showToast(nextToast), 100)
        }
      }, 300)
    }

    // Listen for toast events
    const handleToastEvent = (event) => {
      if (event.detail && event.detail.type && event.detail.title) {
        showToast(event.detail)
      }
    }

    onMounted(() => {
      window.addEventListener('show-toast', handleToastEvent)
    })

    // Cleanup
    const cleanup = () => {
      window.removeEventListener('show-toast', handleToastEvent)
      if (autoHideTimeout.value) {
        clearTimeout(autoHideTimeout.value)
      }
    }

    // Return cleanup function for onUnmounted
    return {
      currentToast,
      isVisible,
      dismissToast,
      cleanup
    }
  },
  beforeUnmount() {
    this.cleanup()
  }
}
</script>