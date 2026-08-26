import './bootstrap'
import '../css/app.css';
import { createApp } from 'vue'
import {createPinia} from "pinia";
import App from './App.vue'
import router from './router'
import Vue3Toastify from 'vue3-toastify'
import 'vue3-toastify/dist/index.css'
import FloatingVue from 'floating-vue';
import 'floating-vue/dist/style.css';
import EmojiPicker from 'vue3-emoji-picker'
import 'vue3-emoji-picker/css'
import { initTheme } from './utils/theme.js'

initTheme()

const pinia = createPinia();

const app = createApp(App)
app.use(pinia)
app.use(Vue3Toastify, {
    position: 'top-right',
    autoClose: 3000,
    hideProgressBar: false,
    closeOnClick: true,
    pauseOnHover: true,
    draggable: true,
    progress: undefined,
})
app.component('EmojiPicker', EmojiPicker)
app.use(FloatingVue);
app.use(router)
app.mount('#app')
