<script setup>
import { onMounted, ref, computed } from 'vue'
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

onMounted(loadChats)

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
  const content = lm.content || ''
  if (c.is_group) {
    const who = lm.sender_username || 'someone'
    return `${who}: ${content}`
  }
  return content
}

function openChat(c) {
  router.push(`/chats/${c.id}`)
}

function openCreate() {
  error.value = ''
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
  creating.value = false
  isGroup.value = false
  title.value = ''
  participantsText.value = ''
}

const canCreate = computed(() => {
  const participants = parseParticipants(participantsText.value)
  if (!isGroup.value) return participants.length === 1
  return title.value.trim().length > 0 && participants.length >= 1
})

async function createChat() {
  error.value = ''
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
    error.value = e.message || 'create chat failed'
  } finally {
    creating.value = false
  }
}
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
              <span class="time">{{ formatTime(c.last_message?.created_at) }}</span>
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
          <div class="modalTitle">New chat</div>
          <button class="btn" @click="closeCreate">Close</button>
        </div>

        <label class="check">
          <input type="checkbox" v-model="isGroup" />
          <span>Group chat</span>
        </label>

        <div v-if="isGroup" class="field">
          <div class="label">Title</div>
          <input v-model="title" class="input" placeholder="Group title" />
        </div>

        <div class="field">
          <div class="label">
            Participants (username/email)
          </div>
          <textarea v-model="participantsText" class="input" rows="3"
            :placeholder="isGroup ? 'user1 user2 user3' : 'username or email'" />
        </div>

        <div class="modalActions">
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
  padding: 18px;
}

.modal {
  width: 100%;
  max-width: 520px;
  border: 1px solid #2a2a2a;
  border-radius: 14px;
  padding: 12px;
  background: #0f0f0f;
}

.modalHead {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.modalTitle {
  font-size: 16px;
  font-weight: 700;
}

.check {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  opacity: .9;
}

.field {
  margin-bottom: 12px;
}

.label {
  font-size: 12px;
  opacity: .75;
  margin-bottom: 6px;
}

.input {
  width: 100%;
  border: 1px solid #2a2a2a;
  border-radius: 12px;
  padding: 10px 12px;
  background: transparent;
  color: inherit;
  resize: vertical;
}

.modalActions {
  display: flex;
  justify-content: flex-end;
  margin-top: 6px;
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

.btn:disabled {
  opacity: .5;
  cursor: not-allowed;
}
</style>