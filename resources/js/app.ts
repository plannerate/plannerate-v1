import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { initializeEcho } from '@/echo';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { setUrlDefaults } from '@/wayfinder';

function metaContent(name: string): string | null {
    if (typeof document === 'undefined') return null;
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? null;
}

initializeEcho();

const appName =
    metaContent('plannerate-tenant-name')?.trim() ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';

router.on('navigate', (event) => {
    const tenant = event.detail.page.props?.tenant as { slug?: string } | undefined;
    if (tenant?.slug) {
        setUrlDefaults({ subdomain: tenant.slug });
    }
});

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
