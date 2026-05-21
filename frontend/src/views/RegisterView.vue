<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="auth-logo-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M20 2H4C2.9 2 2 2.9 2 4v12c0 1.1.9 2 2 2h6l4 4 4-4h2c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-4 9c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-4 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM8 11c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/>
          </svg>
        </div>
        <span class="auth-logo-name">RealtimeChat</span>
      </div>

      <h1>Create account</h1>
      <p class="subtitle">Join RealtimeChat and start messaging</p>

      <div v-if="error" class="auth-error">{{ error }}</div>

      <form @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input
            v-model="username"
            class="input"
            type="text"
            placeholder="cooluser42"
            autocomplete="username"
            required
          />
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input
            v-model="email"
            class="input"
            type="email"
            placeholder="you@example.com"
            autocomplete="email"
            required
          />
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-with-action">
            <input
              v-model="password"
              class="input"
              :type="showPassword ? 'text' : 'password'"
              placeholder="••••••••"
              autocomplete="new-password"
              minlength="8"
              required
            />
            <button type="button" class="input-action-btn" :title="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
              <svg v-if="showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="input-hint">At least 8 characters</div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px" :disabled="loading">
          {{ loading ? 'Creating account…' : 'Create account' }}
        </button>
      </form>

      <p class="auth-footer">
        Already have an account? <RouterLink to="/login">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const username = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const showPassword = ref(false)

async function submit() {
  error.value = ''
  if (!username.value || !email.value || !password.value) {
    error.value = 'All fields are required'
    return
  }
  loading.value = true
  try {
    const res = await fetch('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username.value, email: email.value, password: password.value }),
    })
    const json = await res.json().catch(() => ({}))
    if (!res.ok) {
      error.value = json.error || json.message || 'Registration failed'
      return
    }
    // auto-login
    const loginRes = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier: username.value, password: password.value }),
    })
    const loginJson = await loginRes.json().catch(() => ({}))
    if (!loginRes.ok) {
      router.push('/login')
      return
    }
    localStorage.setItem('access_token', loginJson.access_token)
    localStorage.setItem('refresh_token', loginJson.refresh_token)
    router.push('/')
  } catch (e) {
    error.value = e.message || 'Registration failed'
  } finally {
    loading.value = false
  }
}
</script>
