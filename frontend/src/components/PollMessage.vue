<template>
  <div
    class="poll-card"
    @contextmenu.prevent="openContextMenu"
    @touchstart.passive="onTouchStart"
    @touchmove.passive="onTouchMove"
    @touchend.passive="onTouchEnd"
  >
    <div class="poll-header">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0"><rect x="3" y="3" width="4" height="18" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="13" width="4" height="8" rx="1"/></svg>
      <span class="poll-type-label">{{ poll.anonymous ? 'Anonymous Poll' : 'Poll' }}</span>
    </div>
    <div class="poll-question">{{ poll.question }}</div>

    <div class="poll-options">
      <button
        v-for="(opt, index) in poll.options"
        :key="opt.id"
        class="poll-option"
        :class="{
          'poll-option-voted': isVoted(opt.id),
          'poll-option-winner': showResults && isWinner(opt),
          'poll-option-disabled': isDeleted || isVoted(opt.id) || (myVotes.length > 0 && !poll.multiple_answers),
        }"
        :style="{ transitionDelay: index * 60 + 'ms' }"
        :disabled="isDeleted"
        @click="vote(opt.id)"
      >
        <div class="poll-option-bar" :style="{ width: pct(opt) + '%' }"></div>
        <div class="poll-option-content">
          <span class="poll-option-check">
            <svg v-if="isVoted(opt.id)" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span v-else class="poll-option-dot"></span>
          </span>
          <span class="poll-option-text">{{ opt.text }}</span>
          <span v-if="showResults" class="poll-option-pct">{{ pct(opt) }}%</span>
        </div>
        <div v-if="showResults && !poll.anonymous && opt.voters?.length" class="poll-option-voters">
          {{ opt.voters.join(', ') }}
        </div>
      </button>
    </div>

    <div class="poll-footer">
      <span v-if="poll.total_votes === 0" class="poll-footer-votes">No votes yet</span>
      <button
        v-else
        class="poll-retract-btn poll-footer-votes"
        @click.stop="$emit('show-results')"
      >{{ poll.total_votes }} vote{{ poll.total_votes !== 1 ? 's' : '' }}</button>
      <span v-if="poll.multiple_answers" class="poll-multi-label">· Multiple answers</span>
    </div>

    <!-- Context menu (right-click / long-press) -->
    <Teleport to="body">
      <template v-if="contextMenu.visible && allowRetraction && myVotes.length > 0">
        <div class="poll-ctx-backdrop" @click="closeContextMenu" @contextmenu.prevent="closeContextMenu"></div>
        <div
          class="poll-ctx-menu"
          :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
          role="menu"
        >
          <button class="poll-ctx-menu-item" @click="retractAndClose">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            Retract vote
          </button>
        </div>
      </template>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, ref, onUnmounted } from 'vue'

const props = defineProps({
  poll: { type: Object, required: true },
  myUsername: { type: String, default: null },
  isDeleted: { type: Boolean, default: false },
  allowRetraction: { type: Boolean, default: false },
  chatId: { type: [Number, String], default: null },
})
const emit = defineEmits(['vote', 'retract', 'show-results'])

const showResults = computed(() => props.poll.total_votes > 0)
const myVotes = computed(() => props.poll.my_votes || [])

function isVoted(optId) {
  return myVotes.value.includes(optId)
}

function pct(opt) {
  if (!props.poll.total_votes) return 0
  return Math.round((opt.votes / props.poll.total_votes) * 100)
}

function isWinner(opt) {
  if (!props.poll.total_votes) return false
  const max = Math.max(...props.poll.options.map(o => o.votes))
  return opt.votes === max && opt.votes > 0
}

function vote(optId) {
  if (props.isDeleted) return
  // Already voted for this option — no toggle
  if (isVoted(optId)) return
  // Single-choice: already voted for any option — do nothing
  if (!props.poll.multiple_answers && myVotes.value.length > 0) return
  // Multiple-choice: already voted for this specific option — guard above covers it
  emit('vote', optId)
}

// ── Context menu ──────────────────────────────────────────────────────
const contextMenu = ref({ visible: false, x: 0, y: 0 })
let longPressTimer = null

function openContextMenu(event) {
  if (!props.allowRetraction || myVotes.value.length === 0) return
  const x = event.clientX ?? (event.touches?.[0]?.clientX ?? 0)
  const y = event.clientY ?? (event.touches?.[0]?.clientY ?? 0)
  // Clamp so the menu does not overflow the viewport
  const menuW = 180
  const menuH = 48
  contextMenu.value = {
    visible: true,
    x: Math.min(x, window.innerWidth - menuW - 8),
    y: Math.min(y, window.innerHeight - menuH - 8),
  }
}

function closeContextMenu() {
  contextMenu.value.visible = false
}

function retractAndClose() {
  closeContextMenu()
  emit('retract')
}

// Long-press support for touch devices
function onTouchStart(e) {
  clearTimeout(longPressTimer)
  // Capture coordinates immediately — touches list is live and may be empty by timeout fire
  const touch = e.touches?.[0]
  const coords = touch ? { clientX: touch.clientX, clientY: touch.clientY } : null
  longPressTimer = setTimeout(() => {
    if (navigator.vibrate) navigator.vibrate(10)
    if (coords) openContextMenu(coords)
  }, 500)
}

function onTouchMove() {
  clearTimeout(longPressTimer)
}

function onTouchEnd() {
  clearTimeout(longPressTimer)
}

onUnmounted(() => {
  clearTimeout(longPressTimer)
})
</script>
