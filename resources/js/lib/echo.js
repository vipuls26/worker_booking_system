import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getStoredAuthToken } from './authStorage';

let echoInstance = null;

function currentAuthToken() {
    return getStoredAuthToken();
}

export function getEcho() {
    if (echoInstance) {
        return echoInstance;
    }

    window.Pusher = Pusher;

    const socketHost = window.location.hostname || import.meta.env.VITE_REVERB_HOST;
    const bearerToken = currentAuthToken();

    echoInstance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: socketHost,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/api/broadcasting/auth',
        bearerToken,
        auth: {
            headers: {
                Accept: 'application/json',
                'X-Client-Platform': 'web-spa',
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
