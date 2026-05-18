import { configureEcho, echo } from '@laravel/echo-vue';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        __plannerateEchoConfigured: boolean;
    }
}

function metaContent(name: string): string | null {
    if (typeof document === 'undefined') return null;
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? null;
}

function echoLog(level: 'info' | 'warn' | 'error', ...args: unknown[]): void {
    const prefix = '[Echo]';
    if (level === 'error') console.error(prefix, ...args);
    else if (level === 'warn') console.warn(prefix, ...args);
    else console.log(prefix, ...args);
}

type PusherErrorEvent = {
    type?: string;
    error?: { data?: { code?: number; message?: string } };
};

function wsCloseCodeLabel(code?: number): string {
    if (code === undefined) return '';
    const labels: Record<number, string> = {
        1006: '(Abnormal closure — SSL/TLS failure or server unreachable)',
        1015: '(TLS handshake failure)',
        4001: '(Reverb: app does not exist — wrong APP_KEY)',
        4004: '(Reverb: app disabled)',
        4009: '(Reverb: connection unauthorized)',
        4100: '(Reverb: over capacity)',
    };
    return labels[code] ?? `(close code ${code})`;
}

export function initializeEcho(): void {
    if (typeof window === 'undefined') return;

    window.Pusher = Pusher;
    window.__plannerateEchoConfigured = false;

    Pusher.logToConsole = false;

    const key = metaContent('plannerate-reverb-key') ?? import.meta.env.VITE_REVERB_APP_KEY ?? '';
    const host = metaContent('plannerate-reverb-host') ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
    const port = Number(metaContent('plannerate-reverb-port') ?? import.meta.env.VITE_REVERB_PORT ?? 8080);
    const scheme = (metaContent('plannerate-reverb-scheme') ?? import.meta.env.VITE_REVERB_SCHEME ?? 'http') as string;

    if (!key.trim()) {
        console.warn('[Echo] Reverb disabled — no app key configured.');
        return;
    }

    const forceTLS = scheme === 'https';

    configureEcho({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        // Always set both ports — do NOT use enabledTransports: ['wss'].
        // Restricting enabledTransports causes Pusher to skip the transport
        // strategy entirely and go initialized -> failed without even trying.
        // With forceTLS: true, Pusher will only use WSS regardless.
        wsPort: port,
        wssPort: port,
        forceTLS,
    });

    window.__plannerateEchoConfigured = true;

    const connector = echo().connector as { pusher: Pusher };
    const pusher = connector.pusher;

    pusher.connection.bind('connected', () =>
        echoLog('info', 'Reverb connected ✓', { socketId: pusher.connection.socket_id }),
    );

    pusher.connection.bind('state_change', ({ previous, current }: { previous: string; current: string }) =>
        echoLog('info', `Reverb: ${previous} → ${current}`),
    );

    pusher.connection.bind('failed', () =>
        echoLog('error', 'Reverb: connection FAILED — check host, port, SSL, and REVERB_HOST on the server.'),
    );

    pusher.connection.bind('error', (err: PusherErrorEvent) => {
        const code = err?.error?.data?.code;
        echoLog('error', 'Reverb error — type:', err?.type, '| code:', code, wsCloseCodeLabel(code), '| message:', err?.error?.data?.message);
    });

    // If the connection failed before handlers were bound, disconnect() resets
    // the state machine so connect() can make a proper instrumented attempt.
    if (pusher.connection.state === 'failed') {
        pusher.disconnect();
        pusher.connect();
    }
}
