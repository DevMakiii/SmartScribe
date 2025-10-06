<template>
  <div :class="`min-h-screen ${themeClasses.main}`">
    <!-- Header -->
    <header :class="`backdrop-blur-sm sticky top-0 z-10 ${themeClasses.header}`">
      <div class="max-w-4xl mx-auto px-4 py-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <router-link
              to="/quizzes"
              :class="`flex items-center space-x-2 transition-colors ${themeClasses.secondaryText} hover:${themeClasses.text}`"
            >
              <font-awesome-icon :icon="['fas', 'angle-left']" />
              <span>Back to Quizzes</span>
            </router-link>
          </div>

          <div class="text-center">
            <h1 :class="`font-bold ${themeClasses.text} ${fontSizeClasses.heading}`">{{ quizTitle }}</h1>
          </div>

          <div class="flex items-center space-x-4">
            <div :class="`${themeClasses.secondaryText} ${fontSizeClasses.label}`">
              Question {{ currentQuestionIndex + 1 }} of {{ quizQuestions.length }}
            </div>
            <!-- Study Time Display -->
            <div class="flex items-center space-x-2 text-sm">
              <font-awesome-icon :icon="['fas', 'clock']" class="text-blue-600" />
              <span :class="themeClasses.secondaryText">{{ formattedElapsedTime }}</span>
              <div v-if="isTracking" class="flex items-center space-x-1 text-green-600">
                <font-awesome-icon :icon="['far', 'circle']" class="animate-pulse text-xs" />
                <span :class="fontSizeClasses.small">Auto-tracking</span>
              </div>
              <div v-else :class="`${themeClasses.tertiaryText} ${fontSizeClasses.small}`">
                Auto-tracking paused
              </div>
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 py-8">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex flex-col items-center justify-center min-h-96">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mb-4"></div>
        <p :class="themeClasses.secondaryText">Loading quiz...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex flex-col items-center justify-center min-h-96">
        <div class="text-red-400 mb-4">
          <font-awesome-icon :icon="['fas', 'exclamation-triangle']" class="text-4xl" />
        </div>
        <h2 :class="`font-semibold ${themeClasses.text} mb-2 ${fontSizeClasses.heading}`">Error Loading Quiz</h2>
        <p :class="`${themeClasses.secondaryText} mb-6 text-center`">{{ error }}</p>
        <router-link
          to="/quizzes"
          class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
        >
          Return to Quizzes
        </router-link>
      </div>

      <!-- Quiz Content -->
      <div v-else>
        <!-- Progress Bar -->
        <div class="mb-8">
          <div class="flex justify-between items-center mb-2">
            <span :class="`font-medium ${themeClasses.secondaryText} ${fontSizeClasses.label}`">Progress</span>
            <span :class="`${themeClasses.tertiaryText} ${fontSizeClasses.label}`">{{ answeredQuestionsCount }}/{{ quizQuestions.length }} answered</span>
          </div>
          <div :class="`w-full rounded-full h-2 ${themeClasses.border}`">
            <div
              class="bg-gradient-to-r from-blue-500 to-purple-600 h-2 rounded-full transition-all duration-500 ease-out"
              :style="{ width: `${(answeredQuestionsCount / quizQuestions.length) * 100}%` }"
            ></div>
          </div>
        </div>

        <!-- Question Card -->
        <div :class="`${themeClasses.card} backdrop-blur-sm rounded-xl p-8 mb-8`">
          <!-- Question Header -->
          <div class="flex items-start justify-between mb-6">
            <div class="flex-1">
              <div class="flex items-center space-x-3 mb-4">
                <div class="flex items-center justify-center w-8 h-8 bg-blue-600 rounded-full text-white font-bold text-sm">
                  {{ currentQuestionIndex + 1 }}
                </div>
                <div :class="`${themeClasses.secondaryText} ${fontSizeClasses.label}`">
                  {{ getQuestionType(quizQuestions[currentQuestionIndex]) }}
                </div>
              </div>
              <h2 :class="`font-semibold ${themeClasses.text} leading-relaxed ${fontSizeClasses.heading}`">
                {{ quizQuestions[currentQuestionIndex].text }}
              </h2>
            </div>
          </div>

          <!-- Answer Options -->
          <div class="space-y-3 mb-8">
            <div
              v-for="(option, optionIndex) in quizQuestions[currentQuestionIndex].options"
              :key="`option-${currentQuestionIndex}-${optionIndex}`"
              class="relative"
            >
              <label
                :for="`q${currentQuestionIndex}-o${optionIndex}`"
                class="flex items-center space-x-4 p-4 rounded-lg border-2 transition-all duration-200 cursor-pointer group"
                :class="{
                  'border-blue-500 bg-blue-500/10 text-blue-900': quizQuestions[currentQuestionIndex].selectedAnswer === optionIndex,
                  [`${themeClasses.border} hover:border-gray-500 bg-gray-200/30 ${themeClasses.secondaryText} hover:bg-gray-200/50`]: quizQuestions[currentQuestionIndex].selectedAnswer !== optionIndex
                }"
              >
                <input
                  type="radio"
                  :id="`q${currentQuestionIndex}-o${optionIndex}`"
                  :name="`question-${currentQuestionIndex}`"
                  :value="optionIndex"
                  v-model.number="quizQuestions[currentQuestionIndex].selectedAnswer"
                  class="text-blue-600 focus:ring-blue-500"
                  :disabled="quizCompleted"
                  @change="onAnswerSelected(currentQuestionIndex, optionIndex)"
                />

                <div class="flex items-center space-x-3 flex-1">
                  <div
                    class="flex items-center justify-center w-6 h-6 rounded-full border-2 text-xs font-bold"
                    :class="{
                      'border-blue-500 bg-blue-500 text-white': quizQuestions[currentQuestionIndex].selectedAnswer === optionIndex,
                      [`${themeClasses.border} ${themeClasses.tertiaryText}`]: quizQuestions[currentQuestionIndex].selectedAnswer !== optionIndex
                    }"
                  >
                    {{ String.fromCharCode(65 + optionIndex) }}
                  </div>
                  <span :class="`leading-relaxed ${fontSizeClasses.label}`">{{ option }}</span>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Navigation and Controls -->
        <div class="flex flex-col space-y-6">
          <!-- Question Navigation - Enhanced Premium Design -->
          <div :class="`relative backdrop-blur-xl rounded-2xl p-6 shadow-2xl overflow-hidden ${themeClasses.card}`">
            <!-- Background Pattern -->
            <div class="absolute inset-0 bg-gradient-to-br from-blue-500/10 via-purple-500/10 to-pink-500/10"></div>
            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500"></div>

            <!-- Header with Enhanced Styling -->
            <div class="relative flex items-center justify-between mb-6">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center shadow-lg">
                  <font-awesome-icon :icon="['fas', 'compass']" class="text-white text-sm" />
                </div>
                <h3 :class="`font-bold ${themeClasses.text} bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent ${fontSizeClasses.heading}`">
                  Question Navigator
                </h3>
              </div>

              <!-- Enhanced Legend -->
              <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 px-3 py-1 bg-blue-500/10 rounded-full border border-blue-500/20">
                  <div class="w-2 h-2 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full animate-pulse"></div>
                  <span :class="`font-medium text-blue-700 ${fontSizeClasses.small}`">Current</span>
                </div>
                <div class="flex items-center space-x-2 px-3 py-1 bg-green-500/10 rounded-full border border-green-500/20">
                  <div class="w-2 h-2 bg-gradient-to-r from-green-400 to-green-600 rounded-full"></div>
                  <span :class="`font-medium text-green-700 ${fontSizeClasses.small}`">Answered</span>
                </div>
                <div class="flex items-center space-x-2 px-3 py-1 bg-gray-300/10 rounded-full border border-gray-400/20">
                  <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                  <span :class="`font-medium ${themeClasses.secondaryText} ${fontSizeClasses.small}`">Pending</span>
                </div>
              </div>
            </div>

            <!-- Enhanced Question Grid -->
            <div class="relative grid grid-cols-5 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 xl:grid-cols-15 gap-3 mb-6">
              <button
                v-for="(question, index) in quizQuestions"
                :key="`nav-${index}`"
                @click="goToQuestion(index)"
                class="group relative aspect-square rounded-xl border-2 transition-all duration-300 flex items-center justify-center font-bold hover:scale-110 hover:rotate-3 transform-gpu"
                :class="{
                  'border-blue-400 bg-gradient-to-br from-blue-500/20 to-blue-600/20 text-blue-300 shadow-xl shadow-blue-500/30 ring-2 ring-blue-400/50': currentQuestionIndex === index,
                  [`${themeClasses.border} bg-gradient-to-br from-gray-300/40 to-gray-200/40 ${themeClasses.secondaryText} hover:border-gray-500 hover:bg-gradient-to-br hover:from-gray-400/50 hover:to-gray-300/50 hover:shadow-lg hover:shadow-gray-500/20`]: currentQuestionIndex !== index && question.selectedAnswer === null,
                  'border-green-500 bg-gradient-to-br from-green-500/20 to-green-600/20 text-green-700 hover:shadow-lg hover:shadow-green-500/20': question.selectedAnswer !== null && currentQuestionIndex !== index
                }"
              >
                <!-- Glow Effect for Current Question -->
                <div v-if="currentQuestionIndex === index" class="absolute inset-0 rounded-xl bg-gradient-to-br from-blue-400/20 to-purple-500/20 animate-pulse"></div>

                <!-- Question Number -->
                <span class="relative z-10 group-hover:scale-110 transition-transform duration-200">
                  {{ index + 1 }}
                </span>

                <!-- Hover Effect Overlay -->
                <div class="absolute inset-0 rounded-xl bg-gray-900/5 opacity-0 group-hover:opacity-100 transition-opacity duration-200"></div>
              </button>
            </div>

          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between">
            <!-- Previous Button -->
            <button
              @click="previousQuestion"
              :disabled="currentQuestionIndex === 0"
              class="flex items-center space-x-2 px-6 py-3 rounded-lg transition-all duration-200"
              :class="currentQuestionIndex === 0
                ? `${themeClasses.button} cursor-not-allowed opacity-50`
                : `${themeClasses.button} hover:scale-105`"
            >
              <font-awesome-icon :icon="['fas', 'chevron-left']" />
              <span>Previous</span>
            </button>

            <!-- Next/Submit Button -->
            <div class="flex items-center space-x-4">
              <button
                v-if="currentQuestionIndex < quizQuestions.length - 1"
                @click="nextQuestion"
                :class="`${themeClasses.buttonPrimary} flex items-center space-x-2 px-8 py-3 rounded-lg transition-all duration-200 hover:scale-105 shadow-lg`"
              >
                <span>Next</span>
                <font-awesome-icon :icon="['fas', 'chevron-right']" />
              </button>

              <button
                v-else-if="!quizCompleted"
                @click="checkAnswers"
                :disabled="!allQuestionsAnswered"
                class="flex items-center space-x-2 px-8 py-3 rounded-lg transition-all duration-200 hover:scale-105 shadow-lg"
                :class="allQuestionsAnswered
                  ? 'bg-green-600 hover:bg-green-700 text-white'
                  : `${themeClasses.button} cursor-not-allowed opacity-50`"
              >
                <font-awesome-icon :icon="['fas', 'check']" />
                <span>Submit Quiz</span>
              </button>

              <button
                v-else
                @click="viewResults"
                class="flex items-center space-x-2 px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg transition-all duration-200 text-white hover:scale-105 shadow-lg"
              >
                <font-awesome-icon :icon="['fas', 'chart-bar']" />
                <span>View Results</span>
              </button>
            </div>

          </div>
        </div>

        <!-- Results Modal -->
        <div v-if="showResults" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div :class="`${themeClasses.card} rounded-xl max-w-2xl w-full max-h-90vh overflow-y-auto`">
            <div class="p-6">
              <div class="flex items-center justify-between mb-6">
                <h2 :class="`font-bold ${themeClasses.text} ${fontSizeClasses.heading}`">Quiz Results</h2>
                <button
                  @click="showResults = false"
                  :class="`${themeClasses.secondaryText} hover:${themeClasses.text} transition-colors`"
                >
                  <font-awesome-icon :icon="['fas', 'times']" />
                </button>
              </div>

              <!-- Score Overview -->
              <div class="text-center mb-8">
                <div class="text-6xl font-bold mb-2" :class="quizScore === quizQuestions.length ? 'text-green-400' : quizScore >= quizQuestions.length * 0.7 ? 'text-yellow-400' : 'text-red-400'">
                  {{ Math.round((quizScore / quizQuestions.length) * 100) }}%
                </div>
                <p :class="`${themeClasses.secondaryText} ${fontSizeClasses.body}`">
                  {{ quizScore }} out of {{ quizQuestions.length }} correct
                </p>
              </div>

              <!-- Performance Stats -->
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div :class="`${themeClasses.hover} rounded-lg p-4 text-center`">
                  <div class="text-2xl font-bold text-green-600 mb-1">{{ quizScore }}</div>
                  <div :class="`${themeClasses.secondaryText} ${fontSizeClasses.label}`">Correct</div>
                </div>
                <div :class="`${themeClasses.hover} rounded-lg p-4 text-center`">
                  <div class="text-2xl font-bold text-red-600 mb-1">{{ quizQuestions.length - quizScore }}</div>
                  <div :class="`${themeClasses.secondaryText} ${fontSizeClasses.label}`">Incorrect</div>
                </div>
                <div :class="`${themeClasses.hover} rounded-lg p-4 text-center`">
                  <div class="text-2xl font-bold text-blue-600 mb-1">{{ Math.round((quizScore / quizQuestions.length) * 100) }}%</div>
                  <div :class="`${themeClasses.secondaryText} ${fontSizeClasses.label}`">Accuracy</div>
                </div>
                <div :class="`${themeClasses.hover} rounded-lg p-4 text-center`">
                  <div class="text-2xl font-bold text-purple-600 mb-1">{{ quizQuestions.length }}</div>
                  <div :class="`${themeClasses.secondaryText} ${fontSizeClasses.label}`">Total Questions</div>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex space-x-4">
                <button
                  @click="retakeQuiz"
                  :class="`${themeClasses.buttonPrimary} flex-1 flex items-center justify-center space-x-2 px-6 py-3 rounded-lg transition-colors`"
                >
                  <font-awesome-icon :icon="['fas', 'redo']" />
                  <span>Retake Quiz</span>
                </button>

                <router-link
                  to="/quizzes"
                  :class="`${themeClasses.button} flex-1 flex items-center justify-center space-x-2 px-6 py-3 rounded-lg transition-colors text-center`"
                >
                  <font-awesome-icon :icon="['fas', 'angle-left']" />
                  <span>Back to Quizzes</span>
                </router-link>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted, computed, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import { useStudyTime } from '@/composables/useStudyTime'
import api from '@/services/api'

export default {
  name: 'QuizTakingView',
  setup() {
    const route = useRoute()
    const store = useStore()

    // State
    const routeParams = route.params
    console.log('🔄 QUIZ TAKING VIEW: Route params received:', routeParams)
    console.log('🔄 QUIZ TAKING VIEW: Route params.id:', routeParams.id)
    console.log('🔄 QUIZ TAKING VIEW: Route params.id type:', typeof routeParams.id)

    const quizId = ref(parseInt(route.params.id) || null)
    console.log('🔄 QUIZ TAKING VIEW: Parsed quizId:', quizId.value)

    const quizTitle = ref('Loading Quiz...')
    const noteTitle = ref('')
    const isLoading = ref(true)
    const error = ref(null)
    const quizQuestions = ref([])
    const quizCompleted = ref(false)
    const quizScore = ref(0)
    const showResults = ref(false)
    const currentQuestionIndex = ref(0)

    // =====================================
    // STUDY TIME TRACKING SYSTEM
    // =====================================
    const {
      isTracking,
      formattedElapsedTime,
      setCurrentActivity,
      incrementQuizzesTaken
    } = useStudyTime()

    // Computed properties
    const allQuestionsAnswered = computed(() => {
      return quizQuestions.value.length > 0 &&
             quizQuestions.value.every(question => question.selectedAnswer !== null && question.selectedAnswer !== undefined)
    })

    const answeredQuestionsCount = computed(() => {
      return quizQuestions.value.filter(question => question.selectedAnswer !== null && question.selectedAnswer !== undefined).length
    })

    // Real-time statistics methods (called on demand for better reactivity)
    const getCorrectAnswersCount = () => {
      const count = quizQuestions.value.filter(question => {
        return question.selectedAnswer !== null &&
               question.selectedAnswer !== undefined &&
               question.selectedAnswer === question.correctAnswer
      }).length
      console.log('Correct answers count:', count)
      return count
    }

    const getIncorrectAnswersCount = () => {
      const count = quizQuestions.value.filter(question => {
        return question.selectedAnswer !== null &&
               question.selectedAnswer !== undefined &&
               question.selectedAnswer !== question.correctAnswer
      }).length
      console.log('Incorrect answers count:', count)
      return count
    }

    const getCurrentAccuracy = () => {
      const answered = answeredQuestionsCount.value
      const correct = getCorrectAnswersCount()
      const accuracy = answered === 0 ? 0 : Math.round((correct / answered) * 100)
      console.log('Current accuracy:', accuracy, 'Answered:', answered, 'Correct:', correct)
      return accuracy
    }

    const getUnansweredQuestionsCount = () => {
      const unanswered = quizQuestions.value.length - answeredQuestionsCount.value
      console.log('Unanswered questions count:', unanswered)
      return unanswered
    }

    // Computed properties that call the methods (for template use)
    const correctAnswersCount = computed(() => getCorrectAnswersCount())
    const incorrectAnswersCount = computed(() => getIncorrectAnswersCount())
    const currentAccuracy = computed(() => getCurrentAccuracy())
    const unansweredQuestionsCount = computed(() => getUnansweredQuestionsCount())

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

    // Use global theme classes from store
    const themeClasses = computed(() => {
      try {
        return store.getters['app/getThemeClasses'];
      } catch (error) {
        return {
          main: 'bg-gray-900 text-white',
          header: 'bg-gray-800 border-b border-gray-700 text-white',
          card: 'bg-gray-800 border border-gray-700 shadow-lg',
          text: 'text-white',
          secondaryText: 'text-gray-200',
          tertiaryText: 'text-gray-400',
          border: 'border-gray-700',
          button: 'bg-gray-700 hover:bg-gray-600 text-white border border-gray-600',
          buttonPrimary: 'bg-blue-600 hover:bg-blue-700 text-white border border-blue-600',
          hover: 'hover:bg-gray-700'
        };
      }
    });

    // Helper functions
    const shuffleArray = (array) => {
      const shuffled = [...array]
      for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1))
        ;[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
      }
      return shuffled
    }

    const isQuestionCorrect = (question) => {
      if (question.selectedAnswer === null || question.selectedAnswer === undefined) {
        return false
      }
      if (!question.options || question.options.length === 0) {
        return false
      }
      if (question.correctAnswer < 0 || question.correctAnswer >= question.options.length) {
        return false
      }
      return Number(question.selectedAnswer) === Number(question.correctAnswer)
    }

    const getQuestionType = (question) => {
      // Detect different question types based on question data
      if (question && question.options) {
        // Check if this is a True/False question
        if (question.options.length === 2 &&
            question.options.some(opt => opt.toLowerCase().includes('true')) &&
            question.options.some(opt => opt.toLowerCase().includes('false'))) {
          return 'True/False'
        }
        return `Multiple Choice (${question.options.length} options)`
      }
      return 'Multiple Choice'
    }

    // Navigation functions
    const nextQuestion = () => {
      if (currentQuestionIndex.value < quizQuestions.value.length - 1) {
        currentQuestionIndex.value++
      }
    }

    const previousQuestion = () => {
      if (currentQuestionIndex.value > 0) {
        currentQuestionIndex.value--
      }
    }

    const goToQuestion = (index) => {
      if (index >= 0 && index < quizQuestions.value.length) {
        currentQuestionIndex.value = index
      }
    }

    const onAnswerSelected = async (questionIndex, optionIndex) => {
      console.log(`Answer selected for question ${questionIndex + 1}: option ${optionIndex}`)
      console.log('Question data:', quizQuestions.value[questionIndex])
      console.log('Correct answer:', quizQuestions.value[questionIndex].correctAnswer)
      console.log('Is correct:', optionIndex === quizQuestions.value[questionIndex].correctAnswer)

      // Update study activity if tracking
      if (isTracking.value) {
        setCurrentActivity('taking_quiz')
      }

      // Force reactivity update using nextTick
      await nextTick()
      quizQuestions.value = [...quizQuestions.value]

      // Log updated statistics
      console.log('Updated statistics after answer selection:')
      console.log('- Correct answers:', getCorrectAnswersCount())
      console.log('- Incorrect answers:', getIncorrectAnswersCount())
      console.log('- Current accuracy:', getCurrentAccuracy() + '%')
    }

    const debugStats = () => {
      console.log('=== DEBUG STATISTICS ===')
      console.log('Total questions:', quizQuestions.value.length)
      console.log('Answered questions:', answeredQuestionsCount.value)
      console.log('Correct answers:', getCorrectAnswersCount())
      console.log('Incorrect answers:', getIncorrectAnswersCount())
      console.log('Current accuracy:', getCurrentAccuracy() + '%')
      console.log('Unanswered questions:', getUnansweredQuestionsCount())

      console.log('Question details:')
      quizQuestions.value.forEach((q, i) => {
        console.log(`Question ${i + 1}: Selected=${q.selectedAnswer}, Correct=${q.correctAnswer}, IsCorrect=${q.selectedAnswer === q.correctAnswer}`)
      })

      // Force reactivity update
      quizQuestions.value = [...quizQuestions.value]

      alert(`Debug Stats:
Total: ${quizQuestions.value.length}
Answered: ${answeredQuestionsCount.value}
Correct: ${getCorrectAnswersCount()}
Incorrect: ${getIncorrectAnswersCount()}
Accuracy: ${getCurrentAccuracy()}%
Unanswered: ${getUnansweredQuestionsCount()}`)
    }

    // Methods
    const loadQuiz = async () => {
      console.log('🔄 QUIZ LOADING: Starting to load quiz...')
      console.log('🔄 QUIZ LOADING: Quiz ID:', quizId.value)

      if (!quizId.value) {
        console.error('❌ QUIZ LOADING: No quiz ID provided')
        error.value = 'No quiz ID provided'
        isLoading.value = false
        return
      }

      try {
        console.log('🔄 QUIZ LOADING: Making API call to getQuiz...')
        const response = await api.getQuiz(quizId.value)
        console.log('🔄 QUIZ LOADING: API response received:', response)
        console.log('🔄 QUIZ LOADING: Response status:', response.status)
        console.log('🔄 QUIZ LOADING: Response data:', response.data)

        if (response.data.success && response.data.data) {
          const quizData = response.data.data
          console.log('✅ QUIZ LOADING: Quiz data received:', quizData)

          // Load the quiz questions
          const questions = quizData.questions || []
          console.log('🔄 QUIZ LOADING: Raw questions from API:', questions)
          console.log('🔄 QUIZ LOADING: Questions type:', typeof questions)
          console.log('🔄 QUIZ LOADING: Questions isArray:', Array.isArray(questions))

          if (!Array.isArray(questions) || questions.length === 0) {
            console.error('❌ QUIZ LOADING: No questions found in quiz data')
            throw new Error('No questions found in quiz data')
          }

          console.log('✅ QUIZ LOADING: Found', questions.length, 'questions, processing...')

          quizQuestions.value = questions.map(q => {
            // Handle correct answer conversion from letter to index
            let correctAnswer = q.correct_answer || q.correctAnswer || 0

            // If correct answer is a letter (A, B, C, D), convert to index (0, 1, 2, 3)
            if (typeof correctAnswer === 'string' && /^[A-D]$/.test(correctAnswer.toUpperCase())) {
              correctAnswer = correctAnswer.toUpperCase().charCodeAt(0) - 65 // A=0, B=1, C=2, D=3
            } else {
              correctAnswer = Number(correctAnswer) || 0
            }

            // Ensure we have valid options array
            const originalOptions = Array.isArray(q.options) ? q.options : []

            // Validate that we have options
            if (originalOptions.length === 0) {
              throw new Error('Question has no options')
            }

            // Check if this is a True/False question (has exactly 2 options and contains True/False)
            const isTrueFalseQuestion = originalOptions.length === 2 &&
              originalOptions.some(opt => opt.toLowerCase().includes('true')) &&
              originalOptions.some(opt => opt.toLowerCase().includes('false'))

            let shuffledOptions
            let newCorrectAnswerIndex

            if (isTrueFalseQuestion) {
              // For True/False questions, don't shuffle - keep "True" as A and "False" as B
              shuffledOptions = [...originalOptions]
              // Find the correct answer index in the original options
              const correctAnswerText = originalOptions[correctAnswer]
              newCorrectAnswerIndex = shuffledOptions.findIndex(opt =>
                opt.toLowerCase().includes(correctAnswerText.toLowerCase().trim())
              )
              console.log('True/False question detected - not shuffling options')
            } else {
              // For multiple choice questions, shuffle the options
              shuffledOptions = shuffleArray(originalOptions)

              // Find the new index of the correct answer after shuffling
              const correctAnswerText = originalOptions[correctAnswer]
              newCorrectAnswerIndex = shuffledOptions.indexOf(correctAnswerText)
            }

            const processedQuestion = {
              text: q.question || q.text || 'Untitled Question',
              options: shuffledOptions,
              originalOptions: originalOptions, // Store original for reshuffling
              correctAnswer: Math.max(0, Math.min(newCorrectAnswerIndex, shuffledOptions.length - 1)), // Ensure valid index
              selectedAnswer: null
            }

            // Debug logging
            console.log('Processed question:', {
              text: processedQuestion.text,
              optionsCount: processedQuestion.options.length,
              correctAnswer: processedQuestion.correctAnswer,
              correctAnswerText: processedQuestion.options[processedQuestion.correctAnswer],
              originalCorrectAnswer: q.correct_answer || q.correctAnswer,
              shuffled: true,
              originalOptions: originalOptions,
              shuffledOptions: shuffledOptions
            })

            return processedQuestion
          })

          // Try to get note title if available and use it as quiz title
          if (quizData.note_id) {
            try {
              const noteResponse = await api.getNote(quizData.note_id)
              if (noteResponse.data.success && noteResponse.data.data) {
                const fetchedNoteTitle = noteResponse.data.data.title || 'Unknown Note'
                noteTitle.value = fetchedNoteTitle
                // Use note title as the quiz title
                quizTitle.value = fetchedNoteTitle
              } else {
                noteTitle.value = 'Quiz from Notes'
                quizTitle.value = quizData.title || `Quiz #${quizId.value}`
              }
            } catch (noteError) {
              console.warn('Could not load note title:', noteError)
              noteTitle.value = 'Quiz from Notes'
              quizTitle.value = quizData.title || `Quiz #${quizId.value}`
            }
          } else {
            noteTitle.value = 'Generated Quiz'
            quizTitle.value = quizData.title || `Quiz #${quizId.value}`
          }
        } else {
          error.value = response.data?.error || 'Quiz not found'
        }
      } catch (err) {
        console.error('Error loading quiz:', err)
        error.value = 'Failed to load quiz'
      } finally {
        isLoading.value = false
      }
    }

    const checkAnswers = async () => {
      console.log('checkAnswers called')
      if (!allQuestionsAnswered.value) {
        console.log('Not all questions answered')
        alert('Please answer all questions before submitting.')
        return
      }

      let correctCount = 0
      let totalValidQuestions = 0

      quizQuestions.value.forEach((question, index) => {
        // Only count questions that have valid data
        if (question.options && question.options.length > 0 &&
            question.correctAnswer >= 0 && question.correctAnswer < question.options.length) {
          totalValidQuestions++
          const isCorrect = isQuestionCorrect(question)
          console.log(`Question ${index + 1}: Correct=${isCorrect}, Selected=${question.selectedAnswer}, CorrectAnswer=${question.correctAnswer}`)
          if (isCorrect) {
            correctCount++
          }
        } else {
          console.warn(`Question ${index + 1} has invalid data and will be skipped`)
        }
      })

      // Ensure score is within valid range
      const validatedScore = Math.max(0, Math.min(totalValidQuestions, correctCount))

      quizScore.value = validatedScore
      quizCompleted.value = true
      console.log(`Quiz completed. Valid questions: ${totalValidQuestions}, Correct: ${validatedScore}, Score: ${validatedScore}/${totalValidQuestions}`)

      // Update study session with quiz completion
      if (isTracking.value) {
        incrementQuizzesTaken(validatedScore)
      }

      // Automatically save quiz results to database
      console.log('Automatically saving quiz results...')
      try {
        await saveQuizResults(validatedScore)
        console.log('Quiz results saved successfully')
      } catch (error) {
        console.error('Failed to save quiz results:', error)
        // Don't block the user experience if saving fails
      }

      // Show results modal directly (skip completion popup)
      await viewResults()
    }

    const retakeQuiz = () => {
      quizQuestions.value.forEach(question => {
        question.selectedAnswer = null

        // Check if this is a True/False question
        const originalOptions = question.originalOptions || question.options
        const isTrueFalseQuestion = originalOptions.length === 2 &&
          originalOptions.some(opt => opt.toLowerCase().includes('true')) &&
          originalOptions.some(opt => opt.toLowerCase().includes('false'))

        if (isTrueFalseQuestion) {
          // For True/False questions, don't shuffle - keep original order
          question.options = [...originalOptions]
          // Find the correct answer index in the original options
          const correctAnswerText = originalOptions[question.correctAnswer]
          question.correctAnswer = originalOptions.findIndex(opt =>
            opt.toLowerCase().includes(correctAnswerText.toLowerCase().trim())
          )
          console.log('True/False question retake - not shuffling options')
        } else {
          // Re-shuffle options for multiple choice questions
          const shuffledOptions = shuffleArray(originalOptions)
          const correctAnswerText = originalOptions[question.correctAnswer]
          question.options = shuffledOptions
          question.correctAnswer = shuffledOptions.indexOf(correctAnswerText)
        }
      })
      quizCompleted.value = false
      quizScore.value = 0
      showResults.value = false
      currentQuestionIndex.value = 0
      // Ensure all reactive state is properly reset
      quizQuestions.value = [...quizQuestions.value]
    }

    const viewResults = async () => {
      console.log('View Results clicked')

      // Automatically save quiz results when viewing results
      console.log('Automatically saving quiz results...')
      try {
        await saveQuizResults(quizScore.value)
        console.log('Quiz results saved successfully when viewing results')
      } catch (error) {
        console.error('Failed to save quiz results when viewing:', error)
        // Don't block the user experience if saving fails
      }

      console.log('Quiz questions with answers:', quizQuestions.value.map((q, i) => ({
        question: i + 1,
        text: q.text,
        selected: q.selectedAnswer,
        correct: q.correctAnswer,
        isCorrect: isQuestionCorrect(q)
      })))
      showResults.value = true
    }

    const saveQuizResults = async (score) => {
      console.log('saveQuizResults called with score:', score)

      try {
        // Validate that we have a quiz ID to update
        if (!quizId.value) {
          console.error('Unable to save quiz: Quiz ID not found.')
          return
        }

        // Prepare update data - preserve existing difficulty and quiz_type
        const updateData = {
          score: score
        }

        // If we have quiz data loaded, preserve difficulty and quiz_type
        // This ensures these fields aren't lost when updating the score
        if (quizQuestions.value.length > 0) {
          // We don't have direct access to difficulty/quiz_type from loaded quiz,
          // but the backend should preserve existing values if not provided
          console.log('Preserving existing difficulty and quiz_type fields')
        }

        console.log('Automatically saving quiz results:', {
          quizId: quizId.value,
          score: score,
          totalQuestions: quizQuestions.value.length,
          updateData: updateData
        })

        const response = await api.updateQuiz(quizId.value, updateData)

        if (response.data.success) {
          console.log('Quiz results saved successfully!')
        } else {
          console.error('Failed to save quiz results:', response.data.error || 'Unknown error')
        }
      } catch (error) {
        console.error('Error saving quiz results:', error)
      }
    }


    // =====================================
    // STUDY SESSION FUNCTIONS
    // =====================================


    // Initialize
    onMounted(async () => {
      await loadQuiz()
    })

    return {
      quizId,
      quizTitle,
      noteTitle,
      isLoading,
      error,
      quizQuestions,
      quizCompleted,
      quizScore,
      showResults,
      currentQuestionIndex,
      allQuestionsAnswered,
      answeredQuestionsCount,
      correctAnswersCount,
      incorrectAnswersCount,
      currentAccuracy,
      unansweredQuestionsCount,
      isQuestionCorrect,
      getQuestionType,
      nextQuestion,
      previousQuestion,
      goToQuestion,
      onAnswerSelected,
      debugStats,
      getCorrectAnswersCount,
      getIncorrectAnswersCount,
      getCurrentAccuracy,
      getUnansweredQuestionsCount,
      checkAnswers,
      viewResults,
      retakeQuiz,
      saveQuizResults,
      shuffleArray,
      // Study time tracking
      isTracking,
      formattedElapsedTime,
      // Font size classes
      fontSizeClasses,
      // Theme classes
      themeClasses
    }
  }
}
</script>
