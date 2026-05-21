<template>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">💬</div>
        <span class="sidebar-logo-text">RealtimeChat</span>
        <button class="online-indicator" :class="{ active: showOnlinePanel }" :title="showOnlinePanel ? 'Close' : 'Online users'" @click="showOnlinePanel = !showOnlinePanel">
          <span class="online-indicator-dot"></span>{{ onlineUsers.length }} online
        </button>
        <button class="btn-icon" title="New chat" @click="openCreate">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <OnlineUsersPanel
        v-if="showOnlinePanel"
        :users="onlineUsers"
        @open-profile="openUserProfile"
        @close="showOnlinePanel = false"
      />
      <GlobalSearchPanel
        v-else-if="globalSearchOpen"
        @close="globalSearchOpen = false"
        @select="onGlobalSearchSelect"
      />
      <div v-else class="sidebar-chats">
        <div v-if="loading" style="padding:12px 16px;color:var(--text-3);font-size:13px;">Loading…</div>
        <div v-if="error" style="padding:12px 16px;color:var(--danger);font-size:13px;">{{ error }}</div>

        <template v-if="chats.length">
          <div class="chats-section-header">
            <span class="chats-section-label">Conversations</span>
            <button class="btn-icon chats-section-search-btn" title="Search all messages" @click="globalSearchOpen = true">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
          </div>
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
        <svg class="chat-area-empty-icon" viewBox="0 0 80 80" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 18a6 6 0 0 1 6-6h40a6 6 0 0 1 6 6v28a6 6 0 0 1-6 6H48l-8 10-8-10H20a6 6 0 0 1-6-6V18z"/>
          <line x1="26" y1="30" x2="54" y2="30"/>
          <line x1="26" y1="40" x2="46" y2="40"/>
        </svg>
        <div class="chat-area-empty-title">Your messages</div>
        <div class="chat-area-empty-text">Select a conversation or start a new one</div>
        <button class="btn btn-primary" style="margin-top:8px" @click="openCreate">New chat</button>
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
            <div v-if="isGroup && selectedUsers.length" class="user-chips">
              <span v-for="u in selectedUsers" :key="u.username" class="user-chip">
                {{ u.username }}
                <button type="button" class="user-chip-remove" @click="removeUser(u.username)">×</button>
              </span>
            </div>
            <div style="position:relative">
              <input
                v-model="userSearchQuery"
                class="input"
                :placeholder="isGroup ? 'Search users…' : 'Search by username or email'"
                autocomplete="off"
                @input="onUserSearchInput"
                @focus="showSuggestions = userSuggestions.length > 0"
                @blur="onSearchBlur"
              />
              <div v-if="showSuggestions && userSuggestions.length" class="user-suggestions">
                <button
                  v-for="u in userSuggestions"
                  :key="u.username"
                  type="button"
                  class="user-suggestion-item"
                  @mousedown.prevent
                  @click="selectUser(u)"
                >
                  <span class="user-suggestion-avatar">{{ u.username[0].toUpperCase() }}</span>
                  <span class="user-suggestion-name">{{ u.username }}</span>
                </button>
              </div>
            </div>
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

    <!-- User profile modal -->
    <UserProfileModal
      v-if="profileUsername"
      :username="profileUsername"
      :sidebarChats="chats"
      @close="profileUsername = null"
      @open-chat="(id) => { profileUsername = null; router.push(`/chats/${id}`) }"
      @go-profile="router.push('/profile')"
    />
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'
import UserAvatar from '../components/UserAvatar.vue'
import OnlineUsersPanel from '../components/OnlineUsersPanel.vue'
import UserProfileModal from '../components/UserProfileModal.vue'
import GlobalSearchPanel from '../components/GlobalSearchPanel.vue'

const router = useRouter()
const chats = ref([])
const error = ref('')
const loading = ref(false)

const showCreate = ref(false)
const creating = ref(false)
const isGroup = ref(false)
const title = ref('')
const description = ref('')
const createError = ref('')
const selectedUsers = ref([])
const userSearchQuery = ref('')
const userSuggestions = ref([])
const showSuggestions = ref(false)
let searchDebounce = null

const me = ref(null)
const showOnlinePanel = ref(false)
const globalSearchOpen = ref(false)
const onlineUsers = ref([])
const profileUsername = ref(null)
let es = null
let sseStopped = false
let sseDelay = 1000
let sseTimer = null
let sseGen = 0
let pingInterval = null
let onlineUsersInterval = null

async function loadOnlineUsers() {
  try {
    onlineUsers.value = await api.listOnlineUsers()
  } catch {}
}

function openUserProfile(username) {
  if (!username) return
  profileUsername.value = username
}

function onGlobalSearchSelect({ chatId, messageId }) {
  globalSearchOpen.value = false
  router.push({ path: `/chats/${chatId}`, query: { highlight: messageId } })
}

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
  createError.value = ''
  selectedUsers.value = []
  userSearchQuery.value = ''
  userSuggestions.value = []
  showSuggestions.value = false
  clearTimeout(searchDebounce)
}

const canCreate = computed(() => {
  if (!isGroup.value) return selectedUsers.value.length === 1
  return title.value.trim().length > 0 && selectedUsers.value.length >= 1
})

async function createChat() {
  createError.value = ''
  creating.value = true
  try {
    const participants = selectedUsers.value.map(u => u.username)
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

function onUserSearchInput() {
  clearTimeout(searchDebounce)
  const q = userSearchQuery.value.trim()
  if (!q) {
    userSuggestions.value = []
    showSuggestions.value = false
    return
  }
  searchDebounce = setTimeout(async () => {
    const results = await api.searchUsers(q)
    const selectedNames = new Set(selectedUsers.value.map(u => u.username))
    userSuggestions.value = results.filter(u => !selectedNames.has(u.username))
    showSuggestions.value = userSuggestions.value.length > 0
  }, 300)
}

function onSearchBlur() {
  setTimeout(() => { showSuggestions.value = false }, 150)
}

async function selectUser(user) {
  if (isGroup.value) {
    selectedUsers.value.push(user)
    userSearchQuery.value = ''
    userSuggestions.value = []
    showSuggestions.value = false
  } else {
    selectedUsers.value = [user]
    userSearchQuery.value = ''
    userSuggestions.value = []
    showSuggestions.value = false
    await createChat()
  }
}

function removeUser(username) {
  selectedUsers.value = selectedUsers.value.filter(u => u.username !== username)
}

async function reconnectSse() {
  sseStopped = true
  sseGen++
  clearTimeout(sseTimer)
  if (es) { es.close(); es = null }
  await connectAllChatsSse()
}

function bumpChat(chatId, patch) {
  const idx = chats.value.findIndex(c => c.id === chatId)
  if (idx === -1) return
  const arr = chats.value.map((c, i) => i === idx ? { ...c, ...patch } : c)
  arr.sort((a, b) => {
    const ta = a.last_message?.created_at ? Date.parse(a.last_message.created_at) : Date.parse(a.created_at || 0)
    const tb = b.last_message?.created_at ? Date.parse(b.last_message.created_at) : Date.parse(b.created_at || 0)
    return tb - ta
  })
  chats.value = arr
}

async function connectAllChatsSse() {
  sseStopped = false
  sseDelay = 1000
  const gen = ++sseGen

  const attempt = async () => {
    if (sseStopped || sseGen !== gen) return
    try {
      const sub = await api.subscribeAllChats()
      if (sseStopped || sseGen !== gen) return
      const params = new URLSearchParams()
      for (const t of sub.topics || []) params.append('topic', t)
      const source = new EventSource(`/.well-known/mercure?${params.toString()}`, { withCredentials: true })
      es = source

      source.onopen = () => { sseDelay = 1000 }
      source.onmessage = async (evt) => {
        const payload = JSON.parse(evt.data)
        if (payload.type === 'chat.created') {
          sseStopped = true
          sseGen++
          clearTimeout(sseTimer)
          if (es) { es.close(); es = null }
          await loadChats()
          await connectAllChatsSse()
          return
        }
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
      source.onerror = () => {
        source.close()
        if (sseStopped || sseGen !== gen) return
        sseTimer = setTimeout(attempt, sseDelay)
        sseDelay = Math.min(sseDelay * 2, 30000)
      }
    } catch {
      if (sseStopped || sseGen !== gen) return
      sseTimer = setTimeout(attempt, sseDelay)
      sseDelay = Math.min(sseDelay * 2, 30000)
    }
  }

  await attempt()
}

onMounted(async () => {
  await loadChats()
  me.value = await api.me()
  await connectAllChatsSse()
  api.ping().catch(() => {})
  loadOnlineUsers()
  pingInterval = setInterval(() => api.ping().catch(() => {}), 30000)
  onlineUsersInterval = setInterval(loadOnlineUsers, 15000)
})

onBeforeUnmount(() => {
  sseStopped = true
  clearTimeout(sseTimer)
  if (es) es.close()
  if (pingInterval) clearInterval(pingInterval)
  if (onlineUsersInterval) clearInterval(onlineUsersInterval)
})

watch(showOnlinePanel, (val) => { if (val) loadOnlineUsers() })
</script>

<style scoped>
.user-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.user-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px 2px 10px;
  background: var(--accent);
  color: #fff;
  border-radius: 12px;
  font-size: 13px;
}
.user-chip-remove {
  background: none;
  border: none;
  color: rgba(255,255,255,0.8);
  cursor: pointer;
  font-size: 15px;
  line-height: 1;
  padding: 0;
  display: flex;
  align-items: center;
}
.user-chip-remove:hover { color: #fff; }

.user-suggestions {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  z-index: 50;
  overflow: hidden;
  max-height: 220px;
  overflow-y: auto;
}
.user-suggestion-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 12px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  color: var(--text-1);
  font-size: 14px;
  transition: background 0.12s;
}
.user-suggestion-item:hover { background: var(--bg-3); }
.user-suggestion-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--accent);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}
.user-suggestion-name { font-weight: 500; }
</style>
