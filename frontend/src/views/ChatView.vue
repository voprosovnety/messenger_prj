<template>
  <div class="app-shell">
    <!-- Sidebar with chat list -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">💬</div>
        <span class="sidebar-logo-text">RealtimeChat</span>
        <button class="btn-icon" title="New chat" @click="showCreate = true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <div class="sidebar-chats">
        <p v-if="sidebarChats.length" class="chats-section-label">Conversations</p>
        <button
          v-for="c in sidebarChats"
          :key="c.id"
          class="chat-item"
          :class="{ active: c.id === chatId.value }"
          type="button"
          @click="router.push(`/chats/${c.id}`)"
        >
          <UserAvatar :username="c.display_name || c.id" size="md" />
          <div class="chat-item-info">
            <div class="chat-item-top">
              <span class="chat-item-name">{{ c.display_name || c.id }}</span>
              <span class="chat-item-time">{{ formatTimeShort(c.last_message?.created_at) }}</span>
            </div>
            <div class="chat-item-top" style="margin-top:1px">
              <span class="chat-item-preview">{{ sidebarPreview(c) }}</span>
              <span v-if="(c.unread_count || 0) > 0" class="unread-badge">{{ c.unread_count }}</span>
            </div>
          </div>
        </button>
      </div>

      <div class="sidebar-footer">
        <UserAvatar :username="me?.username || '?'" :avatarUrl="me?.avatar_url" size="md" style="cursor:pointer" @click="router.push('/profile')" />
        <div class="sidebar-footer-user" style="cursor:pointer" @click="router.push('/profile')">
          <div class="sidebar-footer-name">{{ me?.username || '…' }}</div>
          <div class="sidebar-footer-status">{{ me?.email }}</div>
        </div>
        <button class="btn-icon" title="Logout" @click="logout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </div>
    </aside>

    <!-- Main chat area -->
    <div class="chat-area">
      <!-- Header -->
      <div class="chat-header">
        <UserAvatar :username="chatTitle" size="md" />
        <div class="chat-header-info">
          <div class="chat-header-name">{{ chatTitle }}</div>
          <div class="chat-header-sub">
            <span v-if="!isGroup && peerUser">
              <span v-if="isPeerOnline" class="chat-header-online">● Online</span>
              <span v-else-if="peerUser.last_seen_at">Last seen {{ formatRelative(peerUser.last_seen_at) }}</span>
              <span v-else>Offline</span>
            </span>
            <span v-else-if="isGroup">{{ participants.length }} members</span>
          </div>
        </div>
        <div class="chat-header-actions">
          <button v-if="isGroup" class="btn-icon" :title="showMembersPanel ? 'Hide members' : 'Show members'" @click="showMembersPanel = !showMembersPanel">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </button>
          <button v-if="canDeleteChat" class="btn-icon" title="Delete chat" style="color:var(--danger)" @click="deleteChat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>
      </div>

      <!-- Messages + Members panel wrapper -->
      <div style="display:flex;flex:1;min-height:0;overflow:hidden">
        <!-- Messages -->
        <div style="display:flex;flex-direction:column;flex:1;min-width:0;overflow:hidden">
          <div ref="listEl" class="messages-area">
            <template v-for="g in grouped" :key="g.key">
              <div class="date-separator">
                <span class="date-separator-text">{{ g.title }}</span>
              </div>

              <template v-for="(m, idx) in g.items" :key="m.id">
                <div
                  class="message-row"
                  :class="{
                    own: isMine(m),
                    'same-sender': idx > 0 && g.items[idx-1].sender === m.sender && !g.items[idx-1].deleted_at
                  }"
                >
                  <!-- Avatar slot (others only) -->
                  <div class="message-avatar-slot">
                    <UserAvatar
                      v-if="!isMine(m) && (idx === g.items.length-1 || g.items[idx+1]?.sender !== m.sender)"
                      :username="m.sender"
                      :avatarUrl="m.sender_avatar_url"
                      size="sm"
                    />
                  </div>

                  <div class="message-bubble-wrap">
                    <!-- Sender name (group chats, others only) -->
                    <div v-if="isGroup && !isMine(m) && (idx === 0 || g.items[idx-1].sender !== m.sender)" class="message-sender-name">
                      {{ m.sender }}
                    </div>

                    <!-- Editing mode -->
                    <template v-if="isEditing(m)">
                      <textarea
                        v-model="editingText"
                        class="input"
                        style="width:100%;min-width:280px"
                        rows="2"
                        :disabled="busy"
                        @keydown.esc.prevent="cancelEdit"
                        @keydown.enter.exact.prevent="saveEdit(m)"
                      />
                      <div style="display:flex;gap:6px;margin-top:4px;justify-content:flex-end">
                        <button class="btn btn-ghost" style="font-size:13px;padding:5px 10px" :disabled="busy" @click="cancelEdit">Cancel</button>
                        <button class="btn btn-primary" style="font-size:13px;padding:5px 10px" :disabled="busy || !editingText.trim()" @click="saveEdit(m)">Save</button>
                      </div>
                    </template>

                    <!-- Normal bubble -->
                    <template v-else>
                      <div class="message-bubble-outer">
                        <!-- Actions: left of bubble for own messages (flex-direction: row-reverse) -->
                        <div v-if="isMine(m) && !m.deleted_at" class="message-actions">
                          <button class="btn-icon" style="padding:4px 6px;border-radius:4px" title="Edit" @click="startEdit(m)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          </button>
                          <button class="btn-icon" style="padding:4px 6px;border-radius:4px;color:var(--danger)" title="Delete" @click="removeMessage(m)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                          </button>
                        </div>
                        <div class="message-bubble" :class="{ deleted: !!m.deleted_at }">
                          <span v-if="m.deleted_at" style="font-style:italic">Message deleted</span>
                          <span v-else style="white-space:pre-wrap;word-break:break-word">{{ m.content }}</span>
                        </div>
                      </div>

                      <div class="message-meta">
                        <span class="message-time">{{ formatTime(m.created_at) }}</span>
                        <span v-if="m.edited_at && !m.deleted_at" class="message-edited">edited</span>
                        <span v-if="isMine(m) && !m.deleted_at" class="message-ticks" :class="{ read: peerReadId && idLE(m.id, peerReadId) }">
                          <template v-if="peerReadId && idLE(m.id, peerReadId)">✓✓</template>
                          <template v-else-if="peerDeliveredId && idLE(m.id, peerDeliveredId)">✓</template>
                        </span>
                      </div>
                    </template>
                  </div>
                </div>
              </template>
            </template>
          </div>

          <!-- Typing indicator -->
          <div class="typing-indicator">
            <span v-if="typingUser">{{ typingUser }} is typing…</span>
          </div>

          <!-- Composer -->
          <div class="composer">
            <textarea
              ref="composerEl"
              v-model="input"
              class="composer-input"
              placeholder="Type a message…"
              rows="1"
              @keydown="onKeydown"
              @input="onTyping"
            />
            <button class="composer-send" :disabled="!input.trim()" @click="send">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
          </div>
        </div>

        <!-- Members sidebar (group chats) -->
        <div v-if="isGroup && showMembersPanel" class="members-panel">
          <div class="members-panel-header">Members</div>

          <div v-if="isOwner" style="padding:10px 12px">
            <div v-if="!showAddMember">
              <button class="btn btn-ghost" style="width:100%;font-size:13px" @click="showAddMember = true">+ Add member</button>
            </div>
            <div v-else style="display:flex;flex-direction:column;gap:8px">
              <input
                v-model="participantInput"
                class="input"
                placeholder="username or email"
                style="font-size:13px"
                :disabled="busy"
                @keydown.enter.prevent="addParticipant"
              />
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost" style="flex:1;font-size:13px" @click="showAddMember = false">Cancel</button>
                <button class="btn btn-primary" style="flex:1;font-size:13px" :disabled="busy || !participantInput.trim()" @click="addParticipant">Add</button>
              </div>
            </div>
          </div>

          <div v-for="p in participants" :key="p.id" class="member-item">
            <UserAvatar :username="p.username" :avatarUrl="p.avatar_url" :isOnline="isUserOnline(p)" size="sm" />
            <div class="member-item-info">
              <div class="member-item-name">{{ p.username }}<span v-if="p.is_me" style="color:var(--text-3);font-weight:400;font-size:12px"> (you)</span></div>
              <div class="member-item-role">{{ p.role.toLowerCase() }}</div>
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

          <div v-if="error" style="padding:8px 12px;font-size:12px;color:var(--danger)">{{ error }}</div>
        </div>
      </div>
    </div>

    <!-- New chat modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">New conversation</span>
          <button class="btn-icon" @click="showCreate = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="toggle-tabs">
            <div class="toggle-tab" :class="{ active: !createIsGroup }" @click="createIsGroup = false">Direct</div>
            <div class="toggle-tab" :class="{ active: createIsGroup }" @click="createIsGroup = true">Group</div>
          </div>
          <div v-if="createIsGroup">
            <label class="form-label">Title</label>
            <input v-model="createTitle" class="input" placeholder="Group name" />
          </div>
          <div>
            <label class="form-label">{{ createIsGroup ? 'Participants' : 'Username or email' }}</label>
            <input v-if="!createIsGroup" v-model="createParticipants" class="input" placeholder="friend@example.com" />
            <textarea v-else v-model="createParticipants" class="input" rows="3" placeholder="user1, user2" />
          </div>
          <div v-if="createError" class="auth-error" style="margin:0">{{ createError }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="showCreate = false">Cancel</button>
          <button class="btn btn-primary" :disabled="creating" @click="createChat">
            {{ creating ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, nextTick, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import UserAvatar from '../components/UserAvatar.vue'

const route = useRoute()
const router = useRouter()
const chatId = computed(() => route.params.chatId)

const me = ref(null)
const chat = ref(null)
const participants = ref([])
const sidebarChats = ref([])

const peerDeliveredId = ref(null)
const peerReadId = ref(null)

const messages = ref([])
const input = ref('')
const editingId = ref(null)
const editingText = ref('')
const busy = ref(false)
const error = ref('')

const showMembersPanel = ref(false)
const showAddMember = ref(false)
const participantInput = ref('')

const showCreate = ref(false)
const createIsGroup = ref(false)
const createTitle = ref('')
const createParticipants = ref('')
const createError = ref('')
const creating = ref(false)

const typingUser = ref('')
let typingTimeout = null
let typingDebounce = null

const listEl = ref(null)
let es = null
let sidebarEs = null
let pingInterval = null

// ─── computed ────────────────────────────────────────────────────
const chatTitle = computed(() => chat.value?.display_name || 'Chat')
const isGroup = computed(() => !!chat.value?.is_group)
const isOwner = computed(() => chat.value?.my_role === 'OWNER')
const canDeleteChat = computed(() => !isGroup.value || isOwner.value)

const peerUser = computed(() => {
  if (isGroup.value) return null
  return participants.value.find(p => !p.is_me) || null
})

function isUserOnline(user) {
  if (!user?.last_seen_at) return false
  return (Date.now() - new Date(user.last_seen_at).getTime()) < 65000
}

const isPeerOnline = computed(() => peerUser.value ? isUserOnline(peerUser.value) : false)

const grouped = computed(() => {
  const groups = []
  for (const m of messages.value) {
    const key = dayKey(m.created_at)
    const last = groups[groups.length - 1]
    if (!last || last.key !== key) {
      groups.push({ key, title: formatDateHeader(m.created_at), items: [m] })
    } else {
      last.items.push(m)
    }
  }
  return groups
})

// ─── helpers ─────────────────────────────────────────────────────
function myId() { return me.value?.username || '' }
function isMine(m) { return m.sender === myId() }
function isEditing(m) { return editingId.value === m.id }

function idLE(a, b) {
  if (!a || !b) return false
  return String(a) <= String(b)
}

function dayKey(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return 'unknown'
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
}

function formatTime(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
}

function formatTimeShort(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const now = new Date()
  if (d.toDateString() === now.toDateString()) {
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
  }
  return d.toLocaleDateString('en', { month: 'short', day: 'numeric' })
}

function formatDateHeader(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const now = new Date()
  const diffDays = Math.floor((now - d) / 86400000)
  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return 'Yesterday'
  return d.toLocaleDateString('en', { year: 'numeric', month: 'long', day: 'numeric' })
}

function formatRelative(iso) {
  if (!iso) return 'a while ago'
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function sidebarPreview(c) {
  const lm = c.last_message
  if (!lm) return 'No messages'
  const prefix = c.is_group && lm.sender_username ? lm.sender_username + ': ' : (lm.sender_username === me.value?.username ? 'You: ' : '')
  return prefix + (lm.content || '')
}

// ─── scrolling ────────────────────────────────────────────────────
function isNearBottom(thresholdPx = 100) {
  const el = listEl.value
  if (!el) return true
  return el.scrollHeight - el.scrollTop - el.clientHeight < thresholdPx
}

async function scrollToBottom() {
  await nextTick()
  if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight
}

// ─── data loading ─────────────────────────────────────────────────
async function load() {
  try {
    const [chatData, msgData] = await Promise.all([
      api.getChat(chatId.value),
      api.listMessages(chatId.value),
    ])
    chat.value = chatData
    participants.value = chatData.participants || []
    messages.value = msgData.items || []
    peerDeliveredId.value = msgData.peer_delivered_message_id || null
    peerReadId.value = msgData.peer_read_message_id || null
  } catch {
    router.push('/')
    return
  }
  const last = messages.value[messages.value.length - 1]
  if (last) await api.markDelivered(chatId.value, last.id).catch(() => {})
  await scrollToBottom()
}

async function loadSidebarChats() {
  try {
    const data = await api.listChats()
    sidebarChats.value = data.items || []
  } catch {}
}

// ─── receipts ─────────────────────────────────────────────────────
async function markReadIfPossible() {
  if (document.visibilityState !== 'visible') return
  const last = messages.value[messages.value.length - 1]
  if (!last) return
  await api.markRead(chatId.value, last.id).catch(() => {})
}

// ─── typing ───────────────────────────────────────────────────────
function onTyping() {
  clearTimeout(typingDebounce)
  typingDebounce = setTimeout(() => {
    api.sendTyping(chatId.value).catch(() => {})
  }, 400)
}

// ─── SSE ─────────────────────────────────────────────────────────
async function connectSse() {
  try {
    const res = await api.getMercureCookie(chatId.value)
    const url = `/.well-known/mercure?topic=${encodeURIComponent(res.topic)}`
    es = new EventSource(url, { withCredentials: true })

    es.onmessage = async (evt) => {
      const payload = JSON.parse(evt.data)
      const shouldStick = isNearBottom()

      if (payload.type === 'message.created') {
        messages.value.push(payload.data)
        await api.markDelivered(chatId.value, payload.data.id).catch(() => {})
        await markReadIfPossible()
        if (shouldStick) await scrollToBottom()
        return
      }
      if (payload.type === 'message.edited') {
        const i = messages.value.findIndex(m => m.id === payload.data.id)
        if (i !== -1) Object.assign(messages.value[i], payload.data)
        return
      }
      if (payload.type === 'message.deleted') {
        const i = messages.value.findIndex(m => m.id === payload.data.id)
        if (i !== -1) messages.value[i].deleted_at = payload.data.deleted_at
        return
      }
      if (payload.type === 'chat.delivered') {
        if (payload.data?.user && payload.data.user !== myId()) {
          const id = payload.data.last_delivered_message_id
          if (id && (!peerDeliveredId.value || String(id) > String(peerDeliveredId.value))) peerDeliveredId.value = id
        }
        return
      }
      if (payload.type === 'chat.read') {
        if (payload.data?.user && payload.data.user !== myId()) {
          const id = payload.data.last_read_message_id
          if (id && (!peerReadId.value || String(id) > String(peerReadId.value))) peerReadId.value = id
        }
        return
      }
      if (payload.type === 'user.typing') {
        const d = payload.data
        if (d.username !== myId()) {
          typingUser.value = d.username
          clearTimeout(typingTimeout)
          typingTimeout = setTimeout(() => { typingUser.value = '' }, 3000)
        }
        return
      }
    }
    es.onerror = () => {}
  } catch {
    router.push('/')
  }
}

async function connectSidebarSse() {
  try {
    const sub = await api.subscribeAllChats()
    const params = new URLSearchParams()
    for (const t of sub.topics || []) params.append('topic', t)
    sidebarEs = new EventSource(`/.well-known/mercure?${params.toString()}`, { withCredentials: true })
    sidebarEs.onmessage = (evt) => {
      const payload = JSON.parse(evt.data)
      if (payload.type === 'message.created') {
        const m = payload.data
        const fromMe = m.sender === myId()
        const idx = sidebarChats.value.findIndex(c => c.id === m.chat_id)
        if (idx !== -1) {
          const cur = sidebarChats.value[idx]
          sidebarChats.value[idx] = {
            ...cur,
            last_message: { content: m.content, created_at: m.created_at, sender_username: m.sender },
            unread_count: (m.chat_id === chatId.value || fromMe) ? cur.unread_count : (cur.unread_count || 0) + 1,
          }
        }
      }
    }
    sidebarEs.onerror = () => {}
  } catch {}
}

// ─── actions ──────────────────────────────────────────────────────
async function send() {
  const text = input.value.trim()
  if (!text) return
  input.value = ''
  await api.sendMessage(chatId.value, text).catch(() => {})
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

function startEdit(m) {
  if (m.deleted_at) return
  editingId.value = m.id
  editingText.value = m.content || ''
}

function cancelEdit() {
  editingId.value = null
  editingText.value = ''
}

async function saveEdit(m) {
  const text = editingText.value.trim()
  if (!text) return
  busy.value = true
  try {
    const updated = await api.editMessage(chatId.value, m.id, text)
    const i = messages.value.findIndex(x => x.id === m.id)
    if (i !== -1) Object.assign(messages.value[i], updated)
    cancelEdit()
  } catch (e) {
    error.value = e.message
  } finally { busy.value = false }
}

async function removeMessage(m) {
  if (!confirm('Delete this message?')) return
  busy.value = true
  try {
    await api.deleteMessage(chatId.value, m.id)
    const i = messages.value.findIndex(x => x.id === m.id)
    if (i !== -1) messages.value[i].deleted_at = new Date().toISOString()
    if (editingId.value === m.id) cancelEdit()
  } catch (e) { error.value = e.message }
  finally { busy.value = false }
}

async function addParticipant() {
  const ident = participantInput.value.trim()
  if (!ident) return
  busy.value = true
  try {
    await api.addChatMember(chatId.value, ident)
    participantInput.value = ''
    showAddMember.value = false
    const data = await api.getChat(chatId.value)
    chat.value = data
    participants.value = data.participants || []
  } catch (e) { error.value = e.message }
  finally { busy.value = false }
}

async function removeParticipant(userId) {
  busy.value = true
  try {
    await api.removeChatMember(chatId.value, userId)
    const data = await api.getChat(chatId.value)
    chat.value = data
    participants.value = data.participants || []
  } catch (e) { error.value = e.message }
  finally { busy.value = false }
}

async function deleteChat() {
  if (!confirm('Delete this chat permanently?')) return
  await api.deleteChat(chatId.value)
  router.push('/')
}

async function logout() {
  await api.logout()
  router.push('/login')
}

async function createChat() {
  createError.value = ''
  creating.value = true
  try {
    const parts = createParticipants.value.split(/[\s,\n]+/).map(s => s.trim()).filter(Boolean)
    const newChat = await api.createChat({
      isGroup: createIsGroup.value,
      title: createTitle.value.trim(),
      participants: parts,
    })
    showCreate.value = false
    createTitle.value = ''
    createParticipants.value = ''
    // Optimistic insert — появляется мгновенно без перезагрузки
    if (!sidebarChats.value.find(c => c.id === newChat.id)) {
      sidebarChats.value.unshift({
        id: newChat.id,
        is_group: newChat.is_group,
        title: newChat.title,
        display_name: newChat.title || newChat.peer_username || (createIsGroup.value ? 'Group chat' : 'New chat'),
        last_message: null,
        unread_count: 0,
        created_at: new Date().toISOString(),
      })
    }
    router.push(`/chats/${newChat.id}`)
  } catch (e) { createError.value = e.message }
  finally { creating.value = false }
}

// ─── watcher: reloads chat data when chatId changes (same component reuse) ───
watch(chatId, async (newId, oldId) => {
  if (!newId || newId === oldId) return
  if (es) { es.close(); es = null }
  clearTimeout(typingTimeout)
  clearTimeout(typingDebounce)
  messages.value = []
  chat.value = null
  participants.value = []
  peerDeliveredId.value = null
  peerReadId.value = null
  typingUser.value = ''
  cancelEdit()
  error.value = ''
  showMembersPanel.value = false
  await load()
  await connectSse()
  await markReadIfPossible()
}, { immediate: false })

// ─── lifecycle ────────────────────────────────────────────────────
onMounted(async () => {
  [me.value] = await Promise.all([api.me()])
  await Promise.all([load(), loadSidebarChats()])
  await Promise.all([connectSse(), connectSidebarSse()])
  await markReadIfPossible()
  document.addEventListener('visibilitychange', markReadIfPossible)
  api.ping().catch(() => {})
  pingInterval = setInterval(() => api.ping().catch(() => {}), 30000)
})

onBeforeUnmount(() => {
  if (es) es.close()
  if (sidebarEs) sidebarEs.close()
  if (pingInterval) clearInterval(pingInterval)
  clearTimeout(typingTimeout)
  clearTimeout(typingDebounce)
  document.removeEventListener('visibilitychange', markReadIfPossible)
})
</script>
