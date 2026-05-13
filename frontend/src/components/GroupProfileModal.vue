<template>
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('close')">
      <div class="modal group-profile-modal">
        <button class="btn-icon modal-close-btn" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <!-- Avatar -->
        <div class="gp-avatar-section">
          <div style="position:relative;display:inline-block">
            <UserAvatar
              :username="chat.display_name || 'Group'"
              :avatarUrl="localAvatarUrl"
              size="xl"
              :style="lightboxImages.length ? 'cursor:zoom-in' : ''"
              @click="openAvatarLightbox"
            />
            <label v-if="isOwner" class="avatar-upload-btn" title="Change photo" :class="{ loading: uploadingAvatar }">
              <input type="file" accept="image/*" style="display:none" :disabled="uploadingAvatar" @change="onAvatarFile" />
              <svg v-if="!uploadingAvatar" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
              <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            </label>
          </div>

        </div>

        <!-- Title -->
        <div v-if="!showRename" class="gp-title-row">
          <div class="gp-title">{{ chat.display_name || 'Group' }}</div>
          <button v-if="isOwner" class="btn-icon" style="padding:4px" title="Rename" @click="startRename">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
        </div>
        <div v-else class="gp-rename-row">
          <input
            ref="renameInputEl"
            v-model="renameInput"
            class="input"
            placeholder="Group name"
            style="font-size:14px"
            :disabled="renaming"
            @keydown.enter.prevent="saveRename"
            @keydown.esc.prevent="showRename = false"
          />
          <button class="btn btn-primary" style="padding:6px 12px;font-size:13px;flex-shrink:0" :disabled="renaming || !renameInput.trim()" @click="saveRename">Save</button>
          <button class="btn btn-ghost" style="padding:6px 12px;font-size:13px;flex-shrink:0" @click="showRename = false">Cancel</button>
        </div>

        <div class="gp-meta">{{ localParticipants.length }} members</div>

        <div v-if="error" class="auth-error" style="margin:0 0 8px">{{ error }}</div>

        <!-- Members -->
        <div class="gp-members">
          <!-- Add member row -->
          <div v-if="isOwner && !showAddMember" style="padding:8px 0">
            <button class="btn btn-ghost" style="width:100%;font-size:13px" @click="showAddMember = true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add member
            </button>
          </div>
          <div v-else-if="isOwner && showAddMember" style="display:flex;gap:6px;padding:8px 0">
            <input
              v-model="participantInput"
              class="input"
              placeholder="username or email"
              style="font-size:13px"
              :disabled="busy"
              @keydown.enter.prevent="addParticipant"
            />
            <button class="btn btn-primary" style="padding:6px 12px;font-size:13px;flex-shrink:0" :disabled="busy || !participantInput.trim()" @click="addParticipant">Add</button>
            <button class="btn btn-ghost" style="padding:6px 10px;font-size:13px;flex-shrink:0" @click="showAddMember = false">✕</button>
          </div>

          <div v-for="p in localParticipants" :key="p.id" class="member-item">
            <UserAvatar
              :username="p.username"
              :avatarUrl="p.avatar_url"
              size="sm"
              :style="!p.is_me ? 'cursor:pointer' : ''"
              @click="!p.is_me ? $emit('open-user', p.username) : undefined"
            />
            <div
              class="member-item-info"
              :style="!p.is_me ? 'cursor:pointer' : ''"
              @click="!p.is_me ? $emit('open-user', p.username) : undefined"
            >
              <div class="member-item-name">
                {{ p.username }}
                <span v-if="p.is_me" style="color:var(--text-3);font-weight:400;font-size:12px"> (you)</span>
              </div>
              <div class="member-item-role">
                {{ p.role.toLowerCase() }}
                <template v-if="!p.is_me">
                  <span class="member-status-sep">·</span>
                  <span v-if="isUserOnline(p)" class="member-online">online</span>
                  <span v-else-if="p.last_seen_at" class="member-last-seen">last seen {{ formatRelative(p.last_seen_at) }}</span>
                  <span v-else class="member-last-seen">offline</span>
                </template>
              </div>
            </div>
            <div class="member-item-actions">
              <button
                v-if="isOwner && !p.is_me && p.role !== 'OWNER'"
                class="btn-icon"
                style="color:var(--danger)"
                title="Remove"
                :disabled="busy"
                @click="removeParticipant(p.id)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Danger zone -->
        <div class="gp-danger">
          <button v-if="!isOwner" class="btn btn-danger" style="width:100%" :disabled="busy" @click="leaveChat">Leave group</button>
          <button v-if="isOwner" class="btn btn-danger" style="width:100%" :disabled="busy" @click="deleteChat">Delete group</button>
        </div>
      </div>
    </div>

    <!-- Lightbox for avatar -->
    <ImageLightbox
      v-if="lightboxOpen && lightboxImages.length"
      :images="lightboxImages"
      :index="lightboxIndex"
      :canApply="isOwner"
      :currentImageIndex="0"
      @close="lightboxOpen = false"
      @navigate="lightboxIndex = $event"
      @apply="applyFromLightbox($event)"
      @delete="deleteFromLightbox($event)"
    />
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import UserAvatar from './UserAvatar.vue'
import ImageLightbox from './ImageLightbox.vue'
import { api } from '../api.js'

const props = defineProps({
  chat: { type: Object, required: true },
  participants: { type: Array, default: () => [] },
  me: { type: Object, default: null },
})
const emit = defineEmits(['close', 'updated', 'member-added', 'member-removed', 'left', 'deleted', 'open-user'])

