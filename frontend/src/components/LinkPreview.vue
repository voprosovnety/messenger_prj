<template>
  <!-- Loading skeleton -->
  <div v-if="isLoading" class="link-preview link-preview--skeleton" aria-hidden="true">
    <div class="lp-skeleton-img skeleton-bubble"></div>
    <div class="link-preview-body lp-skeleton-body">
      <div class="lp-skeleton-title skeleton-bubble"></div>
      <div class="lp-skeleton-desc skeleton-bubble"></div>
      <div class="lp-skeleton-domain skeleton-bubble"></div>
    </div>
  </div>

  <!-- Loaded preview -->
  <a
    v-else-if="preview"
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
const props = defineProps({
  preview:   { type: Object, default: null },
  isLoading: { type: Boolean, default: false },
})
const domain = computed(() => {
  if (!props.preview?.url) return ''
  try { return new URL(props.preview.url).hostname } catch { return props.preview.url }
})
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
.link-preview:not(.link-preview--skeleton):hover { background: var(--surface-3); }
@media (hover: none) {
  .link-preview:not(.link-preview--skeleton):hover { background: var(--surface-2); }
}

/* Skeleton variant */
.link-preview--skeleton {
  cursor: default;
  pointer-events: none;
}
.lp-skeleton-img {
  width: 80px;
  min-width: 80px;
  height: auto;
  min-height: 72px;
  border-radius: 0;
  flex-shrink: 0;
}
.lp-skeleton-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px 10px;
  justify-content: center;
}
.lp-skeleton-title {
  height: 13px;
  width: 80%;
  border-radius: 4px;
}
.lp-skeleton-desc {
  height: 11px;
  width: 95%;
  border-radius: 4px;
}
.lp-skeleton-domain {
  height: 10px;
  width: 50%;
  border-radius: 4px;
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
