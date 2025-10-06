<!-- src/App.vue -->
<template>
  <div :class="globalThemeClasses">
    <router-view />
  </div>
</template>

<script>
import { computed, onMounted } from 'vue';
import { useStore } from 'vuex';

export default {
  name: 'App',
  setup() {
    const store = useStore();

    // Global theme classes applied to entire app
    const globalThemeClasses = computed(() => {
      try {
        // Check if store and getters are available
        if (!store || !store.getters) {
          return 'bg-gray-900 text-white';
        }

        const themeClasses = store.getters['app/getThemeClasses'];
        return themeClasses?.main || 'bg-gray-900 text-white';
      } catch (error) {
        return 'bg-gray-900 text-white';
      }
    });

    onMounted(() => {
      // Theme is already applied during app initialization in main.js
      // No need to apply it again here to avoid conflicts
      console.log('🎨 App mounted - theme should already be applied from initialization');
    });

    return {
      globalThemeClasses
    };
  }
}
</script>