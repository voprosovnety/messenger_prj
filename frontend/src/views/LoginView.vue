<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const router = useRouter()
const email = ref('test1@example.com')
const password = ref('Secret123!')
const error = ref('')

async function submit() {
  error.value = ''
  try {
    await api.login(email.value, password.value)
    router.push('/')
  } catch (e) {
    error.value = e.message || 'login failed'
  }
}
</script>

<template>
  <div style="max-width: 420px; margin: 40px auto;">
    <h2>Login</h2>
    <div v-if="error" style="color: red; margin-bottom: 12px;">{{ error }}</div>

    <input v-model="email" placeholder="email" style="width:100%; padding:8px; margin-bottom:8px;" />
    <input v-model="password" type="password" placeholder="password" style="width:100%; padding:8px; margin-bottom:12px;" />

    <button @click="submit" style="padding:8px 12px;">Sign in</button>
  </div>
  <div style="margin-top: 12px;">
    <a href="#" @click.prevent="router.push('/register')">Create account</a>
  </div>
</template>