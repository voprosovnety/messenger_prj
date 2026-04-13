<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const router = useRouter()
const chats = ref([])

const error = ref('')
const creating = ref(false)
const isGroup = ref(false)
const title = ref('')
const participantsText = ref('')

async function loadChats() {
  error.value = ''
  try {
    const data = await api.listChats()
    chats.value = data.items || []
  } catch (e) {
    error.value = e.message || 'failed to load chats'
  }
}

onMounted(loadChats)

async function logout() {
  await api.logout()
  router.push('/login')
}

function parseParticipants(text) {
  return text
    .split(/[\s,]+/g)          // пробелы/запятые/переводы строк
    .map(s => s.trim())
    .filter(Boolean)
}

async function createChat() {
  error.value = ''
  creating.value = true
  try {
    const participants = parseParticipants(participantsText.value)

    if (!isGroup.value) {
      if (participants.length !== 1) {
        throw new Error('For DM provide exactly 1 username/email')
      }
    } else {
      if (!title.value.trim()) throw new Error('Title is required for group chat')
      if (participants.length < 1) throw new Error('Group chat requires at least 1 participant')
    }

    const chat = await api.createChat({
      isGroup: isGroup.value,
      title: title.value.trim(),
      participants,
    })

    // reset form
    title.value = ''
    participantsText.value = ''
    isGroup.value = false

    await loadChats()
    router.push(`/chats/${chat.id}`)
  } catch (e) {
    error.value = e.message || 'create chat failed'
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div style="max-width: 720px; margin: 20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2>Chats</h2>
      <button @click="logout">Logout</button>
    </div>

    <div style="border:1px solid #ddd; padding:12px; border-radius:8px; margin-bottom:16px;">
      <h3 style="margin:0 0 10px 0;">New chat</h3>

      <div v-if="error" style="color:red; margin-bottom:10px;">{{ error }}</div>

      <label style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
        <input type="checkbox" v-model="isGroup" />
        Group chat
      </label>

      <div v-if="isGroup" style="margin-bottom:10px;">
        <input v-model="title" placeholder="group title" style="width:100%; padding:8px;" />
      </div>

      <textarea v-model="participantsText"
        :placeholder="isGroup ? 'participants (usernames/emails), separated by comma or space' : 'username or email'"
        rows="3" style="width:100%; padding:8px; margin-bottom:10px;" />

      <button @click="createChat" :disabled="creating" style="padding:8px 12px;">
        {{ creating ? 'Creating...' : 'Create' }}
      </button>
    </div>

    <ul>
      <li v-for="c in chats" :key="c.id" style="margin: 10px 0;">
        <a href="#" @click.prevent="router.push(`/chats/${c.id}`)">
          {{ c.is_group ? 'Group' : 'DM' }} — {{ c.display_name || c.title || c.id }}
        </a>
      </li>
    </ul>
  </div>
</template>