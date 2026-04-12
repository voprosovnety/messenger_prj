<script setup>
import { onMounted, onBeforeUnmount, ref, nextTick, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'

const route = useRoute()
const router = useRouter()
const chatId = route.params.chatId

const me = ref(null)

const peerDeliveredId = ref(null)
const peerReadId = ref(null)

const messages = ref([])
const input = ref('')
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

function idLE(a, b) {
  if (!a || !b) return false
  return String(a) <= String(b)
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
  const data = await api.listMessages(chatId)
  messages.value = data.items || []

  // delivered на старте: доставили последнее, если есть
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
  const { topic } = await api.getMercureCookie(chatId)
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
        peerDeliveredId.value = payload.data.last_delivered_message_id || null
      }
      return
    }

    if (payload.type === 'chat.read') {
      if (payload.data?.user && payload.data.user !== myId()) {
        peerReadId.value = payload.data.last_read_message_id || null
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
        <div class="h">Chat</div>
        <div class="sub">{{ chatId }}</div>
      </div>
      <button class="btn" @click="router.push('/')">Back</button>
    </header>

    <main class="chat">
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
              <div v-else class="text">{{ m.content }}</div>

              <div class="flags">
                <span v-if="m.edited_at && !m.deleted_at" class="edited">edited</span>

                <span v-if="isMine(m) && !m.deleted_at" class="status">
                  <span v-if="peerReadId && idLE(m.id, peerReadId)">✓✓ read</span>
                  <span v-else-if="peerDeliveredId && idLE(m.id, peerDeliveredId)">✓ delivered</span>
                </span>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div class="composer">
        <textarea v-model="input" class="input" rows="2" placeholder="message..." @keydown="onKeydown" />
        <button class="btn primary" @click="send">Send</button>
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
  word-break: break-all;
}

.chat {
  margin-top: 12px;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  min-height: 0;
  /* важно для внутреннего скролла */
  flex: 1;
  overflow: hidden;
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
</style>