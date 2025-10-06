<template>
  <div class="min-h-screen flex flex-col bg-gray-900 text-white">
    <!-- Header -->
    <header class="p-4 bg-gray-800 flex justify-between items-center">
      <div class="flex items-center space-x-3">
        <button @click="goBack" class="text-gray-400 hover:text-white">
          <font-awesome-icon :icon="['fas', 'angle-left']" class="mr-2" />
          Back to Dashboard
        </button>
        <div class="text-lg md:text-xl font-bold">SmartScribe - Camera</div>
      </div>
    </header>

    <!-- Main Content -->
    <div class="flex-1 p-4 md:p-6">
      <div class="max-w-4xl mx-auto">
        <div class="bg-gray-800 rounded-lg p-6">
          <h1 class="text-2xl font-bold mb-6 text-center">Take Photo</h1>

          <!-- Camera Interface -->
          <div v-if="!capturedImage" class="space-y-6">
            <div class="relative">
              <video
                ref="video"
                autoplay
                playsinline
                class="w-full rounded-lg border-2 border-gray-600"
                style="max-height: 60vh;"
              ></video>
              <div v-if="!streamActive" class="absolute inset-0 flex items-center justify-center bg-gray-700 rounded-lg">
                <div class="text-center">
                  <font-awesome-icon :icon="['fas', 'camera']" class="text-4xl text-gray-400 mb-4" />
                  <p class="text-gray-400">Initializing camera...</p>
                </div>
              </div>
            </div>

            <div class="flex justify-center space-x-4">
              <button
                @click="capturePhoto"
                :disabled="!streamActive"
                class="px-6 py-3 bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center space-x-2"
              >
                <font-awesome-icon :icon="['fas', 'camera-retro']" />
                <span>Capture Photo</span>
              </button>
              <button
                @click="goBack"
                class="px-6 py-3 bg-gray-600 rounded-lg hover:bg-gray-700 transition flex items-center space-x-2"
              >
                <font-awesome-icon :icon="['fas', 'times']" />
                <span>Cancel</span>
              </button>
            </div>
          </div>

          <!-- Captured Image Preview -->
          <div v-else class="space-y-6">
            <div class="text-center">
              <h2 class="text-xl font-semibold mb-4">Photo Captured!</h2>
              <img
                :src="capturedImage"
                alt="Captured photo"
                class="max-w-full max-h-96 mx-auto rounded-lg border-2 border-gray-600"
              />
            </div>

            <div class="flex justify-center space-x-4">
              <button
                @click="processCapturedImage"
                :disabled="isProcessing"
                class="px-6 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center space-x-2"
              >
                <span v-if="isProcessing" class="flex items-center space-x-2">
                  <font-awesome-icon :icon="['fas', 'spinner']" class="animate-spin" />
                  <span>Processing...</span>
                </span>
                <span v-else class="flex items-center space-x-2">
                  <font-awesome-icon :icon="['fas', 'magic']" />
                  <span>Extract Text & Create Note</span>
                </span>
              </button>
              <button
                @click="retakePhoto"
                :disabled="isProcessing"
                class="px-6 py-3 bg-gray-600 rounded-lg hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center space-x-2"
              >
                <font-awesome-icon :icon="['fas', 'redo']" />
                <span>Retake</span>
              </button>
              <button
                @click="goBack"
                :disabled="isProcessing"
                class="px-6 py-3 bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center space-x-2"
              >
                <font-awesome-icon :icon="['fas', 'times']" />
                <span>Cancel</span>
              </button>
            </div>
          </div>

          <!-- Processing Status -->
          <div v-if="isProcessing" class="mt-6 p-4 bg-blue-900/20 rounded-lg border border-blue-500/30">
            <div class="flex items-center space-x-2">
              <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
              <span class="text-sm text-blue-400">Extracting text from image...</span>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="mt-6 p-4 bg-red-900/20 rounded-lg border border-red-500/30">
            <div class="flex items-center space-x-2">
              <font-awesome-icon :icon="['fas', 'triangle-exclamation']" class="text-red-500" />
              <span class="text-sm text-red-400">{{ error }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import Tesseract from 'tesseract.js';

export default {
  name: 'CameraView',
  setup() {
    const router = useRouter();
    const video = ref(null);
    const streamActive = ref(false);
    const capturedImage = ref(null);
    const isProcessing = ref(false);
    const error = ref(null);

    let stream = null;

    const startCamera = async () => {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: 'environment', // Use back camera on mobile
            width: { ideal: 1280 },
            height: { ideal: 720 }
          }
        });

        if (video.value) {
          video.value.srcObject = stream;
          streamActive.value = true;
        }
      } catch (err) {
        console.error('Error accessing camera:', err);
        error.value = 'Unable to access camera. Please check permissions and try again.';
      }
    };

    const stopCamera = () => {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
      }
      streamActive.value = false;
    };

    const capturePhoto = () => {
      if (!video.value || !streamActive.value) return;

      const canvas = document.createElement('canvas');
      canvas.width = video.value.videoWidth;
      canvas.height = video.value.videoHeight;

      const ctx = canvas.getContext('2d');
      ctx.drawImage(video.value, 0, 0);

      capturedImage.value = canvas.toDataURL('image/jpeg', 0.8);
      stopCamera();
    };

    const retakePhoto = () => {
      capturedImage.value = null;
      error.value = null;
      startCamera();
    };

    const processCapturedImage = async () => {
      if (!capturedImage.value) return;

      isProcessing.value = true;
      error.value = null;

      try {
        // Convert data URL to blob
        const response = await fetch(capturedImage.value);
        const blob = await response.blob();

        // Perform OCR
        const { data: { text } } = await Tesseract.recognize(blob, 'eng', {
          logger: () => {} // Disable logging
        });

        if (text && text.trim()) {
          // Store the extracted text and navigate to note editor
          const extractedData = {
            type: 'camera',
            originalText: text.trim(),
            imageData: capturedImage.value
          };

          // Store in session storage to pass to note editor
          sessionStorage.setItem('cameraData', JSON.stringify(extractedData));

          // Navigate to note editor
          router.push('/notes/edit?from=camera');
        } else {
          error.value = 'No text was found in the image. Please try taking a clearer photo.';
        }
      } catch (err) {
        console.error('Error processing image:', err);
        error.value = 'Failed to process the image. Please try again.';
      } finally {
        isProcessing.value = false;
      }
    };

    const goBack = () => {
      stopCamera();
      router.push('/dashboard');
    };

    onMounted(() => {
      startCamera();
    });

    onUnmounted(() => {
      stopCamera();
    });

    return {
      video,
      streamActive,
      capturedImage,
      isProcessing,
      error,
      capturePhoto,
      retakePhoto,
      processCapturedImage,
      goBack
    };
  }
}
</script>