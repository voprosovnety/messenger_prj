import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'

// Apply saved theme
const savedTheme = localStorage.getItem('theme')
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme)

createApp(App).use(router).mount('#app')