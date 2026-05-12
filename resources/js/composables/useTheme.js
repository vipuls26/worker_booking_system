import { computed } from 'vue';
import { useThemeStore } from '../stores/themeStore';

export function initializeTheme() {
    const themeStore = useThemeStore();

    themeStore.initializeTheme();
}

export function useTheme() {
    const themeStore = useThemeStore();
    const isDark = computed(() => themeStore.darkMode);
    const icon = computed(() => (themeStore.darkMode ? 'pi-sun' : 'pi-moon'));
    const label = computed(() => themeStore.themeLabel);

    return {
        isDark,
        icon,
        label,
        toggleTheme: themeStore.toggleTheme,
    };
}
