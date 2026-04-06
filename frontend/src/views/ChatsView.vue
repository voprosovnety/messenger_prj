<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api'

const router = useRouter()
const chats = ref([])

onMounted(async () => {
  const data = await api.listChats()
  chats.value = data.items || []
})

async function logout() {
  await api.logout()
  router.push('/login')
}
</script>

<template>
  <div style="max-width: 720px; margin: 20px auto;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <h2>Chats</h2>
      <button @click="logout">Logout</button>
    </div>

    <ul>
      <li v-for="c in chats" :key="c.id" style="margin: 10px 0;">
        <a href="#" @click.prevent="router.push(`/chats/${c.id}`)">
          {{ c.is_group ? 'Group' : 'DM' }} — {{ c.title || c.id }} (role: {{ c.my_role }})
        </a>
      </li>
    </ul>
  </div>
</template>