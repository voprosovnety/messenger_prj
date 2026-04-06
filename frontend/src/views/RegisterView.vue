<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const username = ref('')
const email = ref('')
const password = ref('')
const error = ref('')
const ok = ref('')

async function submit() {
  error.value = ''
  ok.value = ''

  if (!username.value || !email.value || !password.value) {
    error.value = 'username, email and password are required'
    return
  }

  const res = await fetch('/api/auth/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: username.value, email: email.value, password: password.value }),
  })

  const json = await res.json().catch(() => ({}))

  if (!res.ok) {
    error.value = json.error || json.message || 'register failed'
    return
  }

  ok.value = 'registered, now login...'

  // auto login right after register (login supports username/email via identifier)
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
}
</script>

<template>
  <div style="max-width: 420px; margin: 40px auto;">
    <h2>Register</h2>

    <div v-if="error" style="color: red; margin-bottom: 12px;">{{ error }}</div>
    <div v-if="ok" style="color: green; margin-bottom: 12px;">{{ ok }}</div>

    <input v-model="username" placeholder="username" style="width:100%; padding:8px; margin-bottom:8px;" />
    <input v-model="email" placeholder="email" style="width:100%; padding:8px; margin-bottom:8px;" />
    <input v-model="password" type="password" placeholder="password" style="width:100%; padding:8px; margin-bottom:12px;" />

    <button @click="submit" style="padding:8px 12px;">Create account</button>

    <div style="margin-top: 12px;">
      <a href="#" @click.prevent="router.push('/login')">Already have an account? Login</a>
    </div>
  </div>
</template>