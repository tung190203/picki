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
        enabledTransports: ['ws', 'wss', 'sockjs'],
        authEndpoint: '/api/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
        },
        // Low-latency options
        disableStats: true,
        pongTimeout: 30000,
        activityTimeout: 30000,
    });
}

// Initialize Echo only if a valid token exists.
// Lý do: bootstrap ban đầu luôn gọi initEcho(null) cho public pages, khiến Echo
// được tạo với Authorization header "Bearer null". Khi user click vào trang
// subscribe private channel (match.{id}, chat.{id}, ...), Echo sẽ gọi
// /api/broadcasting/auth với Bearer null → 401, Pusher reconnect loop → UI chậm.
// Sửa: chỉ init Echo khi có token hợp lệ. Trang public (live-score) subscribe
// public channel qua window.Echo.channel() — nếu chưa có Echo, tự khởi tạo
// không cần auth (xem ensurePublicEcho() ở các trang public).
const storedToken = localStorage.getItem('access_token');
if (storedToken && storedToken.trim() !== '') {
    initEcho(storedToken);
} else {
    // Không có token -> KHÔNG init Echo ngay. Trang nào cần public channel
    // sẽ tự gọi window.initEcho(null) trước khi subscribe.
    window.initEcho = initEcho;
}

// Re-init Echo when the token changes (e.g., after login/register)
// Note: auth store calls window.initEcho() directly after setting the token
window.initEcho = initEcho;
