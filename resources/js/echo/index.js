/**
 * Platform Echo — IIFE Entry
 *
 * Sets up Laravel Echo with Reverb (Pusher protocol) for real-time broadcasting.
 * Reads config from <meta> tags set by the Blade layout.
 * If config is missing (Reverb not configured), silently skips initialization.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

function init() {
    const key = document.querySelector('meta[name="reverb-key"]')?.content;
    const host = document.querySelector('meta[name="reverb-host"]')?.content;
    const port = document.querySelector('meta[name="reverb-port"]')?.content;

    // Graceful degradation: no config → no Echo
    if (!key || !host) {
        return;
    }

    window.Pusher = Pusher;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port || 443,
        wssPort: port || 443,
        forceTLS: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
        authorizer: (channel) => {
            return {
                authorize: (socketId, callback) => {
                    fetch('/broadcasting/auth', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            socket_id: socketId,
                            channel_name: channel.name,
                        }),
                    })
                    .then(response => {
                        if (!response.ok) throw new Error('Auth failed: ' + response.status);
                        return response.json();
                    })
                    .then(data => callback(null, data))
                    .catch(error => callback(error));
                },
            };
        },
    });

    // Presence channel for online status
    const teamId = document.querySelector('meta[name="team-id"]')?.content;
    if (teamId) {
        window._onlineUsers = new Set();

        function dispatchPresence() {
            window.dispatchEvent(new CustomEvent('presence-updated', { detail: [...window._onlineUsers] }));
        }

        window.Echo.join(`terminal.team.${teamId}`)
            .here(users => {
                window._onlineUsers = new Set(users.map(u => Number(u.id)));
                dispatchPresence();
            })
            .joining(user => {
                window._onlineUsers.add(Number(user.id));
                dispatchPresence();
            })
            .leaving(user => {
                window._onlineUsers.delete(Number(user.id));
                dispatchPresence();
            })
            .error(error => {
                console.warn('[Terminal] Presence channel error:', error);
            });
    }
}

// Init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
