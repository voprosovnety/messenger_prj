<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">💬</div>
        <span class="sidebar-logo-text">RealtimeChat</span>
        <button class="btn-icon" title="New chat" @click="openCreate">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <div class="sidebar-chats">
        <div v-if="loading" style="padding:12px 16px;color:var(--text-3);font-size:13px;">Loading…</div>
        <div v-if="error" style="padding:12px 16px;color:var(--danger);font-size:13px;">{{ error }}</div>

        <template v-if="chats.length">
          <p class="chats-section-label">Conversations</p>
          <button
            v-for="c in chats"
            :key="c.id"
            class="chat-item"
            type="button"
            @click="openChat(c)"
          >
            <UserAvatar :username="c.display_name || c.id" size="md" />
            <div class="chat-item-info">
              <div class="chat-item-top">
                <span class="chat-item-name">{{ c.display_name || c.title || c.id }}</span>
                <span class="chat-item-time">{{ formatTime(c.last_message?.created_at || c.last_at) }}</span>
              </div>
              <div class="chat-item-top" style="margin-top:1px">
                <span class="chat-item-preview">{{ lastPreview(c) }}</span>
                <span v-if="(c.unread_count || 0) > 0" class="unread-badge">{{ c.unread_count }}</span>
              </div>
            </div>
          </button>
        </template>

        <div v-else-if="!loading" style="padding:16px;color:var(--text-3);font-size:13px;text-align:center">
          No conversations yet.<br>
          <button class="btn btn-ghost" style="margin-top:8px;font-size:13px" @click="openCreate">Start one</button>
        </div>
      </div>

      <!-- Profile footer -->
      <div class="sidebar-footer">
        <UserAvatar :username="me?.username || '?'" :avatarUrl="me?.avatar_url" size="md" style="cursor:pointer" @click="goToProfile" />
        <div class="sidebar-footer-user" style="cursor:pointer" @click="goToProfile">
          <div class="sidebar-footer-name">{{ me?.username || '…' }}</div>
          <div class="sidebar-footer-status">{{ me?.email }}</div>
        </div>
        <button class="btn-icon" title="Logout" @click="logout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </div>
    </aside>

    <!-- Chat area: show placeholder when no chat is selected -->
    <div class="chat-area">
      <div class="chat-area-empty">
        <div class="chat-area-empty-icon">💬</div>
        <div class="chat-area-empty-text">Select a conversation or start a new one</div>
        <button class="btn btn-primary" style="margin-top:4px" @click="openCreate">New chat</button>
      </div>
    </div>

    <!-- New chat modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="closeCreate">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">New conversation</span>
          <button class="btn-icon" @click="closeCreate">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <div class="modal-body">
          <div class="toggle-tabs">
            <div class="toggle-tab" :class="{ active: !isGroup }" @click="isGroup = false">Direct</div>
            <div class="toggle-tab" :class="{ active: isGroup }" @click="isGroup = true">Group</div>
          </div>

          <div v-if="isGroup">
            <label class="form-label">Title</label>
            <input v-model="title" class="input" placeholder="My group name" />
          </div>

          <div v-if="isGroup">
            <label class="form-label">Description <span style="color:var(--text-3)">(optional)</span></label>
            <input v-model="description" class="input" placeholder="What's this group about?" />
          </div>

          <div>
            <label class="form-label">{{ isGroup ? 'Participants' : 'Username or email' }}</label>
            <input
              v-if="!isGroup"
              v-model="participantsText"
              class="input"
              placeholder="friend@example.com"
            />
            <textarea
              v-else
              v-model="participantsText"
              class="input"
              rows="3"
              placeholder="user1, user2, user3"
            />
            <p style="font-size:12px;color:var(--text-3);margin-top:4px">
              {{ isGroup ? 'Separate with commas, spaces, or new lines.' : 'Exactly one username or email.' }}
            </p>
          </div>

          <div v-if="createError" class="auth-error" style="margin:0">{{ createError }}</div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeCreate">Cancel</button>
          <button class="btn btn-primary" :disabled="creating || !canCreate" @click="createChat">
            {{ creating ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import UserAvatar from '../components/UserAvatar.vue'

const router = useRouter()
const chats = ref([])
const error = ref('')
const loading = ref(false)

const showCreate = ref(false)
const creating = ref(false)
const isGroup = ref(false)
const title = ref('')
const description = ref('')
const participantsText = ref('')
const createError = ref('')

const me = ref(null)
let es = null
let pingInterval = null

async function loadChats() {
  error.value = ''
  loading.value = true
  try {
    const data = await api.listChats()
    chats.value = data.items || []
  } catch (e) {
    error.value = e.message || 'Failed to load chats'
  } finally {
    loading.value = false
  }
}

async function logout() {
  await api.logout()
  router.push('/login')
}

function goToProfile() {
  router.push('/profile')
}

function parseParticipants(text) {
  return text.split(/[\s,\n]+/).map(s => s.trim()).filter(Boolean)
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const now = new Date()
  const diffDays = Math.floor((now - d) / 86400000)
  if (diffDays === 0) {
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
  }
  if (diffDays < 7) {
    return d.toLocaleDateString('en', { weekday: 'short' })
  }
  return d.toLocaleDateString('en', { month: 'short', day: 'numeric' })
}

function lastPreview(c) {
  const lm = c.last_message
  if (!lm) return 'No messages yet'
  if (typeof lm === 'string') return lm
  const content = lm.content || ''
  if (lm.deleted_at) return 'Message deleted'
  if (c.is_group) {
    const who = lm.sender_username || 'Someone'
    return `${who}: ${content}`
  }
  const prefix = lm.sender_username === me.value?.username ? 'You: ' : ''
  return prefix + content
}

function openChat(c) {
  bumpChat(c.id, { unread_count: 0 })
  router.push(`/chats/${c.id}`)
}

function openCreate() {
  createError.value = ''
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
  creating.value = false
  isGroup.value = false
  title.value = ''
  description.value = ''
  participantsText.value = ''
  createError.value = ''
}

const canCreate = computed(() => {
  const p = parseParticipants(participantsText.value)
  if (!isGroup.value) return p.length === 1
  return title.value.trim().length > 0 && p.length >= 1
})

async function createChat() {
  createError.value = ''
  creating.value = true
  try {
    const participants = parseParticipants(participantsText.value)
    const newChat = await api.createChat({
      isGroup: isGroup.value,
      title: title.value.trim(),
      description: description.value.trim() || null,
      participants,
    })
    closeCreate()
    // Optimistic insert — чат появляется мгновенно
    if (!chats.value.find(c => c.id === newChat.id)) {
      chats.value.unshift({
        id: newChat.id,
        is_group: newChat.is_group,
        title: newChat.title,
        display_name: newChat.title || newChat.peer_username || (isGroup.value ? 'Group chat' : 'New chat'),
        last_message: null,
        unread_count: 0,
        created_at: new Date().toISOString(),
      })
    }
    router.push(`/chats/${newChat.id}`)
  } catch (e) {
    createError.value = e.message || 'Failed to create chat'
  } finally {
    creating.value = false
  }
}

function bumpChat(chatId, patch) {
  const idx = chats.value.findIndex(c => c.id === chatId)
  if (idx === -1) return
  chats.value[idx] = { ...chats.value[idx], ...patch }
  chats.value.sort((a, b) => {
    const ta = a.last_message?.created_at ? Date.parse(a.last_message.created_at) : Date.parse(a.created_at || 0)
    const tb = b.last_message?.created_at ? Date.parse(b.last_message.created_at) : Date.parse(b.created_at || 0)
    return tb - ta
  })
}

async function connectAllChatsSse() {
  const sub = await api.subscribeAllChats()
  const params = new URLSearchParams()
  for (const t of sub.topics || []) params.append('topic', t)
  es = new EventSource(`/.well-known/mercure?${params.toString()}`, { withCredentials: true })
  es.onmessage = (evt) => {
    const payload = JSON.parse(evt.data)
    if (payload.type === 'message.created') {
      const m = payload.data
      const fromMe = m.sender === me.value?.username
      const cur = chats.value.find(c => c.id === m.chat_id)
      const prevUnread = cur?.unread_count || 0
      bumpChat(m.chat_id, {
        last_message: { content: m.content, created_at: m.created_at, sender_username: m.sender },
        unread_count: fromMe ? prevUnread : (prevUnread + 1),
      })
    }
  }
  es.onerror = () => {}
}

onMounted(async () => {
  await loadChats()
  me.value = await api.me()
  await connectAllChatsSse()
  // heartbeat for online status
  api.ping().catch(() => {})
  pingInterval = setInterval(() => api.ping().catch(() => {}), 30000)
})

onBeforeUnmount(() => {
  if (es) es.close()
  if (pingInterval) clearInterval(pingInterval)
})
</script>
