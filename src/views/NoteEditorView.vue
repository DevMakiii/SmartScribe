<template>
  <Header @open-profile-modal="openProfileModal">

    <!-- Mobile Menu Button -->
    <button
      @click="sidebarOpen = !sidebarOpen"
      :class="`md:hidden fixed top-20 left-4 z-50 ${themeClasses.card} p-2 rounded-md shadow-lg`"
    >
      <font-awesome-icon :icon="['fas', sidebarOpen ? 'times' : 'bars']" />
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div
      v-if="sidebarOpen"
      @click="sidebarOpen = false"
      class="md:hidden fixed inset-0 bg-black bg-opacity-50 z-40"
    ></div>

    <!-- Note Editor Main Content -->
    <main :class="`${themeClasses.mainContent} flex-1 p-4 sm:p-6 transition-all duration-300 ease-in-out`">
        <div v-if="isLoading" class="flex justify-center items-center h-full">
          <font-awesome-icon :icon="['fas', 'spinner']" spin class="text-4xl text-blue-500" />
        </div>

        <div v-else-if="error" class="flex flex-col items-center justify-center h-full">
           <font-awesome-icon :icon="['fas', 'times']" class="text-4xl text-red-400 mb-4" />
           <h2 :class="`${themeClasses.text} font-medium mb-2 ${fontSizeClasses.heading}`">Error Loading Note</h2>
           <p :class="`${themeClasses.tertiaryText} mb-4`">{{ error }}</p>
          <router-link to="/notes" class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 transition">
            Back to Notes
          </router-link>
        </div>

        <div v-else>
          <div class="flex flex-col justify-between items-start mb-6 space-y-4" style="flex-direction: column !important;">
            <div class="w-full sm:w-auto flex-1">
              <div class="flex items-center space-x-3 mb-2">
                <h1 class="text-xl sm:text-2xl font-bold flex-1">
                  <input
                    v-model="note.title"
                    :class="`bg-transparent ${themeClasses.border} focus:border-blue-500 focus:outline-none pb-1 w-full ${fontSizeClasses.heading} ${themeClasses.text}`"
                    placeholder="Note Title"
                  />
                </h1>
              </div>
              <p :class="`${themeClasses.tertiaryText} ${fontSizeClasses.label}`">Last edited: {{ note.lastEdited }}</p>
            </div>
            <div class="flex space-x-2 sm:space-x-3 w-full sm:w-auto">
              <button @click="saveNote" :disabled="isSaving" class="flex-1 sm:flex-none px-3 py-2 sm:px-4 sm:py-2 text-sm sm:text-base bg-blue-600 rounded-md hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <font-awesome-icon :icon="isSaving ? ['fas', 'spinner'] : ['fas', 'save']" :spin="isSaving" class="mr-2" />
                {{ isSaving ? 'Saving...' : 'Save' }}
              </button>
              <button @click="showExportOptions = !showExportOptions" :class="`flex-1 sm:flex-none px-3 py-2 sm:px-4 sm:py-2 text-sm sm:text-base ${themeClasses.button} rounded-md transition relative`">
                <font-awesome-icon :icon="['fas', 'file-export']" class="mr-2" /> Export

                <!-- Export Options Dropdown -->
                <div v-if="showExportOptions" :class="`absolute right-0 mt-2 w-48 sm:w-56 ${themeClasses.card} rounded-md shadow-lg py-1 z-10 max-w-full`">
                  <button @click="exportNote('pdf')" :class="`block w-full text-left px-4 py-2 ${themeClasses.hover}`">
                    <font-awesome-icon :icon="['fas', 'file-code']" class="mr-2" /> HTML for PDF conversion
                  </button>
                  <button @click="exportNote('word')" :class="`block w-full text-left px-4 py-2 ${themeClasses.hover}`">
                    <font-awesome-icon :icon="['fas', 'file-word']" class="mr-2" /> Word Document (.doc)
                  </button>
                  <button @click="exportNote('text')" :class="`block w-full text-left px-4 py-2 ${themeClasses.hover}`">
                    <font-awesome-icon :icon="['fas', 'file-alt']" class="mr-2" /> Plain Text (.txt)
                  </button>
                </div>
              </button>
            </div>
          </div>

        <div class="grid grid-cols-1 gap-4 sm:gap-6" style="grid-template-columns: 1fr;">
          <!-- Original Text (OCR Result) -->
          <div :class="`${themeClasses.card} rounded-lg p-4 sm:p-6`">
            <h2 :class="`${themeClasses.text} font-semibold mb-4 ${fontSizeClasses.body}`">Original Text</h2>
            <div class="relative">
              <textarea
                v-model="note.originalText"
                :class="`w-full h-64 sm:h-96 ${themeClasses.input} rounded-lg p-3 sm:p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base overflow-x-hidden resize-none`"
                placeholder="OCR extracted text will appear here"
              ></textarea>
              <div class="absolute top-2 right-2 flex space-x-2">
                <button title="Edit Original Text" :class="`p-1 ${themeClasses.button} rounded`">
                  <font-awesome-icon :icon="['fas', 'edit']" class="text-xs sm:text-sm" />
                </button>
                <button title="Rescan" :class="`p-1 ${themeClasses.button} rounded`">
                  <font-awesome-icon :icon="['fas', 'camera']" class="text-xs sm:text-sm" />
                </button>
              </div>
            </div>

            <!-- Highlighted Text Display -->
            <div v-if="note.keywords.length > 0 && note.originalText" class="mt-4">
              <h3 :class="`${themeClasses.secondaryText} text-sm font-medium mb-2`">Highlighted Keywords:</h3>
              <div
                :class="`w-full min-h-32 max-h-64 ${themeClasses.input} rounded-lg p-3 sm:p-4 text-sm sm:text-base overflow-y-auto border ${themeClasses.border}`"
                v-html="highlightedOriginalText"
              ></div>
            </div>
          </div>

          <!-- AI Generated Summary -->
          <div :class="`${themeClasses.card} rounded-lg p-4 sm:p-6`">
            <div class="flex flex-col justify-between items-start mb-4 space-y-2" style="flex-direction: column !important;">
              <h2 :class="`${themeClasses.text} font-semibold ${fontSizeClasses.body}`">AI Summary</h2>
              <div class="flex items-center space-x-2 w-full sm:w-auto">
                <span :class="`${themeClasses.tertiaryText} text-xs sm:text-sm`">Length:</span>
                <select
                  v-model="summaryLength"
                  :class="`${themeClasses.input} rounded px-2 py-1 sm:p-1 text-xs sm:text-sm flex-1 sm:flex-none`"
                >
                  <option value="auto">Auto</option>
                  <option value="short">Short</option>
                  <option value="medium">Medium</option>
                  <option value="long">Long</option>
                </select>
                <select
                  v-model="summaryFormat"
                  :class="`${themeClasses.input} rounded px-2 py-1 sm:p-1 text-xs sm:text-sm flex-1 sm:flex-none ml-2`"
                >
                  <option value="paragraph">Paragraph</option>
                  <option value="bullet_points">Bullet Points</option>
                </select>
              </div>
            </div>
            <div class="relative">
              <textarea
                v-model="note.summary"
                :class="`w-full h-64 sm:h-96 ${themeClasses.input} rounded-lg p-3 sm:p-4 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base overflow-x-hidden resize-none whitespace-pre-line`"
                placeholder="AI-generated summary will appear here"
                style="white-space: pre-line;"
              ></textarea>
              <div class="absolute top-2 right-2 flex space-x-2">
                <button @click="generateSummary" :disabled="generatingSummary" title="Regenerate Summary" :class="`p-1 ${themeClasses.button} rounded disabled:opacity-50 disabled:cursor-not-allowed`">
                  <font-awesome-icon :icon="['fas', 'sync-alt']" class="text-xs sm:text-sm" :spin="generatingSummary" />
                </button>
                <button title="Copy to Clipboard" :class="`p-1 ${themeClasses.button} rounded`">
                  <font-awesome-icon :icon="['fas', 'copy']" class="text-xs sm:text-sm" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Keywords and Tags -->
        <div :class="`mt-6 ${themeClasses.card} rounded-lg p-4 sm:p-6`">
          <h2 :class="`${themeClasses.text} font-semibold mb-4 ${fontSizeClasses.body}`">Keywords & Tags</h2>
          <div class="flex flex-wrap gap-2 mb-4">
            <span
              v-for="(keyword, index) in note.keywords"
              :key="`keyword-${index}`"
              class="px-2 py-1 sm:px-3 sm:py-1 bg-blue-600 rounded-full text-xs sm:text-sm flex items-center"
            >
              {{ keyword }}
              <button @click="removeKeyword(index)" class="ml-1 sm:ml-2 text-xs">
                <font-awesome-icon :icon="['fas', 'times']" />
              </button>
            </span>
            <input
              v-model="newKeyword"
              @keyup.enter="addKeyword"
              :class="`px-2 py-1 sm:px-3 sm:py-1 ${themeClasses.input} rounded-full text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 flex-1 min-w-0`"
              placeholder="Add keyword..."
            />
          </div>
          <button @click="extractKeywords" :class="`px-3 py-2 sm:px-3 sm:py-1 ${themeClasses.button} rounded text-sm transition w-full sm:w-auto`">
            <font-awesome-icon :icon="['fas', 'magic']" class="mr-1" /> Auto-extract Keywords
          </button>
        </div>
        </div>
      </main>

    <!-- Camera Modal -->
    <CameraModal
      :show="showCameraModal"
      @close="closeCameraModal"
      @photo-captured="handlePhotoCaptured"
    />
  </Header>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { useStore } from 'vuex';
import { useRouter, useRoute } from 'vue-router';
import api from '@/services/api';
import Header from '@/components/Header.vue';
import CameraModal from '@/components/CameraModal.vue';

export default {
  name: 'NoteEditorView',
  components: {
    Header,
    CameraModal
  },
  setup() {
    const store = useStore();
    const router = useRouter();
    const route = useRoute();

    const note = ref({
       title: '',
       lastEdited: new Date().toLocaleString(),
       originalText: '',
       summary: '',
       keywords: []
     });

     const summaryLength = ref('auto');
     const summaryFormat = ref('paragraph');
     const newKeyword = ref('');
     const quizDifficulty = ref('medium');
     const quizQuestionCount = ref('5');
     const quizQuestions = ref([]);
     const isLoading = ref(false);
     const error = ref(null);
     const isSaving = ref(false);
     const generatingSummary = ref(false);
     const showExportOptions = ref(false);
     const sidebarOpen = ref(false);
     const showCameraModal = ref(false);

     // Use global theme classes from store
     const themeClasses = computed(() => {
       try {
         const classes = store.getters['app/getThemeClasses'];
         return classes && typeof classes === 'object' ? classes : {
           main: 'bg-gray-900 text-white',
           header: 'bg-gray-800',
           sidebar: 'bg-gray-800',
           mainContent: '',
           card: 'bg-gray-800',
           text: 'text-white',
           secondaryText: 'text-gray-400',
           input: 'bg-gray-700 border-gray-600 text-white',
           button: 'bg-gray-700 hover:bg-gray-600'
         };
       } catch (error) {
         return {
           main: 'bg-gray-900 text-white',
           header: 'bg-gray-800',
           sidebar: 'bg-gray-800',
           mainContent: '',
           card: 'bg-gray-800',
           text: 'text-white',
           secondaryText: 'text-gray-400',
           input: 'bg-gray-700 border-gray-600 text-white',
           button: 'bg-gray-700 hover:bg-gray-600'
         };
       }
     });

     // Use global font size classes from store
     const fontSizeClasses = computed(() => {
       try {
         return store.getters['app/getFontSizeClasses'];
       } catch (error) {
         return {
           heading: 'text-xl',
           body: 'text-base',
           label: 'text-sm',
           small: 'text-xs'
         };
       }
     });

   // Computed property to highlight keywords in the original text
   const highlightedOriginalText = computed(() => {
     if (!note.value.originalText || note.value.keywords.length === 0) {
       return note.value.originalText;
     }

     let text = note.value.originalText;
     const keywords = note.value.keywords.filter(keyword => keyword.trim() !== '');

     if (keywords.length === 0) {
       return text;
     }

     // Sort keywords by length (longest first) to handle overlapping keywords
     const sortedKeywords = keywords.sort((a, b) => b.length - a.length);

     // Create a regex pattern for all keywords (case-insensitive)
     const keywordPattern = sortedKeywords.map(keyword =>
       keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') // Escape special regex characters
     ).join('|');

     const regex = new RegExp(`(${keywordPattern})`, 'gi');

     // Replace matches with highlighted spans
     return text.replace(regex, '<span class="keyword-highlight">$1</span>');
   });

   onMounted(async () => {
      try {
        const noteId = route.query.id;

        if (noteId) {
          // Fetch the actual note data from API
          isLoading.value = true;
          const response = await api.getNote(noteId);

          if (response.data.success && response.data.data) {
            const noteData = response.data.data;

            note.value = {
              id: noteData.id,
              title: noteData.title || '',
              lastEdited: noteData.last_edited || new Date().toLocaleString(),
              originalText: noteData.original_text || '',
              summary: noteData.summary || '',
              keywords: noteData.keywords ? noteData.keywords.split(',').map(k => k.trim()) : [],
              summaryFormat: noteData.summary_format || 'paragraph'
            };
          } else {
            error.value = response.data?.error || 'Note not found';
          }
        } else {
          // Check if we have temp image data from OCR
          const tempImageData = store.getters['notes/getTempImageData'];
          if (tempImageData) {
            note.value.originalText = tempImageData.originalText;
            // Generate summary automatically
            generateSummary();
          }
        }
      } catch (error) {
        console.error('Error loading note:', error);
        error.value = 'Failed to load note. Please try again.';
      } finally {
        isLoading.value = false;
      }
    });

    const saveNote = async () => {
      console.log('🔄 saveNote called - Current state:', {
        isSaving: isSaving.value,
        noteId: note.value.id,
        title: note.value.title,
        timestamp: new Date().toISOString()
      });

      // Prevent multiple concurrent saves
      if (isSaving.value) {
        console.log('⚠️ Save already in progress, ignoring duplicate save request');
        return;
      }

      try {
        // Check if user is authenticated
        const userData = localStorage.getItem('user');
        if (!userData) {
          alert('You must be logged in to save notes. Please log in and try again.');
          router.push('/login');
          return;
        }

        let user;
        try {
          user = JSON.parse(userData);
        } catch (e) {
          console.error('Invalid user data in localStorage:', e);
          alert('Authentication data is corrupted. Please log in again.');
          localStorage.removeItem('user');
          localStorage.removeItem('token');
          router.push('/login');
          return;
        }

        if (!user || !user.id) {
          alert('Invalid user session. Please log in again.');
          router.push('/login');
          return;
        }

        if (!note.value.title.trim()) {
          alert('Please enter a title for your note.');
          return;
        }

        if (!note.value.originalText.trim()) {
          alert('Please add some content to your note.');
          return;
        }

        // Set saving state
        isSaving.value = true;
        console.log('✅ Saving state set to true');

        // Update last edited timestamp
        note.value.lastEdited = new Date().toLocaleString();

        // Prepare note data for API
        const noteData = {
          title: note.value.title,
          text: note.value.originalText,
          summary: note.value.summary,
          keywords: note.value.keywords.join(',')
        };

        console.log('📝 Saving note:', {
          hasId: !!note.value.id,
          noteId: note.value.id,
          noteData: noteData,
          userId: user.id
        });

        // Log current localStorage state for debugging
        console.log('🔑 Current localStorage state:', {
          token: !!localStorage.getItem('token'),
          user: localStorage.getItem('user')
        });

        if (note.value.id) {
          // Update existing note
          console.log('🔄 Updating existing note with ID:', note.value.id);
          const response = await api.updateNote(note.value.id, noteData);
          console.log('📤 Update response:', response.data);

          if (response.data.success) {
            console.log('✅ Note updated successfully!');
            alert('Note updated successfully!');
            router.push('/notes?refresh=true');
          } else {
            throw new Error(response.data.error || 'Failed to update note');
          }
        } else {
          // Create new note
          console.log('🆕 Creating new note');
          const response = await api.createNote(noteData);
          console.log('📤 Create response:', response.data);

          if (response.data.success) {
            console.log('✅ Note created successfully with ID:', response.data.note_id || response.data.data?.note_id);
            note.value.id = response.data.note_id || response.data.data?.note_id;
            alert('Note saved successfully!');
            router.push('/notes?refresh=true');
          } else {
            throw new Error(response.data.error || 'Failed to save note');
          }
        }
      } catch (error) {
        console.error('Error saving note:', error);

        // Provide more specific error messages
        let errorMessage = 'Failed to save note. Please try again.';

        if (error.response) {
          if (error.response.status === 401) {
            errorMessage = 'Authentication failed. Please log in again.';
          } else if (error.response.status === 403) {
            errorMessage = 'You do not have permission to perform this action.';
          } else if (error.response.data?.error) {
            errorMessage = error.response.data.error;
          }
        } else if (error.message) {
          errorMessage = error.message;
        }

        alert(errorMessage);
      } finally {
        // Reset saving state
        isSaving.value = false;
      }
    };

    const generateSummary = async () => {
      if (!note.value.originalText.trim()) {
        alert('Please add some text to summarize first.');
        return;
      }

      generatingSummary.value = true;
      try {
        // If we have a note ID (editing existing note), use the API
        if (note.value.id) {
          console.log('Generating summary for existing note ID:', note.value.id);
          const response = await api.createSummary(note.value.id, { length: summaryLength.value, format: summaryFormat.value });
          console.log('Summary API response:', response);
          if (response.data.success) {
            // Refresh the note data to get the updated summary
            const noteResponse = await api.getNote(note.value.id);
            if (noteResponse.data.success) {
              note.value.summary = noteResponse.data.data.summary;
            }
            alert('Summary generated successfully!');
          } else {
            alert('Failed to generate summary: ' + (response.data.error || 'Unknown error'));
          }
        } else {
          // For new notes, use the direct GPT service
          console.log('Generating summary for new note');
          const response = await api.gpt.generateSummary(note.value.originalText, { length: summaryLength.value });
          console.log('GPT Summary API response:', response);
          if (response.data) {
            note.value.summary = response.data;
            alert('Summary generated successfully!');
          } else {
            alert('Failed to generate summary. Please try again.');
          }
        }
      } catch (error) {
        console.error('Error generating summary:', error);
        let errorMessage = 'Failed to generate summary. Please try again.';

        if (error.response) {
          if (error.response.status === 401) {
            errorMessage = 'Authentication required. Please log in to generate summaries.';
          } else if (error.response.status === 403) {
            errorMessage = 'Access denied. You do not have permission to generate summaries.';
          } else if (error.response.data?.error) {
            errorMessage = 'Summary generation failed: ' + error.response.data.error;
          } else if (error.response.data?.message) {
            errorMessage = 'Summary generation failed: ' + error.response.data.message;
          }
        } else if (error.message) {
          errorMessage = 'Network error: ' + error.message;
        }

        alert(errorMessage);
      } finally {
        generatingSummary.value = false;
      }
    };

    const addKeyword = () => {
      if (newKeyword.value.trim()) {
        note.value.keywords.push(newKeyword.value.trim());
        newKeyword.value = '';
      }
    };

    const removeKeyword = (index) => {
      note.value.keywords.splice(index, 1);
    };

    const extractKeywords = () => {
      console.log('Extracting keywords from text');
      // In a real app, you would call the GPT API here
      // For now, we'll simulate it

      // Simulate API delay
      setTimeout(() => {
        const extractedKeywords = ['Membrane', 'Cytoplasm', 'Organelles', 'Nucleus'];
        extractedKeywords.forEach(keyword => {
          if (!note.value.keywords.includes(keyword)) {
            note.value.keywords.push(keyword);
          }
        });
      }, 1000);
    };

    const generateQuiz = () => {
      console.log('Generating quiz with difficulty:', quizDifficulty.value, 'and', quizQuestionCount.value, 'questions');
      // In a real app, you would call the GPT API here
      // For now, we'll simulate it

      // Simulate API delay
      setTimeout(() => {
        quizQuestions.value = [
          {
            text: 'Who discovered cells in 1665?',
            options: ['Robert Hooke', 'Anton van Leeuwenhoek', 'Matthias Schleiden', 'Theodor Schwann'],
            correctAnswer: 0,
            selectedAnswer: null
          },
          {
            text: 'What is the study of cells called?',
            options: ['Microbiology', 'Histology', 'Cytology', 'Physiology'],
            correctAnswer: 2,
            selectedAnswer: null
          },
          {
            text: 'What is the typical size range of most plant and animal cells?',
            options: ['0.1-1 micrometers', '1-100 micrometers', '100-1000 micrometers', '1-10 millimeters'],
            correctAnswer: 1,
            selectedAnswer: null
          },
          {
            text: 'What encloses the cytoplasm in a cell?',
            options: ['Cell wall', 'Nucleus', 'Membrane', 'Ribosome'],
            correctAnswer: 2,
            selectedAnswer: null
          },
          {
            text: 'Cells are often referred to as the:',
            options: ['Essence of life', 'Building blocks of life', 'Foundation of organisms', 'Microscopic life'],
            correctAnswer: 1,
            selectedAnswer: null
          }
        ];
      }, 1500);
    };

    const checkQuizAnswers = () => {
      let correctCount = 0;
      quizQuestions.value.forEach(question => {
        if (question.selectedAnswer === question.correctAnswer) {
          correctCount++;
        }
      });

      alert(`You got ${correctCount} out of ${quizQuestions.value.length} questions correct!`);
    };

    const resetQuiz = () => {
      quizQuestions.value.forEach(question => {
        question.selectedAnswer = null;
      });
    };

    const exportNote = async (format) => {
      if (!note.value || !note.value.id) {
        alert('Please save the note first before exporting');
        return;
      }

      try {
        console.log(`Exporting note ${note.value.id} as ${format}...`);

        // Map frontend format names to backend format names
        const backendFormat = format === 'word' ? 'docx' : format === 'text' ? 'txt' : format;
        const fileExtension = format === 'pdf' ? 'html' : format === 'word' ? 'doc' : format === 'text' ? 'txt' : format;

        console.log(`Making API call to export with backendFormat: ${backendFormat}`);
        const response = await api.exportNote(note.value.id, backendFormat);
        console.log('API response received:', response);
        console.log('Response data type:', typeof response.data);
        console.log('Response data length:', response.data ? response.data.length : 'N/A');

        // Create blob and download
        const mimeType = backendFormat === 'pdf' ? 'text/html' :
                        backendFormat === 'docx' ? 'application/msword' :
                        'text/plain';

        console.log(`Creating blob with MIME type: ${mimeType}`);
        const blob = new Blob([response.data], { type: mimeType });
        console.log('Blob created:', blob);
        console.log('Blob size:', blob.size);

        const url = window.URL.createObjectURL(blob);
        console.log('Blob URL created:', url);

        const link = document.createElement('a');
        link.href = url;
        const baseFilename = note.value.title.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const filename = format === 'pdf' ? `${baseFilename}_for_pdf.${fileExtension}` : `${baseFilename}_export.${fileExtension}`;
        link.download = filename;
        console.log('Download filename:', filename);

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        console.log(`Note exported as ${format} successfully`);
        alert(`File exported successfully! Check your downloads folder for "${filename}"`);
      } catch (error) {
        console.error('Export error:', error);
        console.error('Error details:', error.response || error.message);
        alert(`Failed to export note as ${format.toUpperCase()}. Please try again. Error: ${error.message}`);
      }
    };

    const showProfileModal = ref(false);

    const openProfileModal = () => {
      showProfileModal.value = true;
    };

    const closeProfileModal = () => {
      showProfileModal.value = false;
    };

    const openCamera = () => {
      showCameraModal.value = true;
    };

    const closeCameraModal = () => {
      showCameraModal.value = false;
    };

    const handlePhotoCaptured = (photoData) => {
      // Update the note's original text with the captured OCR text
      if (photoData && photoData.originalText) {
        note.value.originalText = photoData.originalText;
        // Optionally generate summary automatically
        generateSummary();
      }
      // Close the modal
      showCameraModal.value = false;
    };

    return {
      note,
      summaryLength,
      summaryFormat,
      newKeyword,
      quizDifficulty,
      quizQuestionCount,
      quizQuestions,
      isLoading,
      error,
      isSaving,
      generatingSummary,
      showExportOptions,
      sidebarOpen,
      themeClasses,
      fontSizeClasses,
      highlightedOriginalText,
      saveNote,
      generateSummary,
      addKeyword,
      removeKeyword,
      extractKeywords,
      generateQuiz,
      checkQuizAnswers,
      resetQuiz,
      exportNote,
      showProfileModal,
      openProfileModal,
      closeProfileModal,
      showCameraModal,
      openCamera,
      closeCameraModal,
      handlePhotoCaptured
    };
  }
}
</script>

<style scoped>
.keyword-highlight {
  background-color: rgba(59, 130, 246, 0.3);
  color: #fbbf24;
  padding: 2px 4px;
  border-radius: 3px;
  font-weight: 600;
  border: 1px solid rgba(59, 130, 246, 0.5);
  box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.2);
}
</style>