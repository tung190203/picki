/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function initEcho(token) {
    if (window.Echo) {
        window.Echo.disconnectInstrumentNotifications?.();
        window.Echo.disconnect?.();
        if (window.Echo.connector?.pusher) {
            window.Echo.connector.pusher.disconnect();
        }
        window.Echo = null;
    }

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        wsHost: (import.meta.env.VITE_PUSHER_HOST && import.meta.env.VITE_PUSHER_HOST.trim() !== '')
            ? import.meta.env.VITE_PUSHER_HOST
            : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
        wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
        wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/api/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
    });
}

// Initialize Echo only if a valid token exists
const storedToken = localStorage.getItem('access_token');
if (storedToken && storedToken.trim() !== '') {
    initEcho(storedToken);
} else {
    // Initialize Echo for public pages (no auth required)
    initEcho(null);
}

// Re-init Echo when the token changes (e.g., after login/register)
// Note: auth store calls window.initEcho() directly after setting the token
window.initEcho = initEcho;
