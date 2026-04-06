<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api'

const route = useRoute()
const chatId = route.params.chatId

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
    await api.markRead(chatId, last.id)
  }
}

async function connectSse() {
  const { topic } = await api.getMercureCookie(chatId)

  const url = `/.well-known/mercure?topic=${encodeURIComponent(topic)}`
  es = new EventSource(url, { withCredentials: true })

  es.onmessage = (evt) => {
    const payload = JSON.parse(evt.data)

    if (payload.type === 'message.created') {
      messages.value.push(payload.data)
      // optional: авто delivered/read тут
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

    if (payload.type === 'chat.read' || payload.type === 'chat.delivered') {
      // пока просто логируем, позже покажем в UI
      console.log(payload)
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
  await load()
  await connectSse()
})

onBeforeUnmount(() => {
  if (es) es.close()
})
</script>

<template>
  <div style="max-width: 720px; margin: 20px auto;">
    <h2>Chat {{ chatId }}</h2>

    <div style="border:1px solid #ddd; padding:12px; min-height:240px;">
      <div v-for="m in messages" :key="m.id" style="margin-bottom:10px;">
        <div><b>{{ m.sender }}</b> — {{ m.created_at }}</div>
        <div v-if="m.deleted_at" style="opacity:.6;"><i>deleted</i></div>
        <div v-else>{{ m.content }}</div>
        <div v-if="m.edited_at" style="opacity:.6;"><i>edited</i></div>
      </div>
    </div>

    <div style="display:flex; gap:8px; margin-top:12px;">
      <input v-model="input" style="flex:1; padding:8px;" placeholder="message..." />
      <button @click="send">Send</button>
    </div>

    <p style="margin-top:12px; opacity:.7;">
      SSE subscription via JWT header is not possible with native EventSource.
      Next step: implement Mercure subscribe via cookie.
    </p>
  </div>
</template>