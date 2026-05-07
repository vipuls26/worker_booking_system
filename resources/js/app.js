import '../css/app.css';
import 'primeicons/primeicons.css';
import 'vue-sonner/style.css';

import { createPinia } from 'pinia';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';

createApp(App).use(createPinia()).use(router).mount('#app');
