import { watch, nextTick, onBeforeUnmount } from 'vue'

const FOCUSABLE = 'input, button, textarea, select, a[href], [tabindex]:not([tabindex="-1"])'

/**
 * Traps keyboard focus inside a modal element while the modal is open.
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef - ref to the modal root element
 * @param {import('vue').Ref<boolean>} isOpenRef - boolean ref controlling visibility
 */
export function useFocusTrap(containerRef, isOpenRef) {
  let previouslyFocused = null

  function getFocusable() {
    if (!containerRef.value) return []
    return Array.from(containerRef.value.querySelectorAll(FOCUSABLE)).filter(
      el => !el.disabled && el.offsetParent !== null
    )
  }

  function onKeydown(e) {
    if (e.key !== 'Tab') return
    const focusable = getFocusable()
    if (!focusable.length) { e.preventDefault(); return }
    const first = focusable[0]
    const last  = focusable[focusable.length - 1]
    if (e.shiftKey) {
      if (document.activeElement === first || !containerRef.value?.contains(document.activeElement)) {
        e.preventDefault()
        last.focus()
      }
    } else {
      if (document.activeElement === last || !containerRef.value?.contains(document.activeElement)) {
        e.preventDefault()
        first.focus()
      }
    }
  }

  function activate() {
    previouslyFocused = document.activeElement
    nextTick(() => {
      const focusable = getFocusable()
      if (focusable.length) focusable[0].focus()
    })
    document.addEventListener('keydown', onKeydown)
  }

  function deactivate() {
    document.removeEventListener('keydown', onKeydown)
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
      previouslyFocused.focus()
    }
    previouslyFocused = null
  }

  const stopWatch = watch(
    isOpenRef,
    (open) => {
      if (open) activate()
      else deactivate()
    },
    { immediate: false }
  )

  onBeforeUnmount(() => {
    stopWatch()
    deactivate()
  })
}
