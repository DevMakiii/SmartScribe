import api from './api';

/**
 * OCR Service for handling image processing and text extraction
 */
class OCRService {
  /**
   * Process an image file and extract text using OCR
   * @param {File} imageFile - The image file to process
   * @returns {Promise<Object>} - The OCR result with extracted text
   */
  async processImage(imageFile) {
    try {
      // Create form data
      const formData = new FormData();
      formData.append('image', imageFile);

      // Send to API
      const response = await api.ocr.processImage(formData);
      return response.data;
    } catch (error) {
      console.error('OCR processing error:', error);
      throw new Error('Failed to process image. Please try again.');
    }
  }



  /**
   * Check if a file is an image
   * @param {File} file - The file to check
   * @returns {boolean} - Whether the file is an image
   */
  isImage(file) {
    return file && file.type.startsWith('image/');
  }
  
  /**
   * Process an image from camera
   * @param {Blob} imageBlob - The image blob from camera
   * @returns {Promise<Object>} - The OCR result with extracted text
   */
  async processCameraImage(imageBlob) {
    try {
      // Convert blob to file
      const imageFile = new File([imageBlob], 'camera-image.jpg', { type: 'image/jpeg' });
      return await this.processImage(imageFile);
    } catch (error) {
      console.error('Camera OCR processing error:', error);
      throw new Error('Failed to process camera image. Please try again.');
    }
  }
  
  /**
   * Check if the device has a camera
   * @returns {Promise<boolean>} - Whether the device has a camera
   */
  async hasCamera() {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      return devices.some(device => device.kind === 'videoinput');
    } catch (error) {
      console.error('Camera detection error:', error);
      return false;
    }
  }
  
  /**
   * Open the camera and take a photo
   * @returns {Promise<Blob>} - The image blob
   */
  async takePhoto() {
    return new Promise((resolve, reject) => {
      (async () => {
        try {
          // Check if camera is available
          if (!(await this.hasCamera())) {
            throw new Error('No camera detected on this device.');
          }
        
        // Create video element
        const video = document.createElement('video');
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        video.setAttribute('muted', '');
        
        // Get user media
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' }
        });
        
        video.srcObject = stream;
        
        // Wait for video to be ready
        video.onloadedmetadata = () => {
          video.play();
          
          // Create canvas to capture image
          const canvas = document.createElement('canvas');
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;
          
          // Draw video frame to canvas
          const context = canvas.getContext('2d');
          context.drawImage(video, 0, 0, canvas.width, canvas.height);
          
          // Stop all video streams
          stream.getTracks().forEach(track => track.stop());
          
          // Convert canvas to blob
          canvas.toBlob(blob => {
            resolve(blob);
          }, 'image/jpeg', 0.95);
        };
      } catch (error) {
        console.error('Camera access error:', error);
        reject(new Error('Failed to access camera. Please check permissions.'));
      }
      })();
    });
  }
}

export default new OCRService();