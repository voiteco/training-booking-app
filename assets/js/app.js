import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import axios from 'axios';
import '../css/app.css';

// Настройка Axios
axios.defaults.baseURL = '/api';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Перехватчик для отправки device_token
axios.interceptors.request.use(config => {
    const deviceToken = localStorage.getItem('device_token');
    if (deviceToken) {
        config.headers['X-Device-Token'] = deviceToken;
    }
    return config;
});

// Создание приложения Vue
const app = createApp(App);
app.use(createPinia());
app.use(router);

// Монтирование приложения к DOM
app.mount('#app');