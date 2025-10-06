import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/LoginView.vue'
import DashboardView from '../views/DashboardView.vue'
import SignupView from '../views/SignupView.vue'
import ForgotPasswordView from '../views/ForgotPasswordView.vue'
import ResetPasswordView from '../views/ResetPasswordView.vue'
import NotesView from '../views/NotesView.vue'
import GoalView from '../views/GoalView.vue'
import SettingsView from '../views/SettingsView.vue'
import NoteDetailView from '../views/NoteDetailView.vue'
import NoteEditorView from '@/views/NoteEditorView.vue'
import QuizView from '../views/QuizView.vue'
import QuizTakingView from '../views/QuizTakingView.vue'
import NotFoundView from '@/views/NotFoundView.vue'
// import { meta } from '@babel/eslint-parser'

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView
  },
  {
    path: '/signup',
    name: 'signup',
    component: SignupView
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: ForgotPasswordView
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: ResetPasswordView
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/notes',
    name: 'notes',
    component: NotesView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/notes/:id',
    name: 'note-detail',
    component: NoteDetailView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/notes/edit',
    name: 'note-editor',
    component: NoteEditorView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/quizzes',
    name: 'quizzes',
    component: QuizView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/quizzes/:id',
    name: 'quiz-taking',
    component: QuizTakingView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/goals',
    name: 'goals',
    component: GoalView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/settings',
    name: 'settings',
    component: SettingsView,
    //meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundView,
    //meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(process.env.BASE_URL),
  routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
  const isAuthenticated = localStorage.getItem('user') !== null;
  
  if (to.matched.some(record => record.meta.requiresAuth) && !isAuthenticated) {
    next('/login');
  } else {
    next();
  }
})

export default router