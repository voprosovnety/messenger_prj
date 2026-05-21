import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'

// Apply saved theme
const savedTheme = localStorage.getItem('theme')
if (savedTheme) document.documentElement.setAttribute('data-theme', savedTheme)

const app = createApp(App)
app.use(router)

app.config.errorHandler = (err, instance, info) => {
  console.error('[Vue error]', info, err)
}

app.mount('#app')