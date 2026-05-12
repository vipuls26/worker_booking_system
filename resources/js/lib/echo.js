import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echoInstance = null;

export function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/api/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}`,
                Accept: 'application/json',
            },
        },
    });

    window.Echo = echoInstance;

    return echoInstance;
}

export function disconnectEcho() {
    if (! echoInstance) {
        return;
    }

    echoInstance.disconnect();
    echoInstance = null;
    window.Echo = null;
}
