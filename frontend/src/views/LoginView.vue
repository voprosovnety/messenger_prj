<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="auth-logo-icon">💬</div>
        <span class="auth-logo-name">RealtimeChat</span>
      </div>

      <h1>Welcome back</h1>
      <p class="subtitle">Sign in to your account to continue</p>

      <div v-if="error" class="auth-error">{{ error }}</div>

      <form @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label">Email or username</label>
          <input
            v-model="identifier"
            class="input"
            type="text"
            placeholder="you@example.com"
            autocomplete="username"
            required
          />
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input
            v-model="password"
            class="input"
            type="password"
            placeholder="••••••••"
            autocomplete="current-password"
            required
          />
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px" :disabled="loading">
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="auth-footer">
        Don't have an account? <RouterLink to="/register">Create one</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const router = useRouter()
const identifier = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await api.login(identifier.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = e.message || 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>
