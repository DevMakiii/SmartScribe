<template>
  <Header @open-profile-modal="openProfileModal">
      <!-- Quiz Main Content -->
      <main :class="[
        'flex-1 p-4 sm:p-6 ml-0 md:ml-0 transition-all duration-300 ease-in-out',
        store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-900' : 'bg-white'
      ]" style="width: 100vw; max-width: 100vw;">
        <div v-if="isLoading" class="flex justify-center items-center h-full">
          <font-awesome-icon :icon="['fas', 'spinner']" spin class="text-3xl sm:text-4xl text-blue-500" />
        </div>

        <div v-else-if="error" class="flex flex-col items-center justify-center h-full">
          <font-awesome-icon :icon="['fas', 'times']" class="text-4xl text-red-400 mb-4" />
          <h2 class="text-xl font-medium mb-2">Error Loading Quiz</h2>
          <p :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'" class="mb-4">{{ error }}</p>
          <router-link to="/notes" class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 transition">
            Back to Notes
          </router-link>
        </div>

        <div v-else>
          <!-- Quiz Management Header -->
          <div class="mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
              <div>
                <h1 :class="[
                  'font-bold mb-2',
                  fontSizeClasses.heading,
                  store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
                ]">Quizzes</h1>
                <p :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'">Create and manage your study quizzes</p>
              </div>
              <div class="flex space-x-3 mt-4 sm:mt-0">
                <button @click="openQuizOptions" class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 transition" :disabled="isGeneratingQuiz">
                  <font-awesome-icon :icon="['fas', 'plus']" class="mr-2" />
                  {{ isGeneratingQuiz ? 'Generating...' : 'Create New Quiz' }}
                </button>
                <router-link to="/notes" :class="[
                  'px-4 py-2 rounded-md transition',
                  store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'bg-gray-600 hover:bg-gray-700'
                    : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
                ]">
                  <font-awesome-icon :icon="['fas', 'angle-left']" class="mr-2" />
                  Back to Notes
                </router-link>
              </div>
            </div>
          </div>




          <!-- Saved Quizzes Section -->
          <div :class="[
            'rounded-lg p-6',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-200'
          ]">
            <div class="flex justify-between items-center mb-6">
              <h3 :class="[
                'font-semibold',
                fontSizeClasses.heading,
                store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
              ]">Your Quizzes</h3>
              <div class="flex space-x-2">
                <button @click="debugQuizStatus" :class="[
                  'text-sm',
                  store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'text-gray-400 hover:text-gray-300'
                    : 'text-gray-600 hover:text-gray-800'
                ]">
                  <font-awesome-icon :icon="['fas', 'info-circle']" class="mr-1" />
                  Debug
                </button>
                <button @click="testQuizGeneration" class="text-blue-400 hover:text-blue-300 text-sm">
                  <font-awesome-icon :icon="['fas', 'flask']" class="mr-1" />
                  Test Quiz
                </button>
                <button @click="loadSavedQuizzes" class="text-blue-400 hover:text-blue-300 text-sm">
                  <font-awesome-icon :icon="['fas', 'sync-alt']" class="mr-1" />
                  Refresh
                </button>
              </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoadingSavedQuizzes" class="flex justify-center py-8">
              <font-awesome-icon :icon="['fas', 'spinner']" spin class="text-2xl text-blue-500" />
              <span :class="[
                'ml-2',
                store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
              ]">Loading your quizzes...</span>
            </div>

            <!-- Empty State -->
            <div v-else-if="savedQuizzes.length === 0" :class="[
              'text-center py-12',
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
            ]">
              <font-awesome-icon :icon="['fas', 'book-open']" class="text-6xl mb-6" />
              <h3 :class="[
                'font-semibold mb-4',
                fontSizeClasses.heading,
                store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
              ]">No Quizzes Yet</h3>
              <p :class="[
                'mb-6',
                store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
              ]">Create your first quiz to start studying!</p>
              <button @click="openNoteSelector" class="px-6 py-3 bg-blue-600 rounded-md hover:bg-blue-700 transition" :disabled="isGeneratingQuiz">
                <font-awesome-icon :icon="['fas', 'plus']" class="mr-2" />
                {{ isGeneratingQuiz ? 'Generating...' : 'Create Your First Quiz' }}
              </button>
            </div>

            <!-- Saved Quizzes List -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <div
                v-for="quiz in savedQuizzes"
                :key="quiz.id"
                :class="[
                  'rounded-lg p-5 transition-all duration-200 cursor-pointer transform hover:scale-105 relative',
                  store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'bg-gray-700 hover:bg-gray-600'
                    : 'bg-white border border-gray-200 shadow-sm hover:shadow-md hover:bg-gray-50'
                ]"
                @click="startQuiz(quiz.id)"
              >
                <!-- Completion Status Indicator -->
                <div v-if="quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0" class="absolute top-2 right-2 w-3 h-3 bg-green-500 rounded-full" title="Completed"></div>
                <div v-else class="absolute top-2 right-2 w-3 h-3 bg-gray-500 rounded-full" title="Not taken"></div>
                <div class="flex items-start justify-between mb-4">
                  <div class="flex-1">
                    <h4 :class="[
                      'font-semibold mb-2',
                      fontSizeClasses.body,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
                    ]">{{ quiz.title }}</h4>
                    <div :class="[
                      'mb-2',
                      fontSizeClasses.label,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                    ]">
                      <span class="text-xs">ID: {{ quiz.id }}</span>
                    </div>
                    <p :class="[
                      'mb-2',
                      fontSizeClasses.label,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                    ]">
                      Created: {{ new Date(quiz.created_at).toLocaleDateString() }}
                    </p>
                    <div :class="[
                      'flex items-center',
                      fontSizeClasses.label,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-600'
                    ]">
                      <font-awesome-icon :icon="['fas', 'question-circle']" class="mr-1" />
                      {{ quiz.questionCount }} questions
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-2xl font-bold mb-1" :class="quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0 ? (quiz.accuracy >= 80 ? 'text-green-400' : quiz.accuracy >= 60 ? 'text-yellow-400' : 'text-red-400') : 'text-gray-500'">
                      {{ quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0 ? quiz.accuracy + '%' : 'Not taken' }}
                    </div>
                    <div :class="[
                      'mb-2',
                      fontSizeClasses.small,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                    ]">{{ quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0 ? 'Accuracy' : 'Status' }}</div>
                    <div class="text-sm">
                      <span class="text-green-400 font-medium">{{ (quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0) ? quiz.score : 0 }}</span>
                      <span :class="store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-500' : 'text-gray-400'" class="mx-1">/</span>
                      <span class="text-red-400 font-medium">{{ (quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0) ? (quiz.questionCount - quiz.score) : 0 }}</span>
                    </div>
                    <div :class="[
                      '',
                      fontSizeClasses.small,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                    ]">Correct/Incorrect</div>
                  </div>
                </div>

                <!-- Performance Analysis -->
                <div :class="[
                  'rounded-lg p-3 mb-4',
                  store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-600' : 'bg-gray-100'
                ]">
                  <h5 :class="[
                    'font-semibold mb-2',
                    fontSizeClasses.label,
                    store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-200' : 'text-gray-800'
                  ]">Performance Statistics</h5>
                  <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                      <div class="text-lg font-bold text-green-400">{{ (quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0) ? quiz.score : 0 }}</div>
                      <div :class="[
                        '',
                        fontSizeClasses.small,
                        store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                      ]">Correct</div>
                    </div>
                    <div>
                      <div class="text-lg font-bold text-red-400">{{ (quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0) ? (quiz.questionCount - quiz.score) : 0 }}</div>
                      <div :class="[
                        '',
                        fontSizeClasses.small,
                        store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                      ]">Incorrect</div>
                    </div>
                    <div>
                      <div class="text-lg font-bold text-blue-400">{{ quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0 ? (quiz.accuracy || 0) + '%' : 'N/A' }}</div>
                      <div :class="[
                        '',
                        fontSizeClasses.small,
                        store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                      ]">Accuracy</div>
                    </div>
                  </div>
                  <div :class="[
                    'mt-2 pt-2 border-t',
                    store.getters['app/getCurrentTheme'] === 'dark' ? 'border-gray-500' : 'border-gray-300'
                  ]">
                    <div :class="[
                      'text-center',
                      fontSizeClasses.small,
                      store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-600'
                    ]">
                      <span class="font-medium">Difficulty:</span> {{ quiz.difficulty || 'Medium' }}
                      <span class="mx-2">•</span>
                      <span class="font-medium">Type:</span> {{ getQuizTypeDisplay(quiz.quiz_type || 'multiple_choice') }}
                      <span class="mx-2">•</span>
                      <span class="font-medium">Questions:</span> {{ quiz.questionCount || 0 }}
                    </div>
                  </div>
                </div>

                <div class="flex items-center justify-between">
                  <span :class="[
                    '',
                    fontSizeClasses.label,
                    store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                  ]">
                    Difficulty: {{ quiz.difficulty || 'Medium' }}
                  </span>
                  <div class="flex space-x-2">
                    <button
                      @click.stop="startQuiz(quiz.id)"
                      class="px-3 py-1 bg-gray-500 text-white text-sm rounded-md hover:bg-gray-600 transition"
                    >
                      <font-awesome-icon :icon="['fas', 'play']" class="mr-1" />
                      Take Quiz
                    </button>
                    <button
                      @click.stop="confirmDeleteQuiz(quiz.id)"
                      class="px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition"
                      title="Delete Quiz"
                    >
                      <font-awesome-icon :icon="['fas', 'trash']" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>

  <!-- Delete Confirmation Modal -->
  <div v-if="showDeleteConfirmation" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div :class="[
      'rounded-lg p-6 w-full max-w-md mx-4',
      store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-200'
    ]">
        <div class="flex items-center mb-4">
          <div class="ml-4">
            <h3 :class="[
              'font-semibold',
              fontSizeClasses.body,
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
            ]">Delete Quiz</h3>
            <p :class="[
              'mt-1',
              fontSizeClasses.label,
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
            ]">Are you sure you want to delete this quiz? This action cannot be undone.</p>
          </div>
        </div>

        <div class="flex justify-end space-x-3">
          <button @click="cancelDelete" :class="[
            'px-4 py-2 rounded-md transition',
            store.getters['app/getCurrentTheme'] === 'dark'
              ? 'bg-gray-600 text-white hover:bg-gray-700'
              : 'bg-gray-200 text-gray-900 hover:bg-gray-300'
          ]">
            Cancel
          </button>
          <button @click="proceedDelete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition">
            Delete Quiz
          </button>
        </div>
      </div>
    </div>

    <!-- Note Selector Modal -->
    <div v-if="showNoteSelector" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div :class="[
        'rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[80vh] overflow-y-auto',
        store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-200'
      ]">
        <div class="flex justify-between items-center mb-6">
          <h2 :class="[
            'font-semibold',
            fontSizeClasses.heading,
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
          ]">Select Notes for Quiz Generation</h2>
          <button @click="closeNoteSelector" :class="[
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-gray-700'
          ]">
            <font-awesome-icon :icon="['fas', 'times']" class="text-xl" />
          </button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoadingNotes" class="flex justify-center py-8">
          <font-awesome-icon :icon="['fas', 'spinner']" spin class="text-2xl text-blue-500" />
          <span :class="[
            'ml-2',
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
          ]">Loading your notes...</span>
        </div>

        <!-- Notes List -->
        <div v-else-if="availableNotes.length > 0" class="space-y-4">
          <!-- Selection Controls -->
          <div class="flex justify-between items-center mb-4">
            <div :class="[
              '',
              fontSizeClasses.label,
              store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
            ]">
              {{ selectedNotes.length }} of {{ availableNotes.length }} notes selected
            </div>
            <div class="flex space-x-2">
              <button @click="selectAllNotes" class="px-3 py-1 text-sm bg-blue-600 rounded-md hover:bg-blue-700 transition">
                Select All
              </button>
              <button @click="deselectAllNotes" :class="[
                'px-3 py-1 text-sm rounded-md transition',
                store.getters['app/getCurrentTheme'] === 'dark'
                  ? 'bg-gray-600 hover:bg-gray-700 text-white'
                  : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
              ]">
                Deselect All
              </button>
            </div>
          </div>

          <!-- Notes Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-96 overflow-y-auto">
            <div
              v-for="note in availableNotes"
              :key="note.id"
              :class="[
                'rounded-lg p-4 cursor-pointer transition-all',
                note.selected
                  ? 'ring-2 ring-blue-500 ' + (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-600' : 'bg-blue-50')
                  : (store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-700 hover:bg-gray-600' : 'bg-white border border-gray-200 hover:bg-gray-50')
              ]"
              @click="toggleNoteSelection(note.id)"
            >
              <div class="flex items-start space-x-3">
                <input
                  type="checkbox"
                  :checked="note.selected"
                  class="mt-1 text-blue-600 focus:ring-blue-500"
                  readonly
                />
                <div class="flex-1">
                  <h3 :class="[
                    'font-medium mb-2',
                    fontSizeClasses.body,
                    store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
                  ]">{{ note.title || 'Untitled Note' }}</h3>
                  <p :class="[
                    'mb-2 line-clamp-2',
                    fontSizeClasses.label,
                    store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-600'
                  ]">
                    {{ note.original_text ? note.original_text.substring(0, 100) + '...' : 'No content available' }}
                  </p>
                  <div :class="[
                    '',
                    fontSizeClasses.small,
                    store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-500'
                  ]">
                    Created: {{ new Date(note.created_at).toLocaleDateString() }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else :class="[
          'text-center py-8',
          store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400' : 'text-gray-600'
        ]">
          <font-awesome-icon :icon="['fas', 'book']" class="text-4xl mb-4" />
          <p class="mb-2">No notes available</p>
          <p class="">Create some notes first to generate quizzes</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 mt-6">
          <button @click="closeNoteSelector" :class="[
            'px-4 py-2 rounded-md transition',
            store.getters['app/getCurrentTheme'] === 'dark'
              ? 'bg-gray-600 hover:bg-gray-700 text-white'
              : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
          ]">
            Cancel
          </button>
          <button
            @click="generateQuizFromSelectedNotes"
            :disabled="selectedNotes.length === 0 || isGeneratingQuiz"
            class="px-4 py-2 bg-blue-600 rounded-md hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <font-awesome-icon :icon="['fas', 'sync-alt']" class="mr-2" :spin="isGeneratingQuiz" />
            {{ isGeneratingQuiz ? 'Generating...' : `Generate Quiz (${selectedNotes.length})` }}
          </button>
        </div>
      </div>
    </div>

    <!-- Quiz Options Modal -->
    <div v-if="showQuizOptions" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div :class="[
        'rounded-lg p-6 w-full max-w-md mx-4',
        store.getters['app/getCurrentTheme'] === 'dark' ? 'bg-gray-800' : 'bg-white border border-gray-200'
      ]">
        <div class="flex justify-between items-center mb-6">
          <h2 :class="[
            'font-semibold',
            fontSizeClasses.heading,
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-white' : 'text-gray-900'
          ]">Quiz Options</h2>
          <button @click="closeQuizOptions" :class="[
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-gray-700'
          ]">
            <font-awesome-icon :icon="['fas', 'times']" class="text-xl" />
          </button>
        </div>

        <!-- Difficulty Selection -->
        <div class="mb-6">
          <label :class="[
            'block font-medium mb-3',
            fontSizeClasses.label,
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
          ]">Difficulty Level</label>
          <div class="grid grid-cols-3 gap-3">
            <button
              v-for="difficulty in ['easy', 'medium', 'hard']"
              :key="difficulty"
              @click="selectedDifficulty = difficulty"
              :class="[
                'px-4 py-3 rounded-lg border-2 transition-all duration-200 font-medium capitalize',
                fontSizeClasses.label,
                selectedDifficulty === difficulty
                  ? 'border-blue-500 bg-blue-500/20 text-blue-400'
                  : store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'border-gray-600 bg-gray-700/30 text-gray-400 hover:border-gray-500'
                    : 'border-gray-300 bg-gray-100/50 text-gray-600 hover:border-gray-400'
              ]"
            >
              <font-awesome-icon
                :icon="difficulty === 'easy' ? ['fas', 'smile'] : difficulty === 'medium' ? ['fas', 'meh'] : ['fas', 'frown']"
                class="mr-2"
              />
              {{ difficulty }}
            </button>
          </div>
        </div>

        <!-- Quantity Selection -->
        <div class="mb-6">
          <label :class="[
            'block font-medium mb-3',
            fontSizeClasses.label,
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
          ]">Number of Questions</label>
          <div class="grid grid-cols-2 gap-3">
            <button
              v-for="quantity in [5, 10, 15, 20]"
              :key="quantity"
              @click="selectedQuantity = quantity"
              :class="[
                'px-4 py-3 rounded-lg border-2 transition-all duration-200 font-medium',
                fontSizeClasses.label,
                selectedQuantity === quantity
                  ? 'border-blue-500 bg-blue-500/20 text-blue-400'
                  : store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'border-gray-600 bg-gray-700/30 text-gray-400 hover:border-gray-500'
                    : 'border-gray-300 bg-gray-100/50 text-gray-600 hover:border-gray-400'
              ]"
            >
              <font-awesome-icon :icon="['fas', 'question-circle']" class="mr-2" />
              {{ quantity }} Questions
            </button>
          </div>
        </div>

        <!-- Quiz Type Selection -->
        <div class="mb-6">
          <label :class="[
            'block font-medium mb-3',
            fontSizeClasses.label,
            store.getters['app/getCurrentTheme'] === 'dark' ? 'text-gray-300' : 'text-gray-700'
          ]">Quiz Type</label>
          <div class="space-y-3">
            <button
              v-for="type in ['multiple_choice', 'true_false', 'mixed']"
              :key="type"
              @click="selectedQuizType = type"
              :class="[
                'w-full px-4 py-3 rounded-lg border-2 transition-all duration-200 font-medium text-left',
                fontSizeClasses.label,
                selectedQuizType === type
                  ? 'border-blue-500 bg-blue-500/20 text-blue-400'
                  : store.getters['app/getCurrentTheme'] === 'dark'
                    ? 'border-gray-600 bg-gray-700/30 text-gray-400 hover:border-gray-500'
                    : 'border-gray-300 bg-gray-100/50 text-gray-600 hover:border-gray-400'
              ]"
            >
              <font-awesome-icon
                :icon="type === 'multiple_choice' ? ['fas', 'list'] : type === 'true_false' ? ['fas', 'check-circle'] : ['fas', 'random']"
                class="mr-3"
              />
              {{ type === 'multiple_choice' ? 'Multiple Choice' : type === 'true_false' ? 'True/False' : 'Mixed Questions' }}
            </button>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex space-x-4">
          <button @click="closeQuizOptions" :class="[
            'flex-1 px-4 py-3 rounded-lg transition-colors font-medium',
            fontSizeClasses.label,
            store.getters['app/getCurrentTheme'] === 'dark'
              ? 'bg-gray-600 hover:bg-gray-700 text-white'
              : 'bg-gray-200 hover:bg-gray-300 text-gray-900'
          ]">
            Cancel
          </button>
          <button
            @click="proceedToNoteSelection"
            class="flex-1 px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors text-white font-medium"
          >
            <font-awesome-icon :icon="['fas', 'arrow-right']" class="mr-2" />
            Next
          </button>
        </div>
      </div>
    </div>
