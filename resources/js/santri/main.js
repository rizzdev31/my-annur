import { createApp } from 'vue'
import router from './router'
import App from './App.vue'
import '../../css/santri.css'

createApp(App).use(router).mount('#santri-app')

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/santri-sw.js', { scope: '/santri/' }).catch(() => {})
    })
}
