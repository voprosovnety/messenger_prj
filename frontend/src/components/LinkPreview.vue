<template>
  <a
    class="link-preview"
    :href="preview.url"
    target="_blank"
    rel="noopener noreferrer"
    @click.stop
  >
    <img v-if="preview.image" class="link-preview-img" :src="preview.image" :alt="preview.title" />
    <div class="link-preview-body">
      <div class="link-preview-title">{{ preview.title }}</div>
      <div v-if="preview.description" class="link-preview-desc">{{ preview.description }}</div>
      <div class="link-preview-domain">{{ domain }}</div>
    </div>
  </a>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({ preview: { type: Object, required: true } })
const domain = computed(() => { try { return new URL(props.preview.url).hostname } catch { return props.preview.url } })
</script>

<style scoped>
.link-preview {
  display: flex;
  align-items: stretch;
  gap: 0;
  margin-top: 6px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  overflow: hidden;
  text-decoration: none;
  background: var(--surface-2);
  transition: background var(--transition);
  max-width: 340px;
}
.link-preview:hover { background: var(--surface-3); }
@media (hover: none) {
  .link-preview:hover { background: var(--surface-2); }
}
.link-preview-img {
  width: 80px;
  min-width: 80px;
  object-fit: cover;
  flex-shrink: 0;
}
.link-preview-body {
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  justify-content: center;
}
.link-preview-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.link-preview-desc {
  font-size: 12px;
  color: var(--text-2);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.link-preview-domain {
  font-size: 11px;
  color: var(--text-3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
