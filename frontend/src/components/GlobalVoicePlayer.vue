<template>
  <Transition name="gvp-slide">
    <div v-if="store.src" class="global-voice-player">
      <button class="gvp-play-btn" @click="togglePlay" :title="store.playing ? 'Pause' : 'Play'">
        <svg v-if="!store.playing" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
      </button>

      <div class="gvp-body">
        <span class="gvp-sender">{{ store.sender || 'Voice message' }}</span>
        <div class="gvp-track">
          <input
            type="range"
            class="gvp-seek"
            :value="isSeeking ? seekPreview : store.current"
            :max="store.duration || 0"
            step="0.05"
            @mousedown="isSeeking = true"
            @touchstart="isSeeking = true"
            @input="e => seekPreview = +e.target.value"
            @change="onSeek"
          />
          <div class="gvp-fill" :style="{ width: progressPct }" />
        </div>
      </div>

      <span class="gvp-time">{{ fmt(store.current) }}<span class="gvp-time-dim">/{{ fmt(store.duration) }}</span></span>

      <button class="gvp-speed-btn" @click="cycleSpeed" title="Playback speed">{{ store.speed }}×</button>

      <div class="gvp-vol">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
          <path v-if="store.vol > 0" d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
          <line v-if="store.vol === 0" x1="23" y1="9" x2="17" y2="15"/>
          <line v-if="store.vol === 0" x1="17" y1="9" x2="23" y2="15"/>
        </svg>
        <input
          type="range"
          class="gvp-volume"
          :value="store.vol"
          min="0"
          max="1"
          step="0.05"
          @input="e => store._setVol?.(+e.target.value)"
        />
      </div>

      <button class="gvp-close-btn" @click="stop" title="Stop">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed } from 'vue'
import { voiceStore, SPEEDS } from '../voiceStore.js'

const store = voiceStore
const isSeeking = ref(false)
const seekPreview = ref(0)

const progressPct = computed(() => {
  const val = isSeeking.value ? seekPreview.value : store.current
  return store.duration ? (val / store.duration * 100) + '%' : '0%'
})

function togglePlay() {
  if (store.playing) store._pause?.()
  else store._play?.()
}

function onSeek(e) {
  isSeeking.value = false
  store._seek?.(+e.target.value)
}

function cycleSpeed() {
  const idx = SPEEDS.indexOf(store.speed)
  store._setSpeed?.(SPEEDS[(idx + 1) % SPEEDS.length])
}

function stop() {
  store._stop?.()
}

function fmt(s) {
  if (!s || !isFinite(s)) return '0:00'
  const m = Math.floor(s / 60)
  const sec = Math.floor(s % 60)
  return `${m}:${String(sec).padStart(2, '0')}`
}
</script>
