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
    Pusher.logToConsole = true;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port || 443,
        wssPort: port || 443,
        forceTLS: true,
        enabledTransports: ['ws', 'wss'],
    });
}

// Init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
