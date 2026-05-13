<template>
  <Teleport to="body">
    <div class="lightbox-overlay" @click.self="$emit('close')" @keydown.esc="$emit('close')">
      <button class="lightbox-close" @click="$emit('close')">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>

      <button v-if="images.length > 1" class="lightbox-arrow left" @click="prev">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      </button>

      <div class="lightbox-img-wrap">
        <img :src="images[index]" class="lightbox-img" @click.stop />
      </div>

      <button v-if="images.length > 1" class="lightbox-arrow right" @click="next">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </button>

      <div v-if="images.length > 1" class="lightbox-counter">{{ index + 1 }} / {{ images.length }}</div>
    </div>
  </Teleport>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  images: { type: Array, required: true },
  index: { type: Number, required: true },
})
const emit = defineEmits(['close', 'navigate'])

function prev() { emit('navigate', (props.index - 1 + props.images.length) % props.images.length) }
function next() { emit('navigate', (props.index + 1) % props.images.length) }

function onKey(e) {
  if (e.key === 'Escape') emit('close')
  if (e.key === 'ArrowLeft') prev()
  if (e.key === 'ArrowRight') next()
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>
