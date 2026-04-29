import { createRouter, createWebHistory } from 'vue-router'
import LoginView from './views/LoginView.vue'
import ChatsView from './views/ChatsView.vue'
import ChatView from './views/ChatView.vue'
import RegisterView from './views/RegisterView.vue'
import ProfileView from './views/ProfileView.vue'

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', component: LoginView },
        { path: '/register', component: RegisterView },
        { path: '/', component: ChatsView },
        { path: '/chats/:chatId', component: ChatView },
        { path: '/profile', component: ProfileView },
    ],
})

router.beforeEach((to) => {
    const access = localStorage.getItem('access_token')
    if (!access && to.path !== '/login' && to.path !== '/register') return '/login'
})

export default router
