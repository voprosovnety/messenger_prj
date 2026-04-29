<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="auth-logo-icon">💬</div>
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
          <input
            v-model="password"
            class="input"
            type="password"
            placeholder="••••••••"
            autocomplete="new-password"
            required
          />
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
