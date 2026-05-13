<template>
  <Teleport to="body">
    <div class="lightbox-overlay" @click.self="onOverlayClick" @wheel.prevent="onWheel">
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
          :style="{ transform: `scale(${zoom})`, cursor: zoom > 1 ? 'grab' : 'default' }"
          @click.stop
        />
      </div>

      <button v-if="images.length > 1" class="lightbox-arrow right" @click="next">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </button>

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
})
const emit = defineEmits(['close', 'navigate'])

const MIN_ZOOM = 0.5
const MAX_ZOOM = 4
const STEP = 0.25

const zoom = ref(1)

watch(() => props.index, () => { zoom.value = 1 })

function zoomIn()  { zoom.value = Math.min(MAX_ZOOM, +(zoom.value + STEP).toFixed(2)) }
function zoomOut() { zoom.value = Math.max(MIN_ZOOM, +(zoom.value - STEP).toFixed(2)) }

function onWheel(e) { e.deltaY < 0 ? zoomIn() : zoomOut() }

function onOverlayClick() {
  if (zoom.value !== 1) { zoom.value = 1; return }
  emit('close')
}

function prev() { emit('navigate', (props.index - 1 + props.images.length) % props.images.length) }
function next() { emit('navigate', (props.index + 1) % props.images.length) }

function onKey(e) {
  if (e.key === 'Escape') emit('close')
  if (e.key === 'ArrowLeft')  prev()
  if (e.key === 'ArrowRight') next()
  if (e.key === '+' || e.key === '=') zoomIn()
  if (e.key === '-') zoomOut()
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>
