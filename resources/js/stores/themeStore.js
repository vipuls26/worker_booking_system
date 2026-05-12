import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

function preferredDarkMode() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

export const useThemeStore = defineStore('theme', () => {
    const darkMode = ref(false);

    function applyTheme(value) {
        darkMode.value = value;
        document.documentElement.classList.toggle('dark', value);
        localStorage.setItem('theme', value ? 'dark' : 'light');
    }

    function initializeTheme() {
        const storedTheme = localStorage.getItem('theme');
        applyTheme(storedTheme ? storedTheme === 'dark' : preferredDarkMode());
    }

    function toggleTheme() {
        applyTheme(!darkMode.value);
    }

    const themeLabel = computed(() => (darkMode.value ? 'Switch to light mode' : 'Switch to dark mode'));

    return {
        darkMode,
        themeLabel,
        initializeTheme,
        toggleTheme,
    };
});
