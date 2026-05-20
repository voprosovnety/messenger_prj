<template>
  <div class="global-search-panel">
    <div class="global-search-bar">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input
        ref="inputEl"
        v-model="query"
        class="global-search-input"
        placeholder="Search all messages…"
        @input="onInput"
        @keydown.escape="$emit('close')"
      />
      <button class="btn-icon" style="padding:4px" title="Close" @click="$emit('close')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <div class="global-search-results">
      <div v-if="query.trim().length < 2 && !loading" class="msg-search-status">
        Type at least 2 characters
      </div>
      <div v-else-if="loading" class="msg-search-status">Searching…</div>
      <template v-else>
        <div v-if="!results.length" class="msg-search-status">
          No messages found for "{{ query.trim() }}"
        </div>
        <button
          v-for="r in results"
          :key="r.id"
          class="global-search-item"
          @click="$emit('select', { chatId: r.chat_id, messageId: r.id })"
        >
          <UserAvatar
            :username="r.chat_display_name || '?'"
            :avatarUrl="r.chat_avatar_url || null"
            size="md"
          />
          <div class="global-search-item-info">
            <div class="global-search-item-top">
              <span class="global-search-item-chat">{{ r.chat_display_name }}</span>
              <span class="global-search-item-time">{{ formatTimeShort(r.created_at) }}</span>
            </div>
            <div class="global-search-item-bottom">
              <span class="global-search-item-text">
                <span v-if="r.sender" class="global-search-item-sender">{{ r.sender }}: </span><span v-html="highlightText(r.content || '', query.trim())"></span>
              </span>
            </div>
          </div>
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { api } from '../api'
import UserAvatar from './UserAvatar.vue'

function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function highlightText(text, query) {
  if (!query || !text) return escapeHtml(text || '')
  const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return escapeHtml(text).replace(
    new RegExp(escaped, 'gi'),
    m => `<mark>${m}</mark>`
  )
}

defineEmits(['close', 'select'])

const query = ref('')
const results = ref([])
const loading = ref(false)
const inputEl = ref(null)
let debounceTimer = null

function onInput() {
  clearTimeout(debounceTimer)
  const q = query.value.trim()
  if (q.length < 2) {
    results.value = []
    loading.value = false
    return
  }
  loading.value = true
  debounceTimer = setTimeout(() => doSearch(q), 300)
}

async function doSearch(q) {
  try {
    const data = await api.searchAllMessages(q)
    if (query.value.trim() !== q) return
    results.value = data.items || []
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
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

onMounted(() => {
  nextTick(() => inputEl.value?.focus())
})

onBeforeUnmount(() => {
  clearTimeout(debounceTimer)
})
</script>
