<template>
  <div
    class="avatar"
    :class="sizeClass"
    :style="!avatarUrl ? { background: bgColor } : {}"
    :title="username"
  >
    <img v-if="avatarUrl" :src="avatarUrl" :alt="username" />
    <span v-else>{{ initials }}</span>
    <span v-if="isOnline" class="avatar-online-dot" />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  username: { type: String, default: '?' },
  avatarUrl: { type: String, default: null },
  size: { type: String, default: 'md' },
  isOnline: { type: Boolean, default: false },
})

const COLORS = [
  '#5b6aff', '#7c3aed', '#db2777', '#059669',
  '#d97706', '#0284c7', '#dc2626', '#0891b2',
]

function hashCode(str) {
  let h = 0
  for (let i = 0; i < str.length; i++) {
    h = (Math.imul(31, h) + str.charCodeAt(i)) | 0
  }
  return Math.abs(h)
}

const initials = computed(() => {
  const parts = props.username.trim().split(/[\s._-]+/)
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return props.username.slice(0, 2).toUpperCase()
})

const bgColor = computed(() => COLORS[hashCode(props.username) % COLORS.length])
const sizeClass = computed(() => `avatar-${props.size}`)
</script>
