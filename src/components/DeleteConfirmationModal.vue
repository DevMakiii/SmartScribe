<template>
  <!-- Delete Confirmation Modal -->
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div class="bg-gray-800 rounded-lg shadow-2xl w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-4 border-b border-gray-700">
        <h2 class="text-xl font-semibold text-white">Delete Account</h2>
        <button @click="cancel" class="text-gray-400 hover:text-white transition-colors">
          <font-awesome-icon :icon="['fas', 'times']" class="text-lg" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6">
        <div class="text-center">
          <div class="mb-4">
            <font-awesome-icon :icon="['fas', 'triangle-exclamation']" class="text-4xl text-red-500 mb-4" />
          </div>
          <h3 class="text-lg font-semibold mb-4 text-white">Are you sure?</h3>
          <p class="text-gray-300 mb-6">
            This action cannot be undone. This will permanently delete your account and remove all your data from our servers.
          </p>
        </div>

        <div class="flex justify-center space-x-4">
          <button
            @click="cancel"
            class="px-6 py-2 bg-gray-600 rounded-lg hover:bg-gray-700 transition flex items-center space-x-2"
          >
            <font-awesome-icon :icon="['fas', 'times']" />
            <span>Cancel</span>
          </button>
          <button
            @click="confirm"
            :disabled="isDeleting"
            class="px-6 py-2 bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center space-x-2"
          >
            <span v-if="isDeleting" class="flex items-center space-x-2">
              <font-awesome-icon :icon="['fas', 'spinner']" class="animate-spin" />
              <span>Deleting...</span>
            </span>
            <span v-else class="flex items-center space-x-2">
              <font-awesome-icon :icon="['fas', 'trash']" />
              <span>Delete Account</span>
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'DeleteConfirmationModal',
  props: {
    show: {
      type: Boolean,
      default: false
    },
    isDeleting: {
      type: Boolean,
      default: false
    }
  },
  emits: ['confirm', 'cancel'],
  setup(props, { emit }) {
    const confirm = () => {
      emit('confirm');
    };

    const cancel = () => {
      emit('cancel');
    };

    return {
      confirm,
      cancel
    };
  }
}
</script>