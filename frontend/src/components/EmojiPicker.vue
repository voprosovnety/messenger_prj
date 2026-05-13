<template>
  <div class="emoji-picker" @click.stop @mousedown.stop>
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
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

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
