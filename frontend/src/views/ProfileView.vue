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
          <div style="position:relative;flex-shrink:0">
            <UserAvatar
              :username="username || '?'"
              :avatarUrl="avatarUrl || null"
              size="xl"
              :style="avatarUrl ? 'cursor:zoom-in' : ''"
              @click="avatarUrl ? (lightboxOpen = true) : undefined"
            />
            <label class="avatar-upload-btn" title="Change photo" :class="{ loading: uploadingAvatar }">
              <input type="file" accept="image/*" style="display:none" :disabled="uploadingAvatar" @change="onAvatarFile" />
              <svg v-if="!uploadingAvatar" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            </label>
          </div>

          <div>
            <div style="font-size:15px;font-weight:600;color:var(--text)">{{ username }}</div>
            <div style="font-size:13px;color:var(--text-2);margin-top:2px">{{ email }}</div>
            <button
              type="button"
              class="preset-toggle"
              @click="showPresets = !showPresets"
            >{{ showPresets ? 'Hide presets' : 'Choose preset' }}</button>
          </div>
        </div>

        <!-- Preset avatar picker -->
        <div v-if="showPresets" class="preset-grid">
          <button
            v-for="name in presetAvatars"
            :key="name"
            type="button"
            class="preset-item"
            :class="{ active: avatarUrl === `/avatars/${name}.svg` }"
            :title="name"
            @click="applyPreset(name)"
          >
            <img :src="`/avatars/${name}.svg`" :alt="name" />
          </button>
        </div>

        <!-- Avatar history -->
        <div v-if="avatarHistory.length" style="margin-bottom:20px">
          <div class="avatar-history-label">Previous photos</div>
          <div class="avatar-history-grid">
            <img
              v-for="h in avatarHistory"
              :key="h.url"
              :src="h.url"
              class="avatar-history-item"
              :class="{ current: h.url === avatarUrl }"
              :title="h.url === avatarUrl ? 'Current' : 'Apply this photo'"
              @click="applyHistoryAvatar(h.url)"
            />
          </div>
        </div>

        <div v-if="success" style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);border-radius:8px;padding:10px 14px;font-size:13px;color:#22c55e;margin-bottom:16px">
          Profile updated successfully
        </div>
        <div v-if="error" class="auth-error">{{ error }}</div>

        <form @submit.prevent="save">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input :value="username" class="input" type="text" disabled style="opacity:0.5;cursor:not-allowed" />
            <p style="font-size:12px;color:var(--text-3);margin-top:4px">Username cannot be changed.</p>
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

  <!-- Avatar lightbox -->
  <ImageLightbox
    v-if="lightboxOpen && avatarUrl"
    :images="[avatarUrl]"
    :index="0"
    @close="lightboxOpen = false"
    @navigate="() => {}"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import UserAvatar from '../components/UserAvatar.vue'
import ImageLightbox from '../components/ImageLightbox.vue'

const router = useRouter()

const username = ref('')
const email = ref('')
const avatarUrl = ref('')
const loading = ref(true)
const saving = ref(false)
const uploadingAvatar = ref(false)
const error = ref('')
const success = ref(false)
const lightboxOpen = ref(false)
const avatarHistory = ref([])
const showPresets = ref(false)

const presetAvatars = [
  'dog','cat','rabbit','fox','bear','panda','koala','tiger','lion','wolf',
  'monkey','pig','cow','frog','hamster','mouse','horse','unicorn','chicken',
  'penguin','duck','owl','turtle','octopus','shark','dolphin','giraffe',
  'zebra','crocodile','butterfly',
]

onMounted(async () => {
  try {
    const me = await api.me()
    username.value = me.username || ''
    email.value = me.email || ''
    avatarUrl.value = me.avatar_url || ''
    const hist = await api.getUserAvatarHistory()
    avatarHistory.value = hist.items || []
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
    await api.updateProfile({ avatarUrl: avatarUrl.value.trim() || null })
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (e) {
    error.value = e.message || 'Failed to update profile'
  } finally {
    saving.value = false
  }
}

async function onAvatarFile(e) {
  const file = e.target.files[0]
  e.target.value = ''
  if (!file) return
  uploadingAvatar.value = true
  error.value = ''
  try {
    const result = await api.uploadFile(file)
    avatarUrl.value = result.url
    await api.updateProfile({ avatarUrl: result.url })
    if (!avatarHistory.value.find(h => h.url === result.url)) {
      avatarHistory.value.unshift({ url: result.url, created_at: new Date().toISOString() })
    }
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (err) {
    error.value = err.message
  } finally {
    uploadingAvatar.value = false
  }
}

async function applyPreset(name) {
  const url = `/avatars/${name}.svg`
  if (url === avatarUrl.value) { showPresets.value = false; return }
  uploadingAvatar.value = true
  error.value = ''
  try {
    await api.updateProfile({ avatarUrl: url })
    avatarUrl.value = url
    showPresets.value = false
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (err) {
    error.value = err.message
  } finally {
    uploadingAvatar.value = false
  }
}

async function applyHistoryAvatar(url) {
  if (url === avatarUrl.value) return
  uploadingAvatar.value = true
  error.value = ''
  try {
    await api.updateProfile({ avatarUrl: url })
    avatarUrl.value = url
    success.value = true
    setTimeout(() => { success.value = false }, 3000)
  } catch (err) {
    error.value = err.message
  } finally {
    uploadingAvatar.value = false
  }
}

async function logout() {
  await api.logout()
  router.push('/login')
}
</script>
