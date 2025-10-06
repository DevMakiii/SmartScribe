import { createClient } from '@supabase/supabase-js'

// Supabase configuration
// Replace these with your actual Supabase project credentials
const supabaseUrl = process.env.VITE_SUPABASE_URL || 'https://fxwwyngdrcigukoyrfzt.supabase.co'
const supabaseAnonKey = process.env.VITE_SUPABASE_ANON_KEY || 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImZ4d3d5bmdkcmNpZ3Vrb3lyZnp0Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTc0NzA1MDEsImV4cCI6MjA3MzA0NjUwMX0.Qjl6h7r7xmAef7iM0J_iwLhXdj73tbtUAO4wXzfyXgY'

console.log('🔧 Supabase configuration loaded:', {
  url: supabaseUrl,
  hasAnonKey: !!supabaseAnonKey,
  anonKeyLength: supabaseAnonKey ? supabaseAnonKey.length : 0,
  envUrl: process.env.VITE_SUPABASE_URL,
  envKeyPresent: !!process.env.VITE_SUPABASE_ANON_KEY
});

export const supabase = createClient(supabaseUrl, supabaseAnonKey)

// Auth helper functions
export const auth = {
  // Sign up with email and password
  signUp: async (email, password, metadata = {}) => {
    const { data, error } = await supabase.auth.signUp({
      email,
      password,
      options: {
        data: metadata
      }
    })
    return { data, error }
  },

  // Sign in with email and password
  signIn: async (email, password) => {
    console.log('🔐 Supabase signIn called with:', {
      email: email,
      passwordLength: password ? password.length : 0,
      supabaseUrl: supabaseUrl,
      hasAnonKey: !!supabaseAnonKey
    });

    try {
      const { data, error } = await supabase.auth.signInWithPassword({
        email,
        password
      });

      console.log('🔐 Supabase signIn response:', {
        hasData: !!data,
        hasError: !!error,
        errorMessage: error?.message,
        dataKeys: data ? Object.keys(data) : null,
        userExists: data?.user ? true : false,
        sessionExists: data?.session ? true : false
      });

      if (error) {
        console.error('🔐 Supabase signIn error details:', {
          name: error.name,
          message: error.message,
          status: error.status,
          details: error
        });
      }

      return { data, error };
    } catch (networkError) {
      console.error('🔐 Supabase signIn network error:', networkError);
      return { data: null, error: networkError };
    }
  },

  // Sign in with Google OAuth
  signInWithGoogle: async () => {
    console.log('🔐 Starting Google OAuth flow...');
    console.log('🔐 Current URL:', window.location.href);
    console.log('🔐 Redirect URL will be:', `${window.location.origin}/`);

    const { data, error } = await supabase.auth.signInWithOAuth({
      provider: 'google',
      options: {
        redirectTo: `${window.location.origin}/`
      }
    })

    if (error) {
      console.error('❌ Google OAuth error:', error);
      console.error('❌ Error details:', error.message);
      return { data, error };
    }

    if (data?.url) {
      console.log('🔐 Google OAuth URL generated:', data.url);
      console.log('🔐 Redirecting to Google...');
    }

    return { data, error }
  },

  // Sign in with Facebook OAuth
  signInWithFacebook: async () => {
    console.log('🔐 Starting Facebook OAuth flow...');
    console.log('🔐 Current URL:', window.location.href);
    console.log('🔐 Redirect URL will be:', `${window.location.origin}/`);

    const { data, error } = await supabase.auth.signInWithOAuth({
      provider: 'facebook',
      options: {
        redirectTo: `${window.location.origin}/`
      }
    })

    if (error) {
      console.error('❌ Facebook OAuth error:', error);
      console.error('❌ Error details:', error.message);
      return { data, error };
    }

    if (data?.url) {
      console.log('🔐 Facebook OAuth URL generated:', data.url);
      console.log('🔐 Redirecting to Facebook...');
    }

    return { data, error }
  },

  // Sign out
  signOut: async () => {
    const { error } = await supabase.auth.signOut()
    return { error }
  },

  // Get current user
  getCurrentUser: async () => {
    const { data: { user }, error } = await supabase.auth.getUser()
    return { user, error }
  },

  // Reset password
  resetPassword: async (email) => {
    const { data, error } = await supabase.auth.resetPasswordForEmail(email, {
      redirectTo: `${window.location.origin}/reset-password`
    })
    return { data, error }
  },

  // Update password
  updatePassword: async (password) => {
    const { data, error } = await supabase.auth.updateUser({
      password
    })
    return { data, error }
  },

  // Listen to auth state changes
  onAuthStateChange: (callback) => {
    return supabase.auth.onAuthStateChange(callback)
  }
}

export default supabase