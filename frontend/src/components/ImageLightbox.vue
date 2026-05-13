<template>
  <Teleport to="body">
    <div
      class="lightbox-overlay"
      @click.self="onOverlayClick"
      @wheel.prevent="onWheel"
      @mousemove="onMouseMove"
      @mouseup="onMouseUp"
      @mouseleave="onMouseUp"
    >
      <button class="lightbox-close" @click="$emit('close')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>

      <button v-if="images.length > 1" class="lightbox-arrow left" @click="prev">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      </button>

      <div class="lightbox-img-wrap">
        <img
          :src="images[index]"
          class="lightbox-img"
          :style="{
            transform: `translate(${panX}px, ${panY}px) scale(${zoom})`,
            cursor: dragging ? 'grabbing' : zoom > 1 ? 'grab' : 'default',
            transition: dragging ? 'none' : 'transform 0.15s ease',
          }"
          draggable="false"
          @click.stop
          @mousedown.prevent="onMouseDown"
        />
      </div>

      <button v-if="images.length > 1" class="lightbox-arrow right" @click="next">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </button>

      <div v-if="canApply && index !== currentImageIndex" class="lightbox-actions">
        <button class="lightbox-delete-btn" title="Delete from history" @click.stop="$emit('delete', index)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </button>
        <button class="lightbox-apply-btn" @click.stop="$emit('apply', index)">Use as current photo</button>
      </div>

      <div class="lightbox-zoom-controls">
        <button class="lightbox-zoom-btn" :disabled="zoom <= MIN_ZOOM" @click="zoomOut">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <span class="lightbox-zoom-label">{{ Math.round(zoom * 100) }}%</span>
        <button class="lightbox-zoom-btn" :disabled="zoom >= MAX_ZOOM" @click="zoomIn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <div v-if="images.length > 1" class="lightbox-counter">{{ index + 1 }} / {{ images.length }}</div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  images: { type: Array, required: true },
  index: { type: Number, required: true },
  canApply: { type: Boolean, default: false },
  currentImageIndex: { type: Number, default: -1 },
})
const emit = defineEmits(['close', 'navigate', 'apply', 'delete'])

const MIN_ZOOM = 0.5
const MAX_ZOOM = 4
const STEP = 0.25

const zoom   = ref(1)
const panX   = ref(0)
const panY   = ref(0)
const dragging = ref(false)
let dragStart  = { x: 0, y: 0 }
let panStart   = { x: 0, y: 0 }
let didDrag    = false

function resetView() { zoom.value = 1; panX.value = 0; panY.value = 0 }

watch(() => props.index, resetView)

function zoomIn()  { zoom.value = Math.min(MAX_ZOOM, +(zoom.value + STEP).toFixed(2)) }
function zoomOut() {
  zoom.value = Math.max(MIN_ZOOM, +(zoom.value - STEP).toFixed(2))
  if (zoom.value <= 1) { panX.value = 0; panY.value = 0 }
}

function onWheel(e) { e.deltaY < 0 ? zoomIn() : zoomOut() }

function onMouseDown(e) {
  if (zoom.value <= 1) return
  dragging.value = true
  didDrag = false
  dragStart = { x: e.clientX, y: e.clientY }
  panStart  = { x: panX.value, y: panY.value }
}

function onMouseMove(e) {
  if (!dragging.value) return
  const dx = e.clientX - dragStart.x
  const dy = e.clientY - dragStart.y
  if (Math.abs(dx) > 2 || Math.abs(dy) > 2) didDrag = true
  panX.value = panStart.x + dx
  panY.value = panStart.y + dy
}

function onMouseUp() { dragging.value = false }

function onOverlayClick() {
  if (didDrag) { didDrag = false; return }
  if (zoom.value !== 1) { resetView(); return }
  emit('close')
}

function prev() { emit('navigate', (props.index - 1 + props.images.length) % props.images.length) }
function next() { emit('navigate', (props.index + 1) % props.images.length) }

function onKey(e) {
  if (e.key === 'Escape')                emit('close')
  if (e.key === 'ArrowLeft')             prev()
  if (e.key === 'ArrowRight')            next()
  if (e.key === '+' || e.key === '=')    zoomIn()
  if (e.key === '-')                     zoomOut()
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>
