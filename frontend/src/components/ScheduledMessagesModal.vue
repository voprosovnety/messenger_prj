<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal scheduled-modal" role="dialog" aria-modal="true" aria-label="Scheduled messages">
      <div class="modal-header">
        <span class="modal-title">Scheduled messages</span>
        <button class="btn-icon" aria-label="Close" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body scheduled-body">
        <div v-if="deleteError" class="auth-error" style="margin:0 0 8px">{{ deleteError }}</div>
        <div v-if="!items.length" class="scheduled-empty">
          No scheduled messages.
        </div>
        <ul v-else class="scheduled-list">
          <li v-for="item in items" :key="item.id" class="scheduled-item">
            <template v-if="editingId === item.id">
              <textarea
                v-model="editContent"
                class="input scheduled-edit-content"
                rows="2"
                placeholder="Message…"
              />
              <input
                v-model="editWhen"
                type="datetime-local"
                class="input scheduled-edit-when"
              />
              <div v-if="editError" class="auth-error" style="margin:0">{{ editError }}</div>
              <div class="scheduled-item-actions">
                <button class="btn btn-ghost" :disabled="saving" @click="cancelEdit">Cancel</button>
                <button class="btn btn-primary" :disabled="saving" @click="saveEdit(item)">
                  {{ saving ? 'Saving…' : 'Save' }}
                </button>
              </div>
            </template>
            <template v-else>
              <div class="scheduled-item-content">
                <span v-if="item.content">{{ item.content }}</span>
                <span v-else class="muted">(no text)</span>
              </div>
              <div class="scheduled-item-meta">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ formatWhen(item.scheduled_at) }}
              </div>
              <div class="scheduled-item-actions">
                <button class="btn btn-ghost" @click="startEdit(item)">Edit</button>
                <template v-if="confirmDeleteId === item.id">
                  <button class="btn btn-danger" :disabled="busyId === item.id" @click="remove(item)">Confirm</button>
                  <button class="btn btn-ghost" @click="confirmDeleteId = null">Cancel</button>
                </template>
                <button v-else class="btn btn-ghost danger" :disabled="busyId === item.id" @click="confirmDeleteId = item.id">Delete</button>
              </div>
            </template>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { api } from '../api'

const props = defineProps({
  items: { type: Array, required: true },
})
const emit = defineEmits(['close', 'updated', 'deleted'])

const editingId = ref(null)
const editContent = ref('')
const editWhen = ref('')
const editError = ref('')
const saving = ref(false)
const busyId = ref(null)
const confirmDeleteId = ref(null)
const deleteError = ref('')

function toLocalInput(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function fromLocalInput(local) {
  if (!local) return null
  const d = new Date(local)
  if (isNaN(d)) return null
  return d.toISOString()
}

function formatWhen(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const now = new Date()
  const sameDay = d.toDateString() === now.toDateString()
  const t = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
  if (sameDay) return `Today, ${t}`
  return d.toLocaleDateString('en', { month: 'short', day: 'numeric', year: 'numeric' }) + `, ${t}`
}

function startEdit(item) {
  editingId.value = item.id
  editContent.value = item.content || ''
  editWhen.value = toLocalInput(item.scheduled_at)
  editError.value = ''
}

function cancelEdit() {
  editingId.value = null
  editContent.value = ''
  editWhen.value = ''
  editError.value = ''
}

async function saveEdit(item) {
  editError.value = ''
  const text = editContent.value.trim()
  const whenIso = fromLocalInput(editWhen.value)
  if (!text && !(item.attachments && item.attachments.length)) {
    editError.value = 'Message text is required'
    return
  }
  if (!whenIso) {
    editError.value = 'Pick a date and time'
    return
  }
  saving.value = true
  try {
    const updated = await api.updateScheduledMessage(item.id, {
      content: text,
      scheduledAt: whenIso,
    })
    emit('updated', updated)
    cancelEdit()
  } catch (e) {
    editError.value = e.message
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  confirmDeleteId.value = null
  deleteError.value = ''
  busyId.value = item.id
  try {
    await api.deleteScheduledMessage(item.id)
    emit('deleted', item.id)
  } catch (e) {
    deleteError.value = e.message || 'Failed to delete scheduled message'
  } finally {
    busyId.value = null
  }
}
</script>
