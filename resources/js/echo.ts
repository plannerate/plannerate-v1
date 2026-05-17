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
    const wsUrl = `${wsProtocol}://${host}:${port}/app/${key}`;

    echoLog('info', 'forceTLS:', forceTLS);
    echoLog('info', 'WebSocket URL will be:', wsUrl);

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

    echoLog('info', 'configureEcho done — binding connection events...');

    const connector = echo().connector as { pusher: Pusher };
    const pusher = connector.pusher;

    echoLog('info', 'Pusher instance created. Initial connection state:', pusher.connection.state);

    pusher.connection.bind('initialized', () => echoLog('info', 'Connection: initialized'));
    pusher.connection.bind('connecting', () => echoLog('info', 'Connection: connecting...'));
    pusher.connection.bind('connected', () => echoLog('info', 'Connection: CONNECTED ✓', { socketId: pusher.connection.socket_id }));
    pusher.connection.bind('unavailable', () => echoLog('warn', 'Connection: unavailable (will retry)'));
    pusher.connection.bind('failed', () => echoLog('error', 'Connection: FAILED — browser may not support WebSockets, or host/port is wrong.'));
    pusher.connection.bind('disconnected', () => echoLog('warn', 'Connection: disconnected'));

    pusher.connection.bind('state_change', ({ previous, current }: { previous: string; current: string }) => {
        echoLog('info', `State change: ${previous} → ${current}`);
    });

    pusher.connection.bind('error', (err: { type?: string; error?: { data?: { code?: number; message?: string } } }) => {
        echoLog('error', 'Connection error event:', err);
        echoLog('error', 'Error type:', err?.type);
        echoLog('error', 'Error code:', err?.error?.data?.code);
        echoLog('error', 'Error message:', err?.error?.data?.message);
    });

    pusher.bind_global((eventName: string, data: unknown) => {
        if (!['pusher:pong', 'pusher:ping'].includes(eventName)) {
            echoLog('info', `Global event: ${eventName}`, data);
        }
    });

    echoLog('info', '─── Echo initialization complete ───');
}
