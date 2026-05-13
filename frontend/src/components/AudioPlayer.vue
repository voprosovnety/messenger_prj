<template>
  <div class="ap">
    <audio
      ref="audioEl"
      :src="src"
      preload="metadata"
      @timeupdate="onTimeUpdate"
      @loadedmetadata="onMeta"
      @durationchange="onDurationChange"
      @ended="onEnded"
    />
    <button class="ap-play" @click="toggle" :title="playing ? 'Pause' : 'Play'">
      <svg v-if="!playing" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
    </button>

    <div class="ap-track">
      <input
        type="range"
        class="ap-seek"
        :value="current"
        :max="duration || 0"
        step="0.05"
        @mousedown="seeking = true"
        @touchstart="seeking = true"
        @change="seek"
        @input="seekPreview"
      />
      <div class="ap-fill" :style="{ width: duration ? (current / duration * 100) + '%' : '0%' }" />
    </div>

    <span class="ap-time">{{ fmt(current) }}<span style="color:var(--text-3)">/{{ fmt(duration) }}</span></span>

    <div class="ap-vol" title="Volume">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
        <path v-if="vol > 0" d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
        <line v-if="vol === 0" x1="23" y1="9" x2="17" y2="15"/>
        <line v-if="vol === 0" x1="17" y1="9" x2="23" y2="15"/>
      </svg>
      <input type="range" class="ap-volume" v-model="vol" min="0" max="1" step="0.05" @input="applyVol" />
    </div>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'

const props = defineProps({ src: { type: String, required: true } })

const audioEl = ref(null)
const playing = ref(false)
const current = ref(0)
const duration = ref(0)
const vol = ref(1)
let seeking = false

function toggle() {
  const a = audioEl.value
  if (!a) return
  if (a.paused) { a.play(); playing.value = true }
  else { a.pause(); playing.value = false }
}

function onMeta() {
  const a = audioEl.value
  if (!a) return
  a.volume = vol.value
  if (!isFinite(a.duration)) {
    // MediaRecorder blobs lack duration metadata — seek to end to force browser to compute it
    a.currentTime = 1e10
  } else {
    duration.value = a.duration
  }
}

function onDurationChange() {
  const a = audioEl.value
  if (!a || !isFinite(a.duration)) return
  duration.value = a.duration
  a.currentTime = 0
}

function onEnded() {
  playing.value = false
}

function onTimeUpdate() {
  if (!seeking) current.value = audioEl.value?.currentTime ?? 0
}

function seekPreview(e) {
  current.value = Number(e.target.value)
}

function seek(e) {
  seeking = false
  const t = Number(e.target.value)
  current.value = t
  if (audioEl.value) audioEl.value.currentTime = t
}

function applyVol() {
  if (audioEl.value) audioEl.value.volume = vol.value
}

function fmt(s) {
  if (!s || !isFinite(s)) return '0:00'
  const m = Math.floor(s / 60)
  const sec = Math.floor(s % 60)
  return `${m}:${String(sec).padStart(2, '0')}`
}

onBeforeUnmount(() => {
  audioEl.value?.pause()
})
</script>
