<template>
  <div class="emoji-picker" @click.stop @mousedown.stop>
    <div class="emoji-picker-search-wrap">
      <input
        v-model="searchQuery"
        class="emoji-search-input"
        placeholder="Search…"
        inputmode="search"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
        spellcheck="false"
        @click.stop
        @mousedown.stop
        @keydown.escape.stop="searchQuery = ''"
      />
    </div>
    <template v-if="!searchQuery.trim()">
      <div class="emoji-cat-tabs">
        <button
          v-for="cat in allCats"
          :key="cat.id"
          class="emoji-cat-tab"
          :class="{ active: activeCat === cat.id }"
          :title="cat.label"
          @click="activeCat = cat.id"
        >{{ cat.icon }}</button>
      </div>
      <div class="emoji-cat-label">{{ currentCat.label }}</div>
      <div class="emoji-grid-wrap">
        <div v-if="currentEmojis.length" class="emoji-grid">
          <button
            v-for="e in currentEmojis"
            :key="e"
            class="emoji-btn"
            :title="e"
            @click="select(e)"
          >{{ e }}</button>
        </div>
        <div v-else class="emoji-empty">No recent emojis yet</div>
      </div>
    </template>
    <template v-else>
      <div class="emoji-grid-wrap">
        <div v-if="filteredEmojis.length" class="emoji-grid">
          <button
            v-for="e in filteredEmojis"
            :key="e"
            class="emoji-btn"
            :title="e"
            @click="select(e)"
          >{{ e }}</button>
        </div>
        <div v-else class="emoji-empty">No results</div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const searchQuery = ref('')

const emit = defineEmits(['select'])

const RECENT_KEY = 'emoji_recent'
const MAX_RECENT = 10

const CATEGORIES = [
  { id: 'smileys', label: 'Smileys & Emotion', icon: '😀', emojis: [
    '😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😚','😋','😛','😜','🤪','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','🤬','😈','👿','💀','💩','🤡','👻','👽','👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿','😾',
  ]},
  { id: 'people', label: 'People & Body', icon: '👋', emojis: [
    '👋','🤚','✋','🖖','👌','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','☝️','👇','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','💅','💪','🫶','❤️‍🔥','🦾','🦿','🦵','🦶','👂','🦻','👃','👀','👅','👄',
  ]},
  { id: 'animals', label: 'Animals & Nature', icon: '🐶', emojis: [
    '🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🐦','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🦋','🐌','🐞','🐜','🐢','🐍','🦎','🐙','🦑','🐠','🐟','🐬','🐳','🦈','🦒','🐘','🦏','🦛','🐪','🐫','🐄','🐎','🐑','🦌','🕊️','🐇','🦝','🦡','🦦','🐀','🐿️',
    '🌱','🌿','🍀','🌸','🌺','🌻','🌼','💐','🌷','🍁','🍂','🍃','🌲','🌳','🌴','🌵','🍄','🌾','⭐','🌟','✨','⚡','🔥','💧','🌊','🌈','❄️','🌙','☀️','⛅',
  ]},
  { id: 'food', label: 'Food & Drink', icon: '🍕', emojis: [
    '🍎','🍊','🍋','🍌','🍇','🍓','🫐','🍑','🍒','🍍','🥭','🥝','🍅','🍆','🥑','🥦','🌽','🥕','🧄','🧅','🥔','🍔','🍟','🍕','🌭','🍗','🍖','🥓','🥩','🍳','🥚','🧀','🥞','🧇','🥐','🍞','🥖','🌮','🌯','🍱','🍣','🍜','🍲','🍛','🍝','🍙','🍚','🍡','🧁','🍰','🎂','🍩','🍪','🍫','🍬','🍭','🍿','🌰','☕','🍵','🧋','🥤','🍺','🍷','🥂','🍸','🍾',
  ]},
  { id: 'activity', label: 'Activities', icon: '⚽', emojis: [
    '⚽','🏀','🏈','⚾','🎾','🏐','🏉','🎱','🏓','🏸','🥊','🎯','🎳','🎮','🎲','♟️','🎭','🎨','🎬','🎤','🎧','🎼','🎵','🎶','🎸','🎹','🥁','🎷','🎺','🎻','🎉','🎊','🎁','🎀','🎈','🎆','🎇','🏆','🥇','🥈','🥉','🏅','🎖️',
  ]},
  { id: 'travel', label: 'Travel & Places', icon: '✈️', emojis: [
    '🚗','🚕','🚙','🚌','🏎️','🚓','🚑','🚒','🛻','🚜','🏍️','🛵','🚲','🛴','🛹','🚁','✈️','🛩️','🚀','🛸','⛵','🚢','🚂','🚆','🚇','🚉','🏔️','⛰️','🌋','🏕️','🏖️','🏜️','🏝️','🏠','🏢','🏥','🏦','🏨','🏪','🏫','🏯','🏰','🌍','🌎','🌏','🗺️','🌐','🗽','🗼','🌉','🌃','🌆','🌇','🌌','🌠',
  ]},
  { id: 'objects', label: 'Objects', icon: '💡', emojis: [
    '💡','🔦','🕯️','💰','💵','💳','🪙','📦','📫','📬','✏️','📝','📁','📂','📅','📊','📋','📌','📍','✂️','🔒','🔓','🔑','🗝️','🔨','⚙️','🔗','🧲','🧪','🧬','🔬','🔭','📡','💊','💉','💎','💍','👑','💬','💭','🔔','📻','📺','📷','💻','⌨️','☎️','📱','🔋','🔌','⌛','⏳','📅','🗑️','🧸',
  ]},
  { id: 'symbols', label: 'Symbols', icon: '❤️', emojis: [
    '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️','✝️','☪️','🕉️','☸️','✡️','☯️','⭕','❌','❓','❗','💯','♻️','✅','❎','🔔','🔕','🔊','🔇','🔅','🔆','📶','🔵','🔴','⚫','⚪','🟣','🟢','🟡','🟠','🔶','🔷','🔸','🔹','🔺','🔻','💠','🔘','🔲','🔳','▪️','▫️','🟥','🟧','🟨','🟩','🟦','🟪','⭐','🌟','🔥','💧','🌊','🌈',
  ]},
]

const recent = ref(JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'))

const allCats = computed(() => [
  { id: 'recent', label: 'Recently used', icon: '🕒', emojis: recent.value },
  ...CATEGORIES,
])

const allEmojis = computed(() => {
  const seen = new Set()
  const result = []
  for (const cat of CATEGORIES) {
    for (const e of cat.emojis) {
      if (!seen.has(e)) { seen.add(e); result.push(e) }
    }
  }
  return result
})

const filteredEmojis = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return allEmojis.value
  // Simple filter: check if the query matches the emoji itself or any substring
  // (emoji have no text names here, so show all if no match by character)
  return allEmojis.value.filter(e => e.includes(q))
})

const activeCat = ref('smileys')
const currentCat = computed(() => allCats.value.find(c => c.id === activeCat.value) || allCats.value[0])
const currentEmojis = computed(() => currentCat.value.emojis)

function select(emoji) {
  const arr = recent.value.filter(e => e !== emoji)
  arr.unshift(emoji)
  recent.value = arr.slice(0, MAX_RECENT)
  localStorage.setItem(RECENT_KEY, JSON.stringify(recent.value))
  emit('select', emoji)
}
</script>
