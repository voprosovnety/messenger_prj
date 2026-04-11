<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
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

async function load() {
  const data = await api.listMessages(chatId)
  messages.value = data.items || []

  // delivered/read: на старте считаем, что доставили/прочитали последнее
  const last = messages.value[messages.value.length - 1]
  if (last) {
    await api.markDelivered(chatId, last.id)
  }
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

    if (payload.type === 'message.created') {
      messages.value.push(payload.data)
      await api.markDelivered(chatId, payload.data.id)
      await markReadIfPossible()
    }

    if (payload.type === 'message.edited') {
      const i = messages.value.findIndex(m => m.id === payload.data.id)
      if (i !== -1) {
        messages.value[i].content = payload.data.content
        messages.value[i].edited_at = payload.data.edited_at
      }
    }

    if (payload.type === 'message.deleted') {
      const i = messages.value.findIndex(m => m.id === payload.data.id)
      if (i !== -1) {
        messages.value[i].deleted_at = payload.data.deleted_at
      }
    }

    if (payload.type === 'chat.delivered') {
      if (payload.data?.user && payload.data.user !== myId()) {
        peerDeliveredId.value = payload.data.last_delivered_message_id || null
      }
    }

    if (payload.type === 'chat.read') {
      if (payload.data?.user && payload.data.user !== myId()) {
        peerReadId.value = payload.data.last_read_message_id || null
      }
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

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
}

function myId() {
  return me.value?.username || me.value?.user || ''
}

function isMine(m) {
  return m.sender === myId()
}

function idLE(a, b) {
  if (!a || !b) return false
  return String(a) <= String(b)
}

</script>

<template>
  <div style="max-width: 720px; margin: 20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2>Chat {{ chatId }}</h2>
      <button @click="router.push('/')">Back to chats</button>
    </div>

    <div style="border:1px solid #ddd; padding:12px; min-height:240px;">
      <div v-for="m in messages" :key="m.id" style="margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; gap:12px;">
          <div><b>{{ m.sender }}</b> — {{ m.created_at }}</div>

          <div v-if="isMine(m) && !m.deleted_at" style="opacity:.7; white-space:nowrap;">
            <span v-if="peerReadId && idLE(m.id, peerReadId)">✓✓ read</span>
            <span v-else-if="peerDeliveredId && idLE(m.id, peerDeliveredId)">✓ delivered</span>
          </div>
        </div>
        <div v-if="m.deleted_at" style="opacity:.6;"><i>deleted</i></div>
        <div v-else>{{ m.content }}</div>
        <div v-if="m.edited_at" style="opacity:.6;"><i>edited</i></div>
      </div>
    </div>

    <div style="display:flex; gap:8px; margin-top:12px;">
      <textarea v-model="input" rows="2" style="flex:1; padding:8px; resize:vertical;" placeholder="message..."
        @keydown="onKeydown" />
      <button @click="send">Send</button>
    </div>
  </div>
</template>