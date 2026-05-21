<template>
  <Teleport to="body">
    <div class="mg-backdrop" @click.self="$emit('close')"></div>
    <div class="mg-panel" role="dialog" aria-label="Media Gallery">
      <div class="mg-header">
        <span class="mg-title">Media</span>
        <button class="btn-icon" title="Close" @click="$emit('close')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <div class="mg-tabs">
        <button
          class="mg-tab"
          :class="{ 'mg-tab--active': activeTab === 'images' }"
          @click="activeTab = 'images'"
        >
          Images{{ imageItems.length ? ` (${imageItems.length})` : '' }}
        </button>
        <button
          class="mg-tab"
          :class="{ 'mg-tab--active': activeTab === 'audio' }"
          @click="activeTab = 'audio'"
        >
          Audio{{ audioItems.length ? ` (${audioItems.length})` : '' }}
        </button>
        <button
          class="mg-tab"
          :class="{ 'mg-tab--active': activeTab === 'files' }"
          @click="activeTab = 'files'"
        >
          Files{{ fileItems.length ? ` (${fileItems.length})` : '' }}
        </button>
      </div>

      <div class="mg-body">
        <!-- Loading / error states -->
        <div v-if="loading" class="mg-empty">Loading…</div>
        <div v-else-if="loadError" class="mg-empty">{{ loadError }}</div>

        <!-- Images tab -->
        <template v-else-if="activeTab === 'images'">
          <div v-if="!imageItems.length" class="mg-empty">No images shared yet</div>
          <div v-else class="mg-image-grid">
            <button
              v-for="(item, idx) in imageItems"
              :key="item.url + idx"
              class="mg-image-thumb"
              :title="item.name || 'Image'"
              @click="openLightbox(idx)"
            >
              <img
                v-if="item.type === 'image'"
                :src="item.url"
                :alt="item.name || 'Image'"
                class="mg-thumb-img"
                loading="lazy"
              />
              <video
                v-else
                :src="item.url"
                class="mg-thumb-img mg-thumb-video"
                preload="metadata"
              />
              <div class="mg-thumb-overlay">
                <svg v-if="item.type === 'video'" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                  <polygon points="5 3 19 12 5 21 5 3"/>
                </svg>
              </div>
            </button>
          </div>
        </template>

        <!-- Audio tab -->
        <template v-else-if="activeTab === 'audio'">
          <div v-if="!audioItems.length" class="mg-empty">No voice messages yet</div>
          <div v-else class="mg-audio-list">
            <div
              v-for="(item, idx) in audioItems"
              :key="item.url + idx"
              class="mg-audio-item"
            >
              <div class="mg-audio-meta">
                <span class="mg-audio-sender">{{ item.sender }}</span>
                <span class="mg-audio-time">{{ formatTimeShort(item.created_at) }}</span>
              </div>
              <AudioPlayer :src="item.url" />
            </div>
          </div>
        </template>

        <!-- Files tab -->
        <template v-else>
          <div v-if="!fileItems.length" class="mg-empty">No files shared yet</div>
          <div v-else class="mg-file-list">
            <a
              v-for="(item, idx) in fileItems"
              :key="item.url + idx"
              class="mg-file-item"
              :href="item.url"
              :download="item.name || true"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span class="mg-file-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                  <polyline points="14 2 14 8 20 8"/>
                </svg>
              </span>
              <div class="mg-file-info">
                <span class="mg-file-name">{{ item.name || 'File' }}</span>
                <span class="mg-file-meta">{{ item.sender }} · {{ formatTimeShort(item.created_at) }}</span>
              </div>
              <span class="mg-file-dl">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="7 10 12 15 17 10"/>
                  <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
              </span>
            </a>
          </div>
        </template>
      </div>
    </div>

    <!-- Lightbox for gallery images -->
    <ImageLightbox
      v-if="lightboxOpen"
      :images="galleryImageUrls"
      :index="lightboxIdx"
      @close="lightboxOpen = false"
      @navigate="lightboxIdx = $event"
    />
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AudioPlayer from './AudioPlayer.vue'
import ImageLightbox from './ImageLightbox.vue'
import { api } from '../api.js'

const props = defineProps({
  chatId: { type: String, required: true },
})
defineEmits(['close'])

const activeTab = ref('images')
const lightboxOpen = ref(false)
const lightboxIdx = ref(0)

const items = ref([])
const loading = ref(true)
const loadError = ref('')

onMounted(async () => {
  try {
    const data = await api.getChatMedia(props.chatId)
    items.value = data.items || []
  } catch (err) {
    loadError.value = err.message || 'Failed to load media'
  } finally {
    loading.value = false
  }
})

const imageItems = computed(() => items.value.filter(i => i.type === 'image' || i.type === 'video'))
const audioItems = computed(() => items.value.filter(i => i.type === 'audio'))
const fileItems = computed(() => items.value.filter(i => i.type !== 'image' && i.type !== 'video' && i.type !== 'audio'))

const galleryImageUrls = computed(() => imageItems.value.map(i => i.url))

function openLightbox(idx) {
  lightboxIdx.value = idx
  lightboxOpen.value = true
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
</script>

<style scoped>
/* Panel slide-in from right */
.mg-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 90;
  animation: mg-overlay-in 200ms ease forwards;
}

@keyframes mg-overlay-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}

.mg-panel {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 340px;
  background: var(--surface);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 91;
  box-shadow: var(--shadow);
  animation: mg-slide-in 220ms cubic-bezier(0.4, 0, 0.2, 1) forwards;
  overflow: hidden;
}

@keyframes mg-slide-in {
  from { transform: translateX(100%); opacity: 0.6; }
  to   { transform: translateX(0);    opacity: 1; }
}

/* Header */
.mg-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.mg-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
}

/* Tab switcher */
.mg-tabs {
  display: flex;
  padding: 8px 12px;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}

.mg-tab {
  flex: 1;
  padding: 6px 8px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 500;
  color: var(--text-2);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background var(--transition), color var(--transition);
  text-align: center;
  min-height: 44px;
}

.mg-tab:hover {
  background: var(--surface-2);
  color: var(--text);
}
@media (hover: none) {
  .mg-tab:hover { background: transparent; color: var(--text-2); }
}

.mg-tab--active {
  background: var(--accent-dim);
  color: var(--accent);
  font-weight: 600;
}

/* Scrollable content */
.mg-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Empty state */
.mg-empty {
  padding: 40px 20px;
  text-align: center;
  font-size: 13px;
  color: var(--text-3);
}

/* Image grid */
.mg-image-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 2px;
  padding: 8px;
}

.mg-image-thumb {
  position: relative;
  aspect-ratio: 1;
  background: var(--surface-2);
  border: none;
  cursor: pointer;
  border-radius: var(--radius-sm);
  overflow: hidden;
  transition: opacity var(--transition), transform var(--transition);
  min-height: 44px;
  min-width: 44px;
  padding: 0;
}

.mg-image-thumb:hover {
  opacity: 0.85;
  transform: scale(1.03);
}
@media (hover: none) {
  .mg-image-thumb:hover { opacity: 1; transform: none; }
}

.mg-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  border-radius: var(--radius-sm);
}

.mg-thumb-video {
  pointer-events: none;
}

.mg-thumb-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  text-shadow: 0 1px 4px rgba(0, 0, 0, 0.8);
  pointer-events: none;
}

/* Audio list */
.mg-audio-list {
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 4px 0;
}

.mg-audio-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
}

.mg-audio-item:last-child {
  border-bottom: none;
}

.mg-audio-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.mg-audio-sender {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mg-audio-time {
  font-size: 11px;
  color: var(--text-3);
  flex-shrink: 0;
}

/* File list */
.mg-file-list {
  display: flex;
  flex-direction: column;
  padding: 4px 0;
}

.mg-file-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  text-decoration: none;
  color: var(--text);
  transition: background var(--transition);
  min-height: 44px;
}

.mg-file-item:last-child {
  border-bottom: none;
}

.mg-file-item:hover {
  background: var(--surface-2);
  text-decoration: none;
}
@media (hover: none) {
  .mg-file-item:hover { background: transparent; }
  .mg-file-item:hover .mg-file-dl { color: var(--text-3); }
}

.mg-file-icon {
  color: var(--accent);
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.mg-file-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.mg-file-name {
  font-size: 13px;
  font-weight: 500;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mg-file-meta {
  font-size: 11px;
  color: var(--text-3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mg-file-dl {
  color: var(--text-3);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  transition: color var(--transition);
}

.mg-file-item:hover .mg-file-dl {
  color: var(--accent);
}

/* Mobile: full width */
@media (max-width: 640px) {
  .mg-panel {
    width: 100%;
    border-left: none;
  }

  .mg-image-grid {
    grid-template-columns: repeat(3, 1fr);
    padding: 4px;
  }
}
</style>
