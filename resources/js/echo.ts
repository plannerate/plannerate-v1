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

function bindConnectionEvents(pusher: Pusher): void {
    pusher.connection.bind('initialized', () =>
        echoLog('info', 'Connection: initialized'),
    );

    pusher.connection.bind('connecting', () => {
        echoLog('info', 'Connection: connecting...');
        echoLog('info', 'Transport being tried:', pusher.connection.state);
    });

    pusher.connection.bind('connected', () =>
        echoLog('info', 'Connection: CONNECTED ✓', { socketId: pusher.connection.socket_id }),
    );

    pusher.connection.bind('unavailable', () =>
        echoLog('warn', 'Connection: unavailable — server unreachable, will retry automatically'),
    );

    pusher.connection.bind('failed', () => {
        echoLog('error', 'Connection: FAILED permanently.');
        echoLog('error', 'Possible causes: SSL/TLS error, REVERB_HOST mismatch, port blocked, or WebSockets not supported.');
    });

    pusher.connection.bind('disconnected', () =>
        echoLog('warn', 'Connection: disconnected'),
    );

    pusher.connection.bind('state_change', ({ previous, current }: { previous: string; current: string }) =>
        echoLog('info', `State change: ${previous} → ${current}`),
    );

    pusher.connection.bind('error', (err: PusherErrorEvent) => {
        const code = err?.error?.data?.code;
        const message = err?.error?.data?.message;
        const type = err?.type;

        echoLog('error', '─── Connection error ───');
        echoLog('error', 'Type:', type);
        echoLog('error', 'Code:', code, wsCloseCodeLabel(code));
        echoLog('error', 'Message:', message);
        echoLog('error', 'Raw:', err);
    });

    pusher.bind_global((eventName: string, data: unknown) => {
        if (!['pusher:pong', 'pusher:ping'].includes(eventName)) {
            echoLog('info', `Global event: ${eventName}`, data);
        }
    });
}

function wsCloseCodeLabel(code?: number): string {
    if (code === undefined) return '';
    const labels: Record<number, string> = {
        1000: '(Normal closure)',
        1001: '(Going away)',
        1006: '(Abnormal closure — SSL/TLS failure or server unreachable)',
        1015: '(TLS handshake failure)',
        4001: '(Reverb: app does not exist — wrong APP_KEY)',
        4004: '(Reverb: app disabled)',
        4009: '(Reverb: connection unauthorized)',
        4100: '(Reverb: over capacity)',
    };
    return labels[code] ?? `(unknown close code ${code})`;
}

export function initializeEcho(): void {
    if (typeof window === 'undefined') return;

    window.Pusher = Pusher;
    window.__plannerateEchoConfigured = false;
    Pusher.logToConsole = true;

    echoLog('info', '─── Echo initialization start ───');
    echoLog('info', 'Environment:', import.meta.env.MODE);
    echoLog('info', 'User agent:', navigator.userAgent);

    const key = metaContent('plannerate-reverb-key') ?? import.meta.env.VITE_REVERB_APP_KEY ?? '';
    const host = metaContent('plannerate-reverb-host') ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
    const port = Number(metaContent('plannerate-reverb-port') ?? import.meta.env.VITE_REVERB_PORT ?? 8080);
    const scheme = (metaContent('plannerate-reverb-scheme') ?? import.meta.env.VITE_REVERB_SCHEME ?? 'http') as string;

    echoLog('info', 'Meta tags read:', {
        key: metaContent('plannerate-reverb-key'),
        host: metaContent('plannerate-reverb-host'),
        port: metaContent('plannerate-reverb-port'),
        scheme: metaContent('plannerate-reverb-scheme'),
    });

    echoLog('info', 'VITE env vars:', {
        VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY,
        VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST,
        VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT,
        VITE_REVERB_SCHEME: import.meta.env.VITE_REVERB_SCHEME,
    });

    echoLog('info', 'Resolved config:', { key, host, port, scheme });

    if (!key.trim()) {
        echoLog('warn', 'Reverb disabled — no app key found in meta tags or VITE env vars.');
        return;
    }

    const forceTLS = scheme === 'https';
    const wsProtocol = forceTLS ? 'wss' : 'ws';

    echoLog('info', 'forceTLS:', forceTLS);
    echoLog('info', 'WebSocket URL will be:', `${wsProtocol}://${host}:${port}/app/${key}`);

    const echoConfig = {
        broadcaster: 'reverb' as const,
        key,
        wsHost: host,
        wsPort: forceTLS ? undefined : port,
        wssPort: forceTLS ? port : undefined,
        forceTLS,
        enabledTransports: [wsProtocol] as ('ws' | 'wss')[],
    };

    echoLog('info', 'Calling configureEcho with:', echoConfig);

    configureEcho(echoConfig);
    window.__plannerateEchoConfigured = true;

    const connector = echo().connector as { pusher: Pusher };
    const pusher = connector.pusher;

    // Bind ALL handlers before checking state so the retry is fully instrumented
    bindConnectionEvents(pusher);

    const initialState = pusher.connection.state;
    echoLog('info', 'Initial connection state after configureEcho:', initialState);

    if (initialState === 'failed') {
        echoLog('error', 'Connection failed immediately — the first attempt fired before handlers were ready.');
        echoLog('warn', 'Retrying now with handlers active to capture the real error...');
        pusher.connection.connect();
    }

    echoLog('info', '─── Echo initialization complete ───');
}
