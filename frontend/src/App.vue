<template>
  <router-view />
</template>

<script setup>
import { onErrorCaptured } from 'vue'

onErrorCaptured((err, instance, info) => {
  console.error('[Vue onErrorCaptured]', info, err)
  return false
})
</script>
