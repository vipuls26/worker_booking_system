import { onBeforeUnmount, watch } from 'vue';

export function useDebouncedWatch(source, callback, delay = 400, options = {}) {
    let timeout = null;

    const stop = watch(
        source,
        (...args) => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => callback(...args), delay);
        },
        options,
    );

    onBeforeUnmount(() => {
        window.clearTimeout(timeout);
        stop();
    });

    return stop;
}
