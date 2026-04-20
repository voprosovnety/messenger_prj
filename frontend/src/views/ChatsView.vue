<script setup>
import { onMounted, onBeforeUnmount, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const router = useRouter()
const chats = ref([])

const error = ref('')
const loading = ref(false)

// create chat modal
const showCreate = ref(false)
const creating = ref(false)
const isGroup = ref(false)
const title = ref('')
const participantsText = ref('')
const createError = ref('')

const me = ref(null)
let es = null


async function loadChats() {
  error.value = ''
  loading.value = true
  try {
    const data = await api.listChats()
    chats.value = data.items || []
  } catch (e) {
    error.value = e.message || 'failed to load chats'
  } finally {
    loading.value = false
  }
}

async function logout() {
  await api.logout()
  router.push('/login')
}

function parseParticipants(text) {
  return text
    .split(/[\s,]+/g)
    .map(s => s.trim())
    .filter(Boolean)
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${hh}:${mm}`
}

function lastPreview(c) {
  const lm = c.last_message
  if (!lm) return 'No messages yet'

  // если вдруг пришла строка
  if (typeof lm === 'string') return lm

  const content = lm.content || ''
  if (c.is_group) {
    const who = lm.sender_username || 'someone'
    return `${who}: ${content}`
  }
  return content
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
  participantsText.value = ''
  createError.value = ''
}

const canCreate = computed(() => {
  const participants = parseParticipants(participantsText.value)
  if (!isGroup.value) return participants.length === 1
  return title.value.trim().length > 0 && participants.length >= 1
})

async function createChat() {
  createError.value = ''
  creating.value = true
  try {
    const participants = parseParticipants(participantsText.value)

    if (!isGroup.value) {
      if (participants.length !== 1) throw new Error('For DM provide exactly 1 username/email')
    } else {
      if (!title.value.trim()) throw new Error('Title is required for group chat')
      if (participants.length < 1) throw new Error('Group chat requires at least 1 participant')
    }

    const chat = await api.createChat({
      isGroup: isGroup.value,
      title: title.value.trim(),
      participants,
    })

    closeCreate()
    await loadChats()
    router.push(`/chats/${chat.id}`)
  } catch (e) {
    createError.value = e.message || 'create chat failed'
  } finally {
    creating.value = false
  }
}

function myId() {
  return me.value?.username || me.value?.user || ''
}

function bumpChat(chatId, patch) {
  const idx = chats.value.findIndex(c => c.id === chatId)
  if (idx === -1) return
  chats.value[idx] = { ...chats.value[idx], ...patch }
  chats.value.sort((a, b) => {
    const ta = a.last_at ? Date.parse(a.last_at) : Date.parse(a.created_at || 0)
    const tb = b.last_at ? Date.parse(b.last_at) : Date.parse(b.created_at || 0)
    return tb - ta
  })
}

async function connectAllChatsSse() {
  const sub = await api.subscribeAllChats()

  // Mercure: несколько topic параметров
  const params = new URLSearchParams()
  for (const t of sub.topics || []) params.append('topic', t)

  es = new EventSource(`/.well-known/mercure?${params.toString()}`, { withCredentials: true })

  es.onmessage = (evt) => {
    const payload = JSON.parse(evt.data)

    if (payload.type === 'message.created') {
      const m = payload.data
      const fromMe = m.sender === myId()

      // last message preview
      const cur = chats.value.find(c => c.id === m.chat_id)
      const prevUnread = cur?.unread_count || 0

      bumpChat(m.chat_id, {
        last_at: m.created_at,
        last_message: {
          content: m.content,
          created_at: m.created_at,
          sender_username: m.sender, // у тебя sender уже username
        },
        unread_count: fromMe ? prevUnread : (prevUnread + 1),
      })
    }

    // Можно добавить обработку deleted/edited тоже — обновлять preview если надо
  }

  es.onerror = (e) => console.log('Chats SSE error', e)
}

onMounted(async () => {
  await loadChats()
  me.value = await api.me()
  await connectAllChatsSse()
})

onBeforeUnmount(() => {
  if (es) es.close()
})

</script>

<template>
  <div class="page">
    <header class="topbar">
      <div>
        <div class="h">Chats</div>
        <div class="sub">Your conversations</div>
      </div>

      <div class="actions">
        <button class="btn primary" @click="openCreate">New chat</button>
        <button class="btn" @click="logout">Logout</button>
      </div>
    </header>

    <div v-if="error" class="error">{{ error }}</div>

    <main class="list">
      <div v-if="loading" class="muted">Loading...</div>

      <button v-for="c in chats" :key="c.id" class="chatItem" @click="openChat(c)" type="button">
        <div class="left">
          <div class="row1">
            <div class="name">
              {{ c.display_name || c.title || c.id }}
            </div>

            <div class="metaRight">
              <span class="time">{{ formatTime(c.last_message?.created_at || c.last_at) }}</span>
              <span v-if="(c.unread_count || 0) > 0" class="badge">{{ c.unread_count }}</span>
            </div>
          </div>

          <div class="row2">
            <div class="preview">
              {{ lastPreview(c) }}
            </div>
          </div>
        </div>
      </button>

      <div v-if="!loading && chats.length === 0" class="muted">
        No chats yet. Create one.
      </div>
    </main>

    <!-- modal -->
    <div v-if="showCreate" class="overlay" @click.self="closeCreate">
      <div class="modal">
        <div class="modalHead">
          <div>
            <div class="modalTitle">New chat</div>
            <div class="modalSub">Start a direct message or create a group.</div>
          </div>
          <button class="btn ghost" @click="closeCreate">Close</button>
        </div>

        <div class="modeSwitch">
          <button class="modeBtn" :class="{ active: !isGroup }" type="button" @click="isGroup = false">Direct</button>
          <button class="modeBtn" :class="{ active: isGroup }" type="button" @click="isGroup = true">Group</button>
        </div>

        <div v-if="isGroup" class="field">
          <div class="label">Title</div>
          <input v-model="title" class="input" placeholder="Group title" />
        </div>

        <div class="field">
          <div class="label">{{ isGroup ? 'Participants' : 'Username or Email' }}</div>
          <input
            v-if="!isGroup"
            v-model="participantsText"
            class="input"
            placeholder="username or email"
          />
          <textarea
            v-else
            v-model="participantsText"
            class="input inputArea"
            rows="4"
            placeholder="user1, user2, user3"
          />
          <div class="hint">
            {{ isGroup ? 'Separate participants with commas, spaces, or new lines.' : 'Enter exactly one username or email.' }}
          </div>
        </div>

        <div v-if="createError" class="error modalError">{{ createError }}</div>

        <div class="modalActions">
          <button class="btn ghost" @click="closeCreate">Cancel</button>
          <button class="btn primary" :disabled="creating || !canCreate" @click="createChat">
            {{ creating ? 'Creating...' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page {
  max-width: 860px;
  margin: 20px auto;
  padding: 0 12px;
}

.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
}

.h {
  font-size: 18px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  opacity: .7;
}

.actions {
  display: flex;
  gap: 10px;
}

.error {
  margin-top: 12px;
  padding: 10px 12px;
  border: 1px solid #5a1f1f;
  border-radius: 12px;
  color: #ffb3b3;
}

.list {
  margin-top: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chatItem {
  width: 100%;
  text-align: left;
  border: 1px solid #2a2a2a;
  border-radius: 14px;
  padding: 12px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.chatItem:hover {
  background: rgba(255, 255, 255, 0.03);
}

.row1 {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: baseline;
}

.name {
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.metaRight {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.time {
  font-size: 12px;
  opacity: .7;
  white-space: nowrap;
}

.badge {
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(120, 100, 255, 0.25);
  border: 1px solid rgba(120, 100, 255, 0.4);
}

.row2 {
  margin-top: 6px;
}

.preview {
  font-size: 13px;
  opacity: .8;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.muted {
  opacity: .7;
  padding: 10px 4px;
}

/* modal */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
}

.modal {
  width: 100%;
  max-width: 560px;
  border: 1px solid #2a2a2a;
  border-radius: 22px;
  padding: 22px;
  background: linear-gradient(180deg, #141519 0%, #101115 100%);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.modalHead {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.modalTitle {
  font-size: 30px;
  font-weight: 700;
}

.modalSub {
  font-size: 13px;
  opacity: .68;
  margin-top: 4px;
}

.modeSwitch {
  display: inline-flex;
  width: fit-content;
  padding: 4px;
  border: 1px solid #2a2a2a;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.02);
}

.modeBtn {
  border: 0;
  background: transparent;
  color: inherit;
  border-radius: 999px;
  padding: 10px 16px;
  cursor: pointer;
  opacity: .72;
}

.modeBtn.active {
  background: rgba(120, 100, 255, 0.18);
  opacity: 1;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.label {
  font-size: 12px;
  opacity: .75;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.input {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #2a2a2a;
  border-radius: 16px;
  padding: 14px 16px;
  background: transparent;
  color: inherit;
  resize: none;
  min-width: 0;
}

.inputArea {
  min-height: 120px;
}

.hint {
  font-size: 12px;
  opacity: .65;
}

.modalError {
  margin: -4px 0 0;
}

.modalActions {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  align-items: center;
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

.btn.ghost {
  background: rgba(255, 255, 255, 0.02);
}

.btn:disabled {
  opacity: .5;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .modal {
    padding: 18px;
    border-radius: 18px;
  }

  .modalHead {
    flex-direction: column;
    align-items: flex-start;
  }

  .modeSwitch {
    width: 100%;
  }

  .modeBtn {
    flex: 1;
    text-align: center;
  }

  .modalActions {
    flex-direction: column-reverse;
    align-items: stretch;
  }
}
</style>
