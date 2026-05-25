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

    <!-- Waveform canvas (shown when decode succeeded) -->
    <canvas
      v-if="!waveformFailed"
      ref="canvasEl"
      class="ap-waveform"
      @click="seekByCanvas"
      @mousedown.prevent
    />
    <!-- Fallback: original range track -->
    <div v-else class="ap-track">
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

    <span class="ap-time">{{ playing ? fmt(current) : fmt(duration) }}</span>
  </div>
</template>

<script>
// Runs once at module level — shared across ALL AudioPlayer instances
const _active = { stop: null }
</script>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { voiceStore, SPEEDS } from '../voiceStore.js'

const props = defineProps({
  src: { type: String, required: true },
  sender: { type: String, default: '' },
})
const emit = defineEmits(['ended'])

const _storedSpeed = parseFloat(localStorage.getItem('audioSpeed') || '1')
const speed = ref(SPEEDS.includes(_storedSpeed) ? _storedSpeed : 1)

const audioEl = ref(null)
const canvasEl = ref(null)
const playing = ref(false)
const current = ref(0)
const duration = ref(0)
const vol = ref(voiceStore.vol)
let seeking = false

// Waveform state
const waveformFailed = ref(false)
let peaks = []      // Float32Array-like: downsampled amplitude peaks
let audioCtx = null // created lazily on first toggle() call (autoplay policy)
let decodePromise = null

// ─── Waveform decode ──────────────────────────────────────────────
const BAR_COUNT = 60

async function decodePeaks() {
  try {
    // Create AudioContext for decoding only; suspended by default in many browsers
    // — we just need decodeAudioData which doesn't require a running context.
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)()
    const response = await fetch(props.src, { credentials: 'same-origin' })
    if (!response.ok) throw new Error('fetch failed')
    const arrayBuf = await response.arrayBuffer()
    const audioBuf = await audioCtx.decodeAudioData(arrayBuf)
    const channelData = audioBuf.getChannelData(0)
    const blockSize = Math.floor(channelData.length / BAR_COUNT)
    const newPeaks = new Float32Array(BAR_COUNT)
    for (let i = 0; i < BAR_COUNT; i++) {
      let max = 0
      const start = i * blockSize
      for (let j = 0; j < blockSize; j++) {
        const abs = Math.abs(channelData[start + j])
        if (abs > max) max = abs
      }
      newPeaks[i] = max
    }
    peaks = newPeaks
    drawWaveform()
  } catch (e) {
    // Cross-origin, unsupported format, or browser restriction — fall back gracefully
    waveformFailed.value = true
  }
}

// ─── Canvas drawing ───────────────────────────────────────────────
function drawWaveform() {
  const canvas = canvasEl.value
  if (!canvas || !peaks.length) return
  const dpr = window.devicePixelRatio || 1
  const w = canvas.clientWidth
  const h = canvas.clientHeight
  if (w === 0 || h === 0) return

  // Size canvas backing store to match display size
  if (canvas.width !== Math.round(w * dpr) || canvas.height !== Math.round(h * dpr)) {
    canvas.width = Math.round(w * dpr)
    canvas.height = Math.round(h * dpr)
  }

  const ctx = canvas.getContext('2d')
  ctx.clearRect(0, 0, canvas.width, canvas.height)

  const progress = duration.value > 0 ? current.value / duration.value : 0
  const BAR_W = 2
  const BAR_GAP = 2
  const STEP = BAR_W + BAR_GAP
  const usableW = canvas.width
  // Use actual peak count constrained by available canvas width
  const barCount = Math.min(peaks.length, Math.floor(usableW / (STEP * dpr)))
  const MIN_H = 3 * dpr
  const MAX_H = (h - 4) * dpr // 2px top + 2px bottom margin

  // Resolve CSS variables for colors
  const accentColor = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#5b8dee'
  const dimColor = getComputedStyle(document.documentElement).getPropertyValue('--text-3').trim() || '#484f58'

  const playedIdx = Math.round(progress * barCount)

  for (let i = 0; i < barCount; i++) {
    const peakIdx = Math.round((i / barCount) * peaks.length)
    const amplitude = peaks[Math.min(peakIdx, peaks.length - 1)]
    const barH = Math.max(MIN_H, amplitude * MAX_H)
    const x = i * STEP * dpr
    const y = (canvas.height - barH) / 2

    ctx.fillStyle = i < playedIdx ? accentColor : dimColor
    // Slightly brighter for the current bar
    if (i === playedIdx) ctx.fillStyle = accentColor
    ctx.beginPath()
    ctx.roundRect(x, y, BAR_W * dpr, barH, 1)
    ctx.fill()
  }
}

// ─── Click-to-seek on canvas ──────────────────────────────────────
function seekByCanvas(e) {
  const canvas = canvasEl.value
  if (!canvas || !duration.value) return
  const rect = canvas.getBoundingClientRect()
  const ratio = (e.clientX - rect.left) / rect.width
  const t = Math.max(0, Math.min(duration.value, ratio * duration.value))
  current.value = t
  if (audioEl.value) audioEl.value.currentTime = t
}

// ─── Audio element callbacks ──────────────────────────────────────
function _stopSelf() {
  const a = audioEl.value
  if (a && !a.paused) a.pause()
  playing.value = false
  if (_active.stop === _stopSelf) _active.stop = null
  if (voiceStore.src === props.src) voiceStore.playing = false
}

function _resumeCtx() {
  if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume().catch(() => {})
}

function _registerWithStore() {
  voiceStore.src = props.src
  voiceStore.sender = props.sender || ''
  voiceStore.playing = true
  voiceStore.current = current.value
  voiceStore.duration = duration.value
  voiceStore.speed = speed.value
  voiceStore.vol = vol.value
  voiceStore._play = play
  voiceStore._pause = _stopSelf
  voiceStore._seek = (t) => {
    current.value = t
    if (audioEl.value) audioEl.value.currentTime = t
  }
  voiceStore._setSpeed = (s) => {
    const next = SPEEDS.includes(s) ? s : 1
    speed.value = next
    voiceStore.speed = next
    if (audioEl.value) audioEl.value.playbackRate = next
    localStorage.setItem('audioSpeed', String(next))
  }
  voiceStore._setVol = (v) => {
    vol.value = v
    voiceStore.vol = v
    if (audioEl.value) audioEl.value.volume = v
    localStorage.setItem('audioVol', String(v))
  }
  voiceStore._stop = () => {
    _stopSelf()
    if (voiceStore.src === props.src) {
      voiceStore.src = null
      voiceStore.sender = ''
      voiceStore._play = voiceStore._pause = voiceStore._seek =
        voiceStore._setSpeed = voiceStore._setVol = voiceStore._stop = null
    }
  }
}

function toggle() {
  const a = audioEl.value
  if (!a) return
  _resumeCtx()
  if (a.paused) {
    _active.stop?.()
    _active.stop = _stopSelf
    a.play()
    playing.value = true
    _registerWithStore()
  } else {
    _stopSelf()
  }
}

// Exposed so ChatView can call .play() programmatically
function play() {
  const a = audioEl.value
  if (!a || !a.paused) return
  _resumeCtx()
  _active.stop?.()
  _active.stop = _stopSelf
  a.play()
  playing.value = true
  _registerWithStore()
}

function onMeta() {
  const a = audioEl.value
  if (!a) return
  a.volume = vol.value
  a.playbackRate = speed.value
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
  if (voiceStore.src === props.src) voiceStore.duration = a.duration
  a.currentTime = 0
}

function onEnded() {
  if (!playing.value) return  // spurious ended from seek-to-1e10 duration probe (Safari quirk)
  if (_active.stop === _stopSelf) _active.stop = null
  playing.value = false
  emit('ended')
  if (voiceStore.src === props.src) {
    voiceStore.src = null
    voiceStore.sender = ''
    voiceStore._play = voiceStore._pause = voiceStore._seek =
      voiceStore._setSpeed = voiceStore._setVol = voiceStore._stop = null
  }
}

function onTimeUpdate() {
  if (!seeking) {
    current.value = audioEl.value?.currentTime ?? 0
    if (voiceStore.src === props.src) voiceStore.current = current.value
    if (!waveformFailed.value && peaks.length) drawWaveform()
  }
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


function fmt(s) {
  if (!s || !isFinite(s)) return '0:00'
  const m = Math.floor(s / 60)
  const sec = Math.floor(s % 60)
  return `${m}:${String(sec).padStart(2, '0')}`
}

onMounted(() => {
  // Start decoding immediately; browsers allow decodeAudioData without user interaction
  decodePromise = decodePeaks()
  // Draw initial (empty progress) waveform after next tick so canvas has layout
  nextTick(() => drawWaveform())
})

onBeforeUnmount(() => {
  if (_active.stop === _stopSelf) _active.stop = null
  audioEl.value?.pause()
  if (voiceStore.src === props.src) {
    voiceStore.src = null
    voiceStore.sender = ''
    voiceStore.playing = false
    voiceStore._play = voiceStore._pause = voiceStore._seek =
      voiceStore._setSpeed = voiceStore._setVol = voiceStore._stop = null
  }
  if (audioCtx) {
    audioCtx.close().catch(() => {})
    audioCtx = null
  }
})

defineExpose({ play })
</script>
