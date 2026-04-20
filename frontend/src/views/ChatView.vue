<script setup>
import { onMounted, onBeforeUnmount, ref, nextTick, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'

const route = useRoute()
const router = useRouter()
const chatId = route.params.chatId

const me = ref(null)
const chat = ref(null)
const participants = ref([])
const participantInput = ref('')
const busy = ref(false)
const error = ref('')
const showMembersPanel = ref(false)
const showAddParticipant = ref(false)

const peerDeliveredId = ref(null)
const peerReadId = ref(null)

const messages = ref([])
const input = ref('')
const editingId = ref(null)
const editingText = ref('')
let es = null

const listEl = ref(null)

// ---------- helpers ----------
function myId() {
  // backend сейчас отдаёт /api/me как { user: <identifier>, roles: [...] } или ты сделал me() с username
  return me.value?.username || me.value?.user || ''
}

function isMine(m) {
  return m.sender === myId()
}

function isEditing(m) {
  return editingId.value === m.id
}

const chatTitle = computed(() => chat.value?.display_name || 'Chat')
const isGroup = computed(() => !!chat.value?.is_group)
const isOwner = computed(() => chat.value?.my_role === 'OWNER')
const canDeleteChat = computed(() => !isGroup.value || isOwner.value)

function idLE(a, b) {
  if (!a || !b) return false
  return String(a) <= String(b)
}

function updatePeerDelivered(id) {
  if (!id) return
  if (!peerDeliveredId.value || String(id) > String(peerDeliveredId.value)) {
    peerDeliveredId.value = id
  }
}

function updatePeerRead(id) {
  if (!id) return
  if (!peerReadId.value || String(id) > String(peerReadId.value)) {
    peerReadId.value = id
  }
}

function parseDateIso(iso) {
  try { return new Date(iso) } catch { return null }
}

function formatTime(iso) {
  const d = parseDateIso(iso)
  if (!d) return ''
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}

function formatDateHeader(iso) {
  const d = parseDateIso(iso)
  if (!d) return ''
  // “11 Apr 2026” / “11.04.2026” — выбери что нравится.
  // Сделаю компактно как DD.MM.YYYY:
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yy = d.getFullYear()
  return `${dd}.${mm}.${yy}`
}

function dayKey(iso) {
  const d = parseDateIso(iso)
  if (!d) return 'unknown'
  // YYYY-MM-DD
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// Группируем сообщения по дням для “разделителей дат”
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

// ---------- scrolling ----------
function isNearBottom(el, thresholdPx = 80) {
  const diff = el.scrollHeight - el.scrollTop - el.clientHeight
  return diff < thresholdPx
}

async function scrollToBottom() {
  await nextTick()
  const el = listEl.value
  if (!el) return
  el.scrollTop = el.scrollHeight
}

// ---------- delivered/read ----------
async function load() {
  try {
    const chatData = await api.getChat(chatId)
    chat.value = chatData
    participants.value = chatData.participants || []

    const data = await api.listMessages(chatId)
    messages.value = data.items || []
    peerDeliveredId.value = data.peer_delivered_message_id || null
    peerReadId.value = data.peer_read_message_id || null
  } catch (e) {
    // чат удалён или нет доступа
    router.push('/')
    return
  }

  const last = messages.value[messages.value.length - 1]
  if (last) {
    await api.markDelivered(chatId, last.id)
  }

  await scrollToBottom()
}

async function markReadIfPossible() {
  const last = messages.value[messages.value.length - 1]
  if (!last) return
  if (document.visibilityState !== 'visible') return
  await api.markRead(chatId, last.id)
}

async function connectSse() {
  let topic
  try {
    const res = await api.getMercureCookie(chatId)
    topic = res.topic
  } catch (e) {
    router.push('/')
    return
  }

  const url = `/.well-known/mercure?topic=${encodeURIComponent(topic)}`
  es = new EventSource(url, { withCredentials: true })

  es.onmessage = async (evt) => {
    const payload = JSON.parse(evt.data)

    // фиксируем: был ли пользователь у низа ДО добавления сообщения
    const el = listEl.value
    const shouldStick = el ? isNearBottom(el) : true

    if (payload.type === 'message.created') {
      messages.value.push(payload.data)

      // delivered: как только мы получили событие (то есть “доставлено на устройство”)
      // NOTE: это упрощение, но для учебного проекта ок
      await api.markDelivered(chatId, payload.data.id)

      // read: если вкладка активна
      await markReadIfPossible()

      if (shouldStick) await scrollToBottom()
      return
    }

    if (payload.type === 'message.edited') {
      const i = messages.value.findIndex(m => m.id === payload.data.id)
      if (i !== -1) {
        messages.value[i].content = payload.data.content
        messages.value[i].edited_at = payload.data.edited_at
      }
      return
    }

    if (payload.type === 'message.deleted') {
      const i = messages.value.findIndex(m => m.id === payload.data.id)
      if (i !== -1) {
        messages.value[i].deleted_at = payload.data.deleted_at
      }
      return
    }

    if (payload.type === 'chat.delivered') {
      if (payload.data?.user && payload.data.user !== myId()) {
        updatePeerDelivered(payload.data.last_delivered_message_id || null)
      }
      return
    }

    if (payload.type === 'chat.read') {
      if (payload.data?.user && payload.data.user !== myId()) {
        updatePeerRead(payload.data.last_read_message_id || null)
      }
      return
    }
  }

  es.onerror = (e) => {
    console.log('SSE error', e)
  }
}

async function send() {
  const text = input.value.trim()
  if (!text) return
  input.value = ''
  await api.sendMessage(chatId, text)
}

function startEdit(message) {
  if (message.deleted_at) return
  editingId.value = message.id
  editingText.value = message.content || ''
}

function cancelEdit() {
  editingId.value = null
  editingText.value = ''
}

async function saveEdit(message) {
  const text = editingText.value.trim()
  if (!text) return

  busy.value = true
  error.value = ''
  try {
    const updated = await api.editMessage(chatId, message.id, text)
    const i = messages.value.findIndex(m => m.id === message.id)
    if (i !== -1) {
      messages.value[i].content = updated.content
      messages.value[i].edited_at = updated.edited_at
    }
    cancelEdit()
  } catch (e) {
    error.value = e.message || 'Failed to edit message'
  } finally {
    busy.value = false
  }
}

async function removeMessage(message) {
  if (!confirm('Delete this message?')) return

  busy.value = true
  error.value = ''
  try {
    await api.deleteMessage(chatId, message.id)
    const i = messages.value.findIndex(m => m.id === message.id)
    if (i !== -1) {
      messages.value[i].deleted_at = new Date().toISOString()
    }
    if (editingId.value === message.id) cancelEdit()
  } catch (e) {
    error.value = e.message || 'Failed to delete message'
  } finally {
    busy.value = false
  }
}

async function addParticipant() {
  const ident = participantInput.value.trim()
  if (!ident) return

  busy.value = true
  error.value = ''
  try {
    await api.addChatMember(chatId, ident)
    participantInput.value = ''
    showAddParticipant.value = false
    const data = await api.getChat(chatId)
    chat.value = data
    participants.value = data.participants || []
  } catch (e) {
    error.value = e.message || 'Failed to add participant'
  } finally {
    busy.value = false
  }
}

async function removeParticipant(userId) {
  busy.value = true
  error.value = ''
  try {
    await api.removeChatMember(chatId, userId)
    const data = await api.getChat(chatId)
    chat.value = data
    participants.value = data.participants || []
  } catch (e) {
    error.value = e.message || 'Failed to remove participant'
  } finally {
    busy.value = false
  }
}

async function deleteChat() {
  if (!confirm('Delete this chat permanently?')) return
  await api.deleteChat(chatId)
  router.push('/')
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

onMounted(async () => {
  me.value = await api.me()
  await load()
  await connectSse()
  await markReadIfPossible()
  document.addEventListener('visibilitychange', markReadIfPossible)
})

onBeforeUnmount(() => {
  if (es) es.close()
  document.removeEventListener('visibilitychange', markReadIfPossible)
})
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="title">
        <div class="h">{{ chatTitle }}</div>
        <div v-if="isGroup" class="sub">{{ participants.length }} participants</div>
      </div>
      <div class="actions">
        <button v-if="isGroup" class="btn" @click="showMembersPanel = !showMembersPanel">
          {{ showMembersPanel ? 'Hide participants' : 'Participants' }}
        </button>
        <button class="btn" @click="router.push('/')">Back</button>
        <button v-if="canDeleteChat" class="btn danger" @click="deleteChat">Delete</button>
      </div>
    </header>

    <main class="chat">
      <div class="chatBody">
        <div class="chatMain">
          <div ref="listEl" class="list">
            <template v-for="g in grouped" :key="g.key">
              <div class="day">{{ g.title }}</div>

              <div v-for="m in g.items" :key="m.id" class="row" :class="{ mine: isMine(m) }">
                <div class="bubble">
                  <div class="meta">
                    <span class="sender">{{ m.sender }}</span>
                    <span class="time">{{ formatTime(m.created_at) }}</span>
                  </div>

                  <div v-if="m.deleted_at" class="deleted">deleted</div>
                  <template v-else-if="isEditing(m)">
                    <textarea
                      v-model="editingText"
                      class="input editInput"
                      rows="3"
                      :disabled="busy"
                      @keydown.esc.prevent="cancelEdit"
                    />
                    <div class="messageActions">
                      <button class="btn primary" :disabled="busy || !editingText.trim()" @click="saveEdit(m)">Save</button>
                      <button class="btn" :disabled="busy" @click="cancelEdit">Cancel</button>
                    </div>
                  </template>
                  <div v-else class="text">{{ m.content }}</div>

                  <div class="flags">
                    <span v-if="m.edited_at && !m.deleted_at" class="edited">edited</span>

                    <span v-if="isMine(m) && !m.deleted_at" class="status">
                      <span v-if="peerReadId && idLE(m.id, peerReadId)">✓✓</span>
                      <span v-else-if="peerDeliveredId && idLE(m.id, peerDeliveredId)">✓</span>
                    </span>
                  </div>

                  <div v-if="isMine(m) && !m.deleted_at && !isEditing(m)" class="messageActions">
                    <button class="actionLink" @click="startEdit(m)">Edit</button>
                    <button class="actionLink dangerText" @click="removeMessage(m)">Delete</button>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <div class="composer">
            <textarea v-model="input" class="input composerInput" rows="2" placeholder="message..." @keydown="onKeydown" />
            <button class="btn primary" @click="send">Send</button>
          </div>
        </div>

        <aside v-if="isGroup && showMembersPanel" class="membersSidebar">
          <div class="members-head">
            <div>
              <div class="members-title">Participants</div>
              <div class="members-sub">{{ participants.length }} in chat</div>
            </div>
            <div v-if="isOwner" class="owner-badge">owner</div>
          </div>

          <button v-if="isOwner" class="btn membersAction" @click="showAddParticipant = !showAddParticipant">
            {{ showAddParticipant ? 'Cancel add' : 'Add participant' }}
          </button>

          <div v-if="isOwner && showAddParticipant" class="memberAddCard">
            <input
              v-model="participantInput"
              class="input sidebarInput"
              placeholder="username or email"
              :disabled="busy"
              @keydown.enter.prevent="addParticipant"
            />
            <button class="btn primary" :disabled="busy" @click="addParticipant">Add</button>
          </div>

          <div class="members-list">
            <div v-for="p in participants" :key="p.id" class="member-item">
              <div class="member-info">
                <div class="member-name">
                  {{ p.username }}
                  <span v-if="p.is_me" class="member-me">you</span>
                </div>
                <div class="member-role">{{ p.role }}</div>
              </div>

              <button
                v-if="isOwner && !p.is_me && p.role !== 'OWNER'"
                class="btn member-remove"
                :disabled="busy"
                @click="removeParticipant(p.id)"
              >
                Remove
              </button>
            </div>
          </div>

          <div v-if="error" class="members-error">{{ error }}</div>
        </aside>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* страница: фиксированная высота, чтобы скроллился чат, а не весь документ */
.page {
  height: calc(100vh - 40px);
  max-width: 860px;
  margin: 20px auto;
  display: flex;
  flex-direction: column;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
}

.title .h {
  font-size: 18px;
  font-weight: 700;
}

.title .sub {
  opacity: 0.7;
  font-size: 12px;
}

.actions {
  display: flex;
  gap: 10px;
}

.chat {
  margin-top: 12px;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  min-height: 0;
  flex: 1;
  overflow: hidden;
}

.chatBody {
  display: flex;
  flex: 1;
  min-height: 0;
}

.chatMain {
  display: flex;
  flex: 1;
  min-width: 0;
  min-height: 0;
  flex-direction: column;
}

.list {
  padding: 12px;
  overflow: auto;
  flex: 1;
  min-height: 0;
}

.day {
  display: flex;
  justify-content: center;
  margin: 10px 0;
  opacity: 0.7;
  font-size: 12px;
}

.row {
  display: flex;
  justify-content: flex-start;
  margin: 8px 0;
}

.row.mine {
  justify-content: flex-end;
}

.bubble {
  max-width: 72%;
  border: 1px solid #2a2a2a;
  border-radius: 14px;
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.03);
}

.row.mine .bubble {
  background: rgba(120, 100, 255, 0.10);
}

.meta {
  display: flex;
  gap: 8px;
  justify-content: space-between;
  font-size: 12px;
  margin-bottom: 6px;
  opacity: 0.85;
}

.sender {
  font-weight: 600;
}

.time {
  opacity: 0.75;
  white-space: nowrap;
}

.text {
  white-space: pre-wrap;
  word-break: break-word;
}

.editInput {
  width: 100%;
  margin-top: 2px;
}

.deleted {
  opacity: 0.6;
  font-style: italic;
}

.flags {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 6px;
  font-size: 12px;
  opacity: 0.75;
}

.edited {
  font-style: italic;
}

.status {
  white-space: nowrap;
}

.messageActions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 8px;
}

.actionLink {
  border: 0;
  background: transparent;
  color: inherit;
  opacity: 0.72;
  cursor: pointer;
  padding: 0;
  font-size: 12px;
}

.dangerText {
  color: #ff9a9a;
}

.composer {
  display: flex;
  gap: 10px;
  padding: 12px;
  border-top: 1px solid #2a2a2a;
  align-items: flex-end;
}

.input {
  flex: 1;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
  padding: 10px 12px;
  resize: none;
  background: transparent;
  color: inherit;
  box-sizing: border-box;
}

.composerInput {
  min-width: 0;
}

.btn {
  border: 1px solid #2a2a2a;
  background: transparent;
  color: inherit;
  border-radius: 10px;
  padding: 8px 12px;
  cursor: pointer;
}

.btn.primary {
  background: rgba(120, 100, 255, 0.18);
}

.btn.danger {
  border-color: #7a1f1f;
  background: rgba(255, 80, 80, 0.12);
}

.membersSidebar {
  width: 300px;
  border-left: 1px solid #2a2a2a;
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: rgba(255, 255, 255, 0.02);
}

.members-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.members-title {
  font-size: 14px;
  font-weight: 700;
}

.members-sub,
.owner-badge,
.member-role,
.member-me {
  font-size: 12px;
  opacity: 0.68;
}

.membersAction {
  width: 100%;
}

.memberAddCard {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid #2a2a2a;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.02);
}

.sidebarInput {
  width: 100%;
}

.members-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow: auto;
  min-height: 0;
}

.member-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
  padding: 10px;
}

.member-info {
  min-width: 0;
}

.member-name {
  font-weight: 600;
  word-break: break-word;
}

.member-remove {
  padding: 6px 10px;
  flex-shrink: 0;
}

.members-error {
  color: #ff8d8d;
  font-size: 12px;
}

@media (max-width: 900px) {
  .page {
    padding: 0 12px;
  }

  .chatBody {
    flex-direction: column;
  }

  .membersSidebar {
    width: auto;
    border-left: 0;
    border-top: 1px solid #2a2a2a;
  }
}

@media (max-width: 640px) {
  .topbar {
    flex-direction: column;
    align-items: stretch;
  }

  .actions {
    flex-wrap: wrap;
  }
}
</style>
