import { createApp } from 'vue'
import router from './router'
import './style.css'
import App from './App.vue'
import { MotionPlugin } from 'motion-v'

createApp(App).use(router).use(MotionPlugin).mount('#app')
