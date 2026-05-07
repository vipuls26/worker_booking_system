import { computed, ref } from 'vue';

const isDark = ref(false);

function preferredDarkMode() {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
}

function applyTheme(value) {
    isDark.value = value;
    document.documentElement.classList.toggle('dark', value);
    localStorage.setItem('theme', value ? 'dark' : 'light');
}

export function initializeTheme() {
    const storedTheme = localStorage.getItem('theme');
    applyTheme(storedTheme ? storedTheme === 'dark' : preferredDarkMode());
}

export function useTheme() {
    const icon = computed(() => (isDark.value ? 'pi-moon' : 'pi-sun'));
    const label = computed(() => (isDark.value ? 'Switch to light mode' : 'Switch to dark mode'));

    function toggleTheme() {
        applyTheme(!isDark.value);
    }

    return {
        isDark,
        icon,
        label,
        toggleTheme,
    };
}
