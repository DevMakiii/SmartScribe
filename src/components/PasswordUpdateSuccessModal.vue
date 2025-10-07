<template>
  <!-- Password Update Success Modal -->
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div class="bg-gray-800 rounded-lg shadow-2xl w-full max-w-md">
      <!-- Modal Header -->
      <div class="flex justify-between items-center p-4 border-b border-gray-700">
        <h2 class="text-xl font-semibold text-white">Password Updated</h2>
        <button @click="close" class="text-gray-400 hover:text-white transition-colors">
          <font-awesome-icon :icon="['fas', 'times']" class="text-lg" />
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6">
        <div class="text-center">
          <div class="mb-4">
            <font-awesome-icon :icon="['fas', 'check-circle']" class="text-4xl text-green-500 mb-4" />
          </div>
          <h3 class="text-lg font-semibold mb-4 text-white">Success!</h3>
          <p class="text-gray-300 mb-6">
            Your password has been successfully updated. You can now use your new password to log in.
          </p>
        </div>

        <div class="flex justify-center">
          <button
            @click="close"
            class="px-6 py-2 bg-blue-600 rounded-lg hover:bg-blue-700 transition flex items-center space-x-2"
          >
            <font-awesome-icon :icon="['fas', 'check']" />
            <span>OK</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PasswordUpdateSuccessModal',
  props: {
    show: {
      type: Boolean,
      default: false
    }
  },
  emits: ['close'],
  setup(props, { emit }) {
    const close = () => {
      emit('close');
    };

    return {
      close
    };
  }
}
</script>