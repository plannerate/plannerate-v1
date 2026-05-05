import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import Pusher from 'pusher-js';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

function metaContent(name: string): string | null {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? null;
}

if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    const reverbAppKey = (metaContent('plannerate-reverb-key') ?? import.meta.env.VITE_REVERB_APP_KEY ?? '') as string;
    const reverbHost = (metaContent('plannerate-reverb-host') ?? import.meta.env.VITE_REVERB_HOST ?? window.location.hostname) as string;
    const reverbPort = Number(metaContent('plannerate-reverb-port') ?? import.meta.env.VITE_REVERB_PORT ?? 8080);
    const reverbScheme = (metaContent('plannerate-reverb-scheme') ?? import.meta.env.VITE_REVERB_SCHEME ?? window.location.protocol.replace(':', '')) as string;

    if (reverbAppKey.trim() !== '') {
        configureEcho({
            broadcaster: 'reverb',
            key: reverbAppKey,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } else {
        console.warn('Echo/Reverb disabled: no runtime or Vite Reverb app key was provided.');
    }
}

const appName =
    metaContent('plannerate-tenant-name')?.trim() ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return null;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
