<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('close')">
      <div class="modal user-profile-modal">
        <button class="btn-icon modal-close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <div v-if="loading" class="user-profile-loading">Loading…</div>

        <template v-else-if="user">
          <div class="user-profile-avatar">
            <UserAvatar :username="user.username" :avatarUrl="user.avatar_url" :isOnline="user.is_online" size="xl" />
          </div>
          <div class="user-profile-name">{{ user.username }}</div>
          <div class="user-profile-status">
            <span v-if="user.is_online" class="status-online">● Online</span>
            <span v-else-if="user.last_seen_at" class="status-offline">Last seen {{ formatRelative(user.last_seen_at) }}</span>
            <span v-else class="status-offline">Offline</span>
          </div>

          <div v-if="!user.is_me" class="user-profile-actions">
            <button class="btn btn-primary" style="width:100%" :disabled="starting" @click="startChat">
              {{ starting ? 'Opening…' : 'Send message' }}
            </button>
          </div>
          <div v-else class="user-profile-actions">
            <button class="btn btn-ghost" style="width:100%" @click="$emit('go-profile')">Edit profile</button>
          </div>
        </template>

        <div v-else class="user-profile-loading">User not found</div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import UserAvatar from './UserAvatar.vue'
import { api } from '../api.js'

const props = defineProps({
  username: { type: String, required: true },
  sidebarChats: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'open-chat', 'go-profile'])

const user = ref(null)
const loading = ref(true)
const starting = ref(false)

onMounted(async () => {
  try {
    user.value = await api.getUser(props.username)
  } finally {
    loading.value = false
  }
})

function formatRelative(iso) {
  if (!iso) return 'a while ago'
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

async function startChat() {
  starting.value = true
  try {
    const existing = props.sidebarChats.find(c => !c.is_group && c.peer_username === props.username)
    if (existing) {
      emit('open-chat', existing.id)
    } else {
      const chat = await api.createChat({ isGroup: false, participants: [props.username] })
      emit('open-chat', chat.id)
    }
    emit('close')
  } catch {
    starting.value = false
  }
}

function onKey(e) { if (e.key === 'Escape') emit('close') }
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>