</Header>
</template>

<script>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import { useNotifications } from '@/composables/useNotifications'
import { useUserProfile } from '@/composables/useUserProfile'
import api from '@/services/api'
import Header from '@/components/Header.vue'

export default {
  name: 'QuizView',
  components: {
    Header
  },
  setup() {
    const route = useRoute()
    const router = useRouter()
    const store = useStore()

    // =====================================
    // NOTIFICATION SYSTEM
    // =====================================
    const {
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      closeNotifications,
      markAsRead,
      markAllAsRead,
      showSuccess,
      showInfo,
      showWarning
    } = useNotifications()

    // =====================================
    // USER PROFILE SYSTEM
    // =====================================
    const {
      user: userProfile,
      loadUserProfile,
      getProfilePictureUrl
    } = useUserProfile()

    // Route debugging removed for production

    // State - Add error handling for route params
    let parsedNoteId = null
    try {
      const paramId = route.params.id
      if (paramId && paramId !== 'undefined' && paramId !== 'null') {
        parsedNoteId = parseInt(paramId)
        if (isNaN(parsedNoteId)) {
          parsedNoteId = null
        }
      }
    } catch (paramError) {
      console.error('Error parsing route params:', paramError)
      parsedNoteId = null
    }

    const noteId = ref(parsedNoteId)
    const noteTitle = ref('Loading...')
    const quizTitle = ref('')
    const isLoading = ref(true)
    const error = ref(null)
    const sidebarOpen = ref(false)
    const isGeneratingQuiz = ref(false)

    // Initial state set

    // Note selection state
    const showNoteSelector = ref(false)
    const availableNotes = ref([])
    const selectedNotes = ref([])
    const isLoadingNotes = ref(false)

    // Quiz state - removed since we redirect to QuizTakingView

    // Saved quizzes state
    const savedQuizzes = ref([])
    const isLoadingSavedQuizzes = ref(false)

    // Delete confirmation state
    const showDeleteConfirmation = ref(false)
    const quizToDelete = ref(null)

    // Quiz options state
    const showQuizOptions = ref(false)
    const selectedDifficulty = ref('medium')
    const selectedQuantity = ref(10)
    const selectedQuizType = ref('multiple_choice')

    // User menu state
    const showUserMenu = ref(false)

    // Computed properties


    // Sidebar visibility from store
    const sidebarVisible = computed(() => store.getters['app/getSidebarVisible'])

    // Use user from composable
    const user = userProfile

    // Use global theme classes from store
    const themeClasses = computed(() => {
      return store.getters['app/getThemeClasses'];
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


    // =====================================
    // SIDEBAR FUNCTIONS
    // =====================================

    /**
     * Toggle sidebar visibility
     */
    const toggleSidebar = () => {
      store.dispatch('app/toggleSidebar')
    }

    // =====================================
    // USER MENU FUNCTIONS
    // =====================================

    /**
     * Toggle user menu dropdown
     */
    const toggleUserMenu = () => {
      showUserMenu.value = !showUserMenu.value
    }

    /**
     * Close user menu dropdown
     */
    const closeUserMenu = () => {
      showUserMenu.value = false
    }

    /**
     * Open user profile modal
     */
    const openProfileModal = () => {
      showUserMenu.value = false
      // For now, just close the menu
      // In a full implementation, this would open a profile modal
    }

    /**
     * Logout user and redirect to login
     */
    const logout = async () => {
      try {
        router.push('/login')
      } catch (error) {
        // Error logging out
      }
    }


    // Handle image loading errors
    const handleImageError = () => {
      // Profile picture failed to load
    }

    // Handle successful image loading
    const handleImageLoad = () => {
      // Profile picture loaded successfully
    }

    // Methods
    const loadNoteData = async () => {
      console.log('=== LOAD NOTE DATA START ===')
      console.log('noteId.value:', noteId.value, 'Type:', typeof noteId.value)

      // If no note ID is provided, skip loading specific note data
      // and show the note selector instead
      if (!noteId.value) {
        console.log('No note ID provided, will show note selector')
        isLoading.value = false
        console.log('=== LOAD NOTE DATA END (no noteId) ===')
        return
      }

      try {
        console.log('Making API call to getNote with ID:', noteId.value)
        const response = await api.getNote(noteId.value)
        console.log('API response received:', response)
        console.log('Response data:', response.data)

        if (response.data.success && response.data.data) {
          const noteData = response.data.data
          console.log('Note data loaded successfully:', noteData)
          noteTitle.value = noteData.title || 'Untitled Note'
          console.log('Note title set to:', noteTitle.value)
        } else {
          console.warn('API response indicates failure:', response.data)
          error.value = response.data?.error || 'Note not found'
          console.log('Error set to:', error.value)
        }
      } catch (err) {
        console.error('Error loading note:', err)
        console.error('Error details:', {
          message: err.message,
          stack: err.stack,
          response: err.response
        })
        error.value = 'Failed to load note data'
      } finally {
        isLoading.value = false
        console.log('=== LOAD NOTE DATA END ===')
      }
    }

    const loadAvailableNotes = async () => {
      isLoadingNotes.value = true
      try {
        const response = await api.getNotes()
        if (response.data.success && response.data.data) {
          availableNotes.value = response.data.data.map(note => ({
            id: note.id,
            title: note.title || 'Untitled Note',
            original_text: note.original_text || '',
            summary: note.summary || '',
            created_at: note.created_at,
            selected: false
          }))
        } else {
          console.error('Failed to load notes:', response.data?.error)
        }
      } catch (error) {
        console.error('Error loading notes:', error)
      } finally {
        isLoadingNotes.value = false
      }
    }

    const toggleNoteSelection = (noteId) => {
      const note = availableNotes.value.find(n => n.id === noteId)
      if (note) {
        note.selected = !note.selected
        updateSelectedNotes()
      }
    }

    const updateSelectedNotes = () => {
      selectedNotes.value = availableNotes.value.filter(note => note.selected)
    }

    const selectAllNotes = () => {
      availableNotes.value.forEach(note => {
        note.selected = true
      })
      updateSelectedNotes()
    }

    const deselectAllNotes = () => {
      availableNotes.value.forEach(note => {
        note.selected = false
      })
      updateSelectedNotes()
    }

    const openNoteSelector = async () => {
      console.log('Opening note selector modal')
      showNoteSelector.value = true
      console.log('showNoteSelector set to:', showNoteSelector.value)
      if (availableNotes.value.length === 0) {
        console.log('Loading available notes...')
        await loadAvailableNotes()
        console.log('Available notes loaded:', availableNotes.value.length)
      } else {
        console.log('Notes already loaded:', availableNotes.value.length)
      }
    }

    const closeNoteSelector = () => {
      showNoteSelector.value = false
    }

    // Quiz options functions
    const openQuizOptions = () => {
      showQuizOptions.value = true
    }

    const closeQuizOptions = () => {
      showQuizOptions.value = false
      // Reset to defaults
      selectedDifficulty.value = 'medium'
      selectedQuantity.value = 10
      selectedQuizType.value = 'multiple_choice'
    }

    const proceedToNoteSelection = () => {
      showQuizOptions.value = false
      openNoteSelector()
    }

    const generateQuizFromSelectedNotes = async () => {
      if (selectedNotes.value.length === 0) {
        alert('Please select at least one note to generate a quiz from.')
        return
      }

      closeNoteSelector()
      await generateQuizFromNotes(selectedNotes.value)
    }

    const generateNewQuiz = async () => {
      console.log('=== GENERATE NEW QUIZ BUTTON CLICKED ===')
      console.log('isGeneratingQuiz:', isGeneratingQuiz.value)
      console.log('noteId:', noteId.value)
      console.log('Current route params:', route.params)
      console.log('showNoteSelector:', showNoteSelector.value)
      console.log('availableNotes length:', availableNotes.value.length)

      if (isGeneratingQuiz.value) {
        console.log('Quiz generation already in progress, returning')
        return
      }

      // Load available notes if not already loaded
      if (availableNotes.value.length === 0) {
        console.log('Loading available notes...')
        await loadAvailableNotes()
        console.log('Available notes loaded:', availableNotes.value.length)
      }

      // Determine which notes to use for quiz generation
      let notesToUse = []

      if (noteId.value) {
        // Use the specific note if provided
        console.log('Using specific note ID:', noteId.value)
        const specificNote = availableNotes.value.find(n => n.id === noteId.value)
        if (specificNote) {
          notesToUse = [specificNote]
          console.log('Found specific note:', specificNote.title)
        } else {
          console.error('Specific note not found, using all available notes')
          notesToUse = availableNotes.value
        }
      } else {
        // Use all available notes if no specific note is provided
        console.log('No specific note ID, using all available notes')
        notesToUse = availableNotes.value
      }

      if (notesToUse.length === 0) {
        alert('No notes available for quiz generation. Please create some notes first.')
        return
      }

      // Generate quiz from the selected notes
      console.log('Generating quiz from', notesToUse.length, 'note(s)')
      await generateQuizFromNotes(notesToUse)
    }

    const generateQuizFromNotes = async (notes) => {
      console.log('🎯 QUIZ GENERATION: generateQuizFromNotes function called!')
      console.log('🎯 QUIZ GENERATION: Notes received:', notes.length, 'notes')
      console.log('🎯 QUIZ GENERATION: First note preview:', notes[0] ? notes[0].title : 'No notes')

      if (isGeneratingQuiz.value) {
        console.log('⚠️ QUIZ GENERATION: Already generating, returning early')
        return
      }

      isGeneratingQuiz.value = true
      console.log('✅ QUIZ GENERATION: Set isGeneratingQuiz to true')

      try {
        console.log('=== FRONTEND QUIZ GENERATION START ===')
        console.log('Generating quiz from', notes.length, 'note(s)')

        // Collect content from all selected notes
        let combinedContent = ''
        const noteTitles = []

        for (const note of notes) {
          try {
            let noteData
            if (note.original_text) {
              // Note data already available
              noteData = note
            } else {
              // Fetch note data
              const response = await api.getNote(note.id)
              if (response.data.success && response.data.data) {
                noteData = response.data.data
              } else {
                console.warn('Failed to load note:', note.id)
                continue
              }
            }

            const usingSummary = noteData.summary && noteData.summary !== 'No summary available. Click "Generate Summary" to create one.'
            const content = usingSummary ? noteData.summary : noteData.original_text

            if (content && content.length > 10) {
              combinedContent += `\n\n--- ${noteData.title || 'Untitled Note'} ---\n${content}`
              noteTitles.push(noteData.title || 'Untitled Note')
            }
          } catch (error) {
            console.warn('Error loading note content:', note.id, error)
          }
        }

        if (!combinedContent || combinedContent.length < 50) {
          alert('Not enough content available for quiz generation. Please ensure your selected notes have sufficient text content.')
          return
        }

        console.log('Combined content length:', combinedContent.length)
        console.log('Note titles:', noteTitles.join(', '))

        // Generate quiz title using note title(s)
        let generatedQuizTitle = 'General Knowledge Quiz'

        if (notes.length === 1) {
          // For single note, use the note title as the quiz title
          generatedQuizTitle = notes[0].title || 'Untitled Note'
        } else if (notes.length === 2) {
          // For two notes, combine both titles
          const title1 = notes[0].title || 'Note 1'
          const title2 = notes[1].title || 'Note 2'
          generatedQuizTitle = `${title1} & ${title2}`
        } else if (notes.length > 2) {
          // For multiple notes, show first note title and count
          const firstTitle = notes[0].title || 'First Note'
          const remainingCount = notes.length - 1
          generatedQuizTitle = `${firstTitle} + ${remainingCount} more notes`
        } else {
          // Fallback for edge cases
          const titleResponse = await api.gpt.extractKeywords(combinedContent, 3)
          if (titleResponse.data && titleResponse.data.success && titleResponse.data.data) {
            const keywords = titleResponse.data.data
            if (Array.isArray(keywords) && keywords.length > 0) {
              // Create a meaningful title from the top keywords
              const mainTopic = keywords[0].toLowerCase()
              const secondaryTopic = keywords.length > 1 ? keywords[1].toLowerCase() : ''
              generatedQuizTitle = `${mainTopic.charAt(0).toUpperCase() + mainTopic.slice(1)}${secondaryTopic ? ' & ' + secondaryTopic.charAt(0).toUpperCase() + secondaryTopic.slice(1) : ''} Quiz`
            }
          }
        }

        console.log('🔄 QUIZ GENERATION: Starting GPT API call...')
        console.log('🔄 QUIZ GENERATION: Combined content length:', combinedContent.length)
        console.log('🔄 QUIZ GENERATION: Note titles:', noteTitles)

        // Generate quiz questions from combined content using selected options
        const gptResponse = await api.gpt.generateQuiz(combinedContent, {
          difficulty: selectedDifficulty.value,
          questionCount: selectedQuantity.value,
          noteTitle: noteTitles.length === 1 ? noteTitles[0] : `${noteTitles.length} Selected Notes`,
          quizTitle: generatedQuizTitle,
          quizType: selectedQuizType.value
        })

        console.log('🔄 QUIZ GENERATION: GPT API response received:', gptResponse)
        console.log('🔄 QUIZ GENERATION: GPT response status:', gptResponse.status)
        console.log('🔄 QUIZ GENERATION: GPT response data:', gptResponse.data)

        if (gptResponse.data && gptResponse.data.success && gptResponse.data.data) {
          const generatedQuestions = gptResponse.data.data.questions || gptResponse.data.data || []
          console.log('🔄 QUIZ GENERATION: Generated questions:', generatedQuestions)
          console.log('🔄 QUIZ GENERATION: Questions count:', generatedQuestions.length)

          if (!Array.isArray(generatedQuestions) || generatedQuestions.length === 0) {
            console.error('❌ QUIZ GENERATION: No questions generated')
            showWarning('Generation Failed', 'No quiz questions were generated. The content may be too short or unclear.')
            return
          }

          console.log('✅ QUIZ GENERATION: Questions generated successfully, now saving to database...')

          // For multiple notes, we'll create a quiz without saving to a specific note
          // Save the quiz to the database (using first note as reference or create a general quiz)
          const referenceNoteId = notes.length === 1 ? notes[0].id : (noteId.value || notes[0].id)
          console.log('🔄 QUIZ GENERATION: Using reference note ID:', referenceNoteId)

          const quizData = {
            questions: generatedQuestions,
            difficulty: selectedDifficulty.value,
            source: notes.length === 1 ? 'single_note' : 'multiple_notes',
            note_count: notes.length,
            title: generatedQuizTitle,
            note_title: generatedQuizTitle,
            quiz_type: selectedQuizType.value,
            total_questions: selectedQuantity.value
          }
          console.log('🔄 QUIZ GENERATION: Quiz data to save:', quizData)
          console.log('🔄 QUIZ GENERATION: Reference note ID:', referenceNoteId)
          console.log('🔄 QUIZ GENERATION: Generated questions count:', generatedQuestions.length)
          console.log('🔄 QUIZ GENERATION: First question preview:', generatedQuestions[0] ? JSON.stringify(generatedQuestions[0]).substring(0, 100) + '...' : 'No questions')

          console.log('🔄 QUIZ GENERATION: About to call api.createQuiz...')
          const quizResponse = await api.createQuiz(referenceNoteId, quizData)
          console.log('🔄 QUIZ GENERATION: api.createQuiz returned:', quizResponse)
          console.log('🔄 QUIZ GENERATION: Quiz creation API response:', quizResponse)
          console.log('🔄 QUIZ GENERATION: Quiz creation response data:', quizResponse.data)

          if (quizResponse.data && quizResponse.data.success) {
            console.log('✅ QUIZ GENERATION: Quiz creation response data:', quizResponse.data)
            console.log('✅ QUIZ GENERATION: Full response data keys:', Object.keys(quizResponse.data))
            console.log('✅ QUIZ GENERATION: Response data.data keys:', quizResponse.data.data ? Object.keys(quizResponse.data.data) : 'no data.data')

            // Enhanced quiz ID extraction with multiple fallback mechanisms
            let quizId = null

            // Try different possible locations for the quiz ID
            if (quizResponse.data.quiz_id) {
              quizId = quizResponse.data.quiz_id
              console.log('✅ QUIZ GENERATION: Found quiz_id in response.data')
            } else if (quizResponse.data.id) {
              quizId = quizResponse.data.id
              console.log('✅ QUIZ GENERATION: Found id in response.data')
            } else if (quizResponse.data.data && quizResponse.data.data.quiz_id) {
              quizId = quizResponse.data.data.quiz_id
              console.log('✅ QUIZ GENERATION: Found quiz_id in response.data.data')
            } else if (quizResponse.data.data && quizResponse.data.data.id) {
              quizId = quizResponse.data.data.id
              console.log('✅ QUIZ GENERATION: Found id in response.data.data')
            } else if (quizResponse.data.data && typeof quizResponse.data.data === 'number') {
              quizId = quizResponse.data.data
              console.log('✅ QUIZ GENERATION: Found numeric ID in response.data.data')
            } else if (typeof quizResponse.data.data === 'string' && !isNaN(parseInt(quizResponse.data.data))) {
              quizId = parseInt(quizResponse.data.data)
              console.log('✅ QUIZ GENERATION: Found numeric string ID in response.data.data')
            }

            console.log('✅ QUIZ GENERATION: Quiz saved successfully with ID:', quizId)
            console.log('✅ QUIZ GENERATION: Quiz ID type:', typeof quizId)
            console.log('✅ QUIZ GENERATION: Quiz ID value:', quizId)

            // Validate quiz ID before redirecting
            if (!quizId || isNaN(parseInt(quizId))) {
              console.error('❌ QUIZ GENERATION: Invalid quiz ID received:', quizId)
              console.error('❌ QUIZ GENERATION: Full response data:', JSON.stringify(quizResponse.data, null, 2))

              // Try to extract from any nested object or array
              const findQuizId = (obj, path = '') => {
                if (typeof obj === 'number' && obj > 0) {
                  console.log(`Found potential quiz ID ${obj} at path: ${path}`)
                  return obj
                }
                if (typeof obj === 'string' && !isNaN(parseInt(obj)) && parseInt(obj) > 0) {
                  console.log(`Found potential quiz ID ${obj} at path: ${path}`)
                  return parseInt(obj)
                }
                if (obj && typeof obj === 'object') {
                  for (const key in obj) {
                    const result = findQuizId(obj[key], path + '.' + key)
                    if (result) return result
                  }
                }
                return null
              }

              const extractedId = findQuizId(quizResponse.data)
              if (extractedId) {
                console.log('✅ QUIZ GENERATION: Extracted quiz ID from deep search:', extractedId)
                quizId = extractedId
              } else {
                showWarning('Save Failed', 'Quiz was created but received invalid quiz ID. Please try again.')
                return
              }
            }

            // Show success message
            showSuccess(`Quiz Generated!`, `Created ${generatedQuestions.length} questions from ${notes.length} selected note${notes.length > 1 ? 's' : ''}`)

            // Refresh the saved quizzes list to show the new quiz
            console.log('🔄 QUIZ GENERATION: Refreshing saved quizzes list...')
            try {
              await loadSavedQuizzes()
              console.log('✅ QUIZ GENERATION: Saved quizzes list refreshed successfully')
            } catch (refreshError) {
              console.warn('⚠️ QUIZ GENERATION: Failed to refresh quizzes list:', refreshError)
              // Don't fail the entire process if refresh fails
            }

            // Redirect to QuizTakingView with the quiz ID
            console.log('🔄 QUIZ GENERATION: Redirecting to quiz taking view...')
            console.log('🔄 QUIZ GENERATION: Redirect URL:', `/quizzes/${quizId}`)

            setTimeout(() => {
              console.log('🔄 QUIZ GENERATION: Executing router.push...')
              try {
                router.push(`/quizzes/${quizId}`)
                console.log('✅ QUIZ GENERATION: Router.push completed successfully')
              } catch (routerError) {
                console.error('❌ QUIZ GENERATION: Router.push failed:', routerError)
                // Fallback: show success message and stay on current page
                showSuccess('Quiz Created Successfully!', `Your quiz has been saved and is ready to take. You can find it in your quizzes list.`)
              }
            }, 1000) // Small delay to show the success message
          } else {
            console.error('❌ QUIZ GENERATION: Quiz creation failed:', quizResponse.data?.error)
            console.error('❌ QUIZ GENERATION: Full quiz response:', quizResponse.data)

            // Try to provide more specific error messages
            let errorMessage = 'Failed to save quiz'
            if (quizResponse.data?.error) {
              errorMessage += ': ' + quizResponse.data.error
            } else if (quizResponse.data?.message) {
              errorMessage += ': ' + quizResponse.data.message
            } else if (quizResponse.status === 401) {
              errorMessage = 'Authentication failed. Please log in again.'
            } else if (quizResponse.status === 403) {
              errorMessage = 'You do not have permission to create quizzes.'
            } else if (quizResponse.status === 500) {
              errorMessage = 'Server error occurred. Please try again later.'
            }

            showWarning('Save Failed', errorMessage)
          }
        } else {
          const errorMessage = gptResponse.data?.error || 'Failed to generate quiz questions'
          console.error('❌ QUIZ GENERATION: GPT API failed:', errorMessage)
          console.error('❌ QUIZ GENERATION: Full GPT response:', gptResponse)

          if (errorMessage.includes('API key') || errorMessage.includes('authentication') || errorMessage.includes('Gemini')) {
            showWarning('AI Service Error', 'AI service is not configured properly. Please check your Google Gemini API key in the .env file.')
          } else {
            showWarning('Generation Failed', 'Failed to generate quiz questions: ' + errorMessage)
          }
        }
      } catch (error) {
        console.error('Error generating quiz:', error)
        alert('Failed to generate quiz. Please try again.')
      } finally {
        isGeneratingQuiz.value = false
        console.log('=== FRONTEND QUIZ GENERATION END ===')
      }
    }

    // Quiz functions removed - now handled by QuizTakingView

    // saveQuiz function removed - quiz saving is handled by QuizTakingView

    const getQuizTypeDisplay = (quizType) => {
      switch (quizType) {
        case 'multiple_choice':
          return 'Multiple Choice'
        case 'true_false':
          return 'True/False'
        case 'mixed':
          return 'Mixed'
        default:
          return 'Multiple Choice'
      }
    }

    const debugQuizStatus = () => {
      console.log('=== QUIZ STATUS DEBUG ===')
      console.log('Total quizzes:', savedQuizzes.value.length)
      savedQuizzes.value.forEach((quiz, index) => {
        console.log(`Quiz ${index + 1} (ID: ${quiz.id}):`, {
          score: quiz.score,
          scoreType: typeof quiz.score,
          questionCount: quiz.questionCount,
          accuracy: quiz.accuracy,
          isCompleted: quiz.score !== null && quiz.score !== undefined && quiz.questionCount > 0,
          title: quiz.title
        })
      })

      const completedQuizzes = savedQuizzes.value.filter(q => q.score !== null && q.score !== undefined && q.questionCount > 0)
      const notTakenQuizzes = savedQuizzes.value.filter(q => !(q.score !== null && q.score !== undefined && q.questionCount > 0))

      console.log('Completed quizzes:', completedQuizzes.length)
      console.log('Not taken quizzes:', notTakenQuizzes.length)

      alert(`Debug Info:
Total Quizzes: ${savedQuizzes.value.length}
Completed: ${completedQuizzes.length}
Not Taken: ${notTakenQuizzes.length}

Check console for detailed quiz data.`)
    }

    const loadSavedQuizzes = async () => {
      console.log('=== LOAD SAVED QUIZZES START ===')
      try {
        isLoadingSavedQuizzes.value = true
        console.log('Making API call to getQuizzes')
        console.log('API base URL will be determined by axios configuration')
        const response = await api.getQuizzes()
        console.log('API response received:', response)
        console.log('Response status:', response.status)
        console.log('Response headers:', response.headers)
        console.log('Response data type:', typeof response.data)
        console.log('Response data:', response.data)

        if (response.data && typeof response.data === 'string' && response.data.includes('<html>')) {
          console.error('❌ CRITICAL: Received HTML response instead of JSON!')
          console.error('❌ This indicates the API request was not routed correctly')
          console.error('❌ Response contains HTML:', response.data.substring(0, 200) + '...')
          error.value = 'API routing error: received HTML instead of JSON'
          return
        }

        if (response.data.success && response.data.data) {
          console.log('Processing', response.data.data.length, 'quizzes')
          console.log('Raw quiz data from API:', response.data.data)
          savedQuizzes.value = response.data.data.map(quiz => {
            console.log('Processing quiz:', quiz.id, 'Questions type:', typeof quiz.questions, 'Questions value:', quiz.questions)
            console.log('Quiz difficulty:', quiz.difficulty, 'Quiz type:', quiz.quiz_type)
            let questions = []
            let questionCount = 0
            let accuracy = 0

            try {
              // Parse questions JSON if it's a string (from index method)
              if (typeof quiz.questions === 'string') {
                questions = JSON.parse(quiz.questions) || []
              } else {
                questions = quiz.questions || []
              }

              // Get question count with multiple fallbacks
              if (Array.isArray(questions) && questions.length > 0) {
                questionCount = questions.length
              } else if (quiz.total_questions && quiz.total_questions > 0) {
                // Fallback to total_questions field from database
                questionCount = Number(quiz.total_questions)
                console.log(`Using total_questions fallback for quiz ${quiz.id}: ${questionCount}`)
              } else {
                // Last resort: try to estimate from other data or set to 0
                questionCount = 0
                console.warn(`Could not determine question count for quiz ${quiz.id}`)
              }

              // Ensure score is a valid number (including 0)
              const validScore = (quiz.score !== null && quiz.score !== undefined && !isNaN(quiz.score)) ? Number(quiz.score) : null

              // Calculate accuracy with proper validation
              if (validScore !== null && questionCount > 0) {
                accuracy = Math.round((validScore / questionCount) * 100)
                // Ensure accuracy is within valid range
                accuracy = Math.max(0, Math.min(100, accuracy))
              } else {
                accuracy = 0
              }

              console.log(`Quiz ${quiz.id} stats: score=${validScore}, questionCount=${questionCount}, accuracy=${accuracy}%, isCompleted=${validScore !== null && questionCount > 0}`)
            } catch (parseError) {
              console.error('Error parsing questions for quiz', quiz.id, parseError)
              console.error('Quiz data:', quiz)

              // Fallback: try to use total_questions if available
              questionCount = quiz.total_questions ? Number(quiz.total_questions) : 0
              accuracy = 0

              console.warn(`Fallback used for quiz ${quiz.id}: questionCount=${questionCount}`)
            }

            // Use note title as the primary display title
            let displayTitle = quiz.note_title || quiz.title
            if (!displayTitle) {
              displayTitle = `Quiz #${quiz.id}`
            }

            const processedQuiz = {
              id: quiz.id,
              note_id: quiz.note_id,
              questions: questions,
              difficulty: quiz.difficulty,
              quiz_type: quiz.quiz_type,
              score: quiz.score,
              created_at: quiz.created_at,
              updated_at: quiz.updated_at,
              questionCount: questionCount,
              accuracy: accuracy,
              title: displayTitle,
              note_title: quiz.note_title
            }
            console.log('Processed quiz:', processedQuiz)
            return processedQuiz
          })
          console.log('Successfully processed', savedQuizzes.value.length, 'quizzes')
        } else {
          console.error('Failed to load saved quizzes:', response.data?.error)
          console.error('Response data:', response.data)
          error.value = response.data?.error || 'Failed to load quizzes'
        }
      } catch (error) {
        console.error('Error loading saved quizzes:', error)
        console.error('Error details:', {
          message: error.message,
          stack: error.stack,
          response: error.response
        })

        if (error.response) {
          console.error('Error response status:', error.response.status)
          console.error('Error response data:', error.response.data)
          if (typeof error.response.data === 'string' && error.response.data.includes('<html>')) {
            console.error('❌ CRITICAL: Error response contains HTML instead of JSON!')
            error.value = 'Server error: received HTML response'
          } else {
            error.value = error.response.data?.error || 'Failed to load quizzes'
          }
        } else if (error.request) {
          console.error('No response received from server')
          error.value = 'No response from server'
        } else {
          console.error('Request setup error:', error.message)
          error.value = 'Request setup error'
        }
      } finally {
        isLoadingSavedQuizzes.value = false
        console.log('=== LOAD SAVED QUIZZES END ===')
      }
    }

    const startQuiz = (quizId) => {
      // Navigate to the quiz taking page with the quiz ID
      router.push(`/quizzes/${quizId}`)
    }

    const confirmDeleteQuiz = (quizId) => {
      quizToDelete.value = quizId
      showDeleteConfirmation.value = true
    }

    const cancelDelete = () => {
      showDeleteConfirmation.value = false
      quizToDelete.value = null
    }

    const proceedDelete = async () => {
      if (quizToDelete.value) {
        await deleteQuiz(quizToDelete.value)
        showDeleteConfirmation.value = false
        quizToDelete.value = null
      }
    }

    const deleteQuiz = async (quizId) => {
      try {
        const response = await api.deleteQuiz(quizId)
        if (response.data.success) {
          // Remove the quiz from the local list
          savedQuizzes.value = savedQuizzes.value.filter(quiz => quiz.id !== quizId)
          alert('Quiz deleted successfully!')
        } else {
          alert('Failed to delete quiz: ' + (response.data.error || 'Unknown error'))
        }
      } catch (error) {
        console.error('Error deleting quiz:', error)
        alert('Failed to delete quiz. Please try again.')
      }
    }

    // loadSavedQuiz function removed - quiz loading is handled by QuizTakingView


    // Initialize
    onMounted(async () => {
      console.log('=== ONMOUNTED START ===')
      try {
        console.log('Calling loadNoteData...')
        await loadNoteData()
        console.log('loadNoteData completed successfully')

        console.log('Calling loadSavedQuizzes...')
        await loadSavedQuizzes()
        console.log('loadSavedQuizzes completed successfully')

        console.log('Calling loadUserProfile...')
        await loadUserProfile()
        console.log('loadUserProfile completed successfully')

        console.log('=== ONMOUNTED END ===')
      } catch (error) {
        console.error('=== ONMOUNTED ERROR ===')
        console.error('Error during component initialization:', error)
        console.error('Error details:', {
          message: error.message,
          stack: error.stack,
          name: error.name
        })
        // Set error state to show error UI
        error.value = 'Failed to initialize component: ' + error.message
        isLoading.value = false
        console.log('=== ONMOUNTED ERROR HANDLED ===')
      }

      // Note: Removed automatic note selector popup when navigating to /quizzes
      // The popup will only show when user clicks "Generate Quiz" button
    })

    return {
      noteId,
      noteTitle,
      quizTitle,
      isLoading,
      error,
      sidebarOpen,
      sidebarVisible,
      isGeneratingQuiz,
      showNoteSelector,
      availableNotes,
      selectedNotes,
      isLoadingNotes,
      savedQuizzes,
      isLoadingSavedQuizzes,
      showUserMenu,
      user,
      toggleSidebar,
      generateNewQuiz,
      loadAvailableNotes,
      toggleNoteSelection,
      selectAllNotes,
      deselectAllNotes,
      openNoteSelector,
      closeNoteSelector,
      generateQuizFromSelectedNotes,
      generateQuizFromNotes,
      loadSavedQuizzes,
      startQuiz,
      confirmDeleteQuiz,
      deleteQuiz,
      cancelDelete,
      proceedDelete,
      showDeleteConfirmation,
      quizToDelete,
      toggleUserMenu,
      closeUserMenu,
      openProfileModal,
      logout,
      getProfilePictureUrl,
      handleImageError,
      handleImageLoad,
      showNotifications,
      notifications,
      unreadNotifications,
      toggleNotifications,
      closeNotifications,
      markAsRead,
      markAllAsRead,
      showSuccess,
      showInfo,
      showWarning,
      loadUserProfile,
      debugQuizStatus,
      getQuizTypeDisplay,
      themeClasses,
      fontSizeClasses,
      store,
      // Quiz options
      showQuizOptions,
      selectedDifficulty,
      selectedQuantity,
      selectedQuizType,
      openQuizOptions,
      closeQuizOptions,
      proceedToNoteSelection
    }
  }
}
</script>