const localParticipants = ref([...props.participants])
const localAvatarUrl = ref(props.chat.avatar_url || null)

const isOwner = computed(() => props.chat.my_role === 'OWNER')

const lightboxOpen = ref(false)
const lightboxIndex = ref(0)
const uploadingAvatar = ref(false)
const avatarHistory = ref([])

const lightboxImages = computed(() => {
  const urls = []
  if (localAvatarUrl.value) urls.push(localAvatarUrl.value)
  for (const h of avatarHistory.value) {
    if (h.url !== localAvatarUrl.value) urls.push(h.url)
  }
  return urls
})

function openAvatarLightbox() {
  if (!lightboxImages.value.length) return
  lightboxIndex.value = 0
  lightboxOpen.value = true
}

const showRename = ref(false)
const renameInput = ref('')
const renaming = ref(false)
const renameInputEl = ref(null)

const showAddMember = ref(false)
const participantInput = ref('')
const busy = ref(false)
const error = ref('')

onMounted(async () => {
  try {
    const data = await api.getChatAvatarHistory(props.chat.id)
    avatarHistory.value = data.items || []
  } catch {}
})

async function onAvatarFile(e) {
  const file = e.target.files[0]
  e.target.value = ''
  if (!file) return
  uploadingAvatar.value = true
  error.value = ''
  try {
    const result = await api.uploadFile(file)
    await api.updateChatAvatar(props.chat.id, result.url)
    localAvatarUrl.value = result.url
    if (!avatarHistory.value.find(h => h.url === result.url)) {
      avatarHistory.value.unshift({ url: result.url, created_at: new Date().toISOString() })
    }
    emit('updated', { avatar_url: result.url })
  } catch (err) {
    error.value = err.message
  } finally {
    uploadingAvatar.value = false
  }
}

async function applyHistoryAvatar(url) {
  if (url === localAvatarUrl.value || !isOwner.value) return
  uploadingAvatar.value = true
  error.value = ''
  try {
    await api.updateChatAvatar(props.chat.id, url)
    localAvatarUrl.value = url
    lightboxIndex.value = 0
    emit('updated', { avatar_url: url })
  } catch (err) {
    error.value = err.message
  } finally {
    uploadingAvatar.value = false
  }
}

function applyFromLightbox(idx) {
  const url = lightboxImages.value[idx]
  if (url) applyHistoryAvatar(url)
}

async function deleteFromLightbox(idx) {
  const url = lightboxImages.value[idx]
  if (!url || url === localAvatarUrl.value) return
  const entry = avatarHistory.value.find(h => h.url === url)
  if (!entry?.id) return
  try {
    await api.deleteChatAvatarHistory(props.chat.id, entry.id)
    avatarHistory.value = avatarHistory.value.filter(h => h.id !== entry.id)
    const newLen = lightboxImages.value.length
    if (lightboxIndex.value >= newLen) lightboxIndex.value = Math.max(0, newLen - 1)
  } catch (err) {
    error.value = err.message
  }
}

function startRename() {
  renameInput.value = props.chat.title || props.chat.display_name || ''
  showRename.value = true
  nextTick(() => renameInputEl.value?.focus())
}

async function saveRename() {
  const title = renameInput.value.trim()
  if (!title) return
  renaming.value = true
  error.value = ''
  try {
    const res = await api.renameChat(props.chat.id, title)
    showRename.value = false
    emit('updated', { title: res.title, display_name: res.title })
  } catch (err) {
    error.value = err.message
  } finally {
    renaming.value = false
  }
}

async function addParticipant() {
  const ident = participantInput.value.trim()
  if (!ident) return
  busy.value = true
  error.value = ''
  try {
    await api.addChatMember(props.chat.id, ident)
    const data = await api.getChat(props.chat.id)
    localParticipants.value = data.participants || []
    participantInput.value = ''
    showAddMember.value = false
    emit('member-added', localParticipants.value)
  } catch (err) {
    error.value = err.message
  } finally {
    busy.value = false
  }
}

async function removeParticipant(userId) {
  busy.value = true
  error.value = ''
  try {
    await api.removeChatMember(props.chat.id, userId)
    localParticipants.value = localParticipants.value.filter(p => p.id !== userId)
    emit('member-removed', localParticipants.value)
  } catch (err) {
    error.value = err.message
  } finally {
    busy.value = false
  }
}

async function leaveChat() {
  if (!confirm('Leave this group?')) return
  busy.value = true
  try {
    await api.leaveChat(props.chat.id)
    emit('left')
  } catch (err) {
    error.value = err.message
    busy.value = false
  }
}

async function deleteChat() {
  if (!confirm('Delete this group permanently?')) return
  busy.value = true
  try {
    await api.deleteChat(props.chat.id)
    emit('deleted')
  } catch (err) {
    error.value = err.message
    busy.value = false
  }
}

function isUserOnline(user) {
  if (!user?.last_seen_at) return false
  return (Date.now() - new Date(user.last_seen_at).getTime()) < 65000
}

function formatRelative(iso) {
  if (!iso) return 'a while ago'
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function onKey(e) { if (e.key === 'Escape') emit('close') }
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<style scoped>
.member-status-sep {
  margin: 0 3px;
  color: var(--text-3);
}
.member-online {
  color: #4caf7d;
  font-weight: 500;
}
.member-last-seen {
  color: var(--text-3);
}
</style>
