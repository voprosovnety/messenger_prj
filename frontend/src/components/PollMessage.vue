<template>
  <div class="poll-card">
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
          'poll-option-disabled': isDeleted,
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
      <button
        v-if="allowRetraction && myVotes.length > 0"
        class="poll-retract-btn"
        @click.stop="$emit('retract')"
      >&#8629; Return vote</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

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
  emit('vote', optId)
}
</script>
