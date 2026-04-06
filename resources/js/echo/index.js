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
    });

    // Presence channel for online status
    const teamId = document.querySelector('meta[name="team-id"]')?.content;
    if (teamId) {
        window._onlineUsers = new Set();

        function dispatchPresence() {
            window.dispatchEvent(new CustomEvent('presence-updated', { detail: [...window._onlineUsers] }));
        }

        // Auth presence channel manually via fetch, then subscribe via Pusher
        function authAndJoinPresence(socketId) {
            const params = new URLSearchParams();
            params.append('socket_id', socketId);
            params.append('channel_name', `presence-terminal.team.${teamId}`);

            fetch('/broadcasting/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: params.toString(),
            })
            .then(r => {
                if (!r.ok) throw new Error('Auth ' + r.status);
                return r.json();
            })
            .then(data => {
                const pusher = window.Echo.connector.pusher;
                const channel = pusher.subscribe(`presence-terminal.team.${teamId}`);

                channel.bind('pusher:subscription_succeeded', (members) => {
                    window._onlineUsers = new Set();
                    members.each(m => window._onlineUsers.add(Number(m.id)));
                    dispatchPresence();
                });
                channel.bind('pusher:member_added', (member) => {
                    window._onlineUsers.add(Number(member.id));
                    dispatchPresence();
                });
                channel.bind('pusher:member_removed', (member) => {
                    window._onlineUsers.delete(Number(member.id));
                    dispatchPresence();
                });
            })
            .catch(err => {
                console.warn('[Terminal] Presence auth failed:', err);
            });
        }

        // Wait for WebSocket connection to get socket ID
        const pusher = window.Echo.connector.pusher;
        if (pusher.connection.socket_id) {
            authAndJoinPresence(pusher.connection.socket_id);
        } else {
            pusher.connection.bind('connected', () => {
                authAndJoinPresence(pusher.connection.socket_id);
            });
        }
    }
}

// Init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
