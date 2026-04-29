<template>
  <div class="profile-page">
    <div class="profile-card">
      <div class="profile-card-header">
        <button class="btn-icon" title="Back" @click="router.push('/')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <span class="profile-card-title">Profile settings</span>
      </div>

      <div v-if="loading" style="color:var(--text-2);font-size:14px">Loading…</div>

      <template v-else>
        <div class="profile-avatar-section">
          <UserAvatar :username="username || '?'" :avatarUrl="avatarUrl || null" size="xl" />
          <div>
            <div style="font-size:15px;font-weight:600;color:var(--text)">{{ username }}</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:2px">{{ email }}</div>
          </div>
        </div>

        <div v-if="success" style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:8px;padding:10px 14px;font-size:13px;color:#22c55e;margin-bottom:16px">
          Profile updated successfully
        </div>
        <div v-if="error" class="auth-error">{{ error }}</div>

        <form @submit.prevent="save">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input v-model="username" class="input" type="text" placeholder="username" required />
          </div>
          <div class="form-group">
            <label class="form-label">Avatar URL <span style="color:var(--text-3)">(optional)</span></label>
            <input v-model="avatarUrl" class="input" type="url" placeholder="https://example.com/avatar.jpg" />
            <p style="font-size:12px;color:var(--text-3);margin-top:4px">Paste a direct link to a publicly accessible image.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input :value="email" class="input" type="email" disabled style="opacity:0.5;cursor:not-allowed" />
            <p style="font-size:12px;color:var(--text-3);margin-top:4px">Email cannot be changed.</p>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save changes' }}
          </button>
        </form>

        <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
          <button class="btn btn-danger" style="width:100%" @click="logout">Sign out</button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import UserAvatar from '../components/UserAvatar.vue'

const router = useRouter()

const username = ref('')
const email = ref('')
const avatarUrl = ref('')
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref(false)

onMounted(async () => {
  try {
    const me = await api.me()
    username.value = me.username || ''
    email.value = me.email || ''
    avatarUrl.value = me.avatar_url || ''
  } catch {
    router.push('/login')
  } finally {
    loading.value = false
  }
})

async function save() {
  error.value = ''
  success.value = false
  saving.value = true
  try {
    await api.updateProfile({
      username: username.value.trim(),
      avatarUrl: avatarUrl.value.trim() || null,
    })
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (e) {
    error.value = e.message || 'Failed to update profile'
  } finally {
    saving.value = false
  }
}

async function logout() {
  await api.logout()
  router.push('/login')
}
</script>
