import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, defineAsyncComponent, h } from 'vue';
import RaptorPlugin, { ComponentRegistry, ActionRegistry } from '~/raptor';
import TableRegistry from '~/utils/TableRegistry';
import { initializeTheme } from './composables/useAppearance';
import { initializeThemeSystem } from './composables/useTheme';

// Initialize Laravel Echo for WebSocket/Broadcasting (Reverb)
import './echo';
// import '~/echo.ts';
TableRegistry.register(
    'table-planogram',
    defineAsyncComponent(() => import('./components/table/PlanogramTable.vue')),
);

ComponentRegistry.registerBulk({
    // 'table-column-image': defineAsyncComponent(() => import('./components/table/TableImage.vue')),
    'form-field-maps': defineAsyncComponent(() => import('./components/form/fields/FormFieldMaps.vue')),
    'form-field-sysmo-api': defineAsyncComponent(() => import('./components/form/fields/FormFieldSection.vue')),
    'form-field-visao-api': defineAsyncComponent(() => import('./components/form/fields/FormFieldSection.vue')),
    'form-field-multiselect': defineAsyncComponent(() => import('./components/form/fields/FormFieldMultiSelect.vue')),
});

ActionRegistry.registerBulk({
    'person-action-create-gondola-stepper': defineAsyncComponent(() => import('./components/actions/types/ActionCreateGondolaStepper.vue')),
});
const resolveTitleSuffix = (): string => {
    const tenantName =
        typeof document !== 'undefined'
            ? document.querySelector('meta[name="tenant-name"]')?.getAttribute('content')
            : null;

    const appNameFromMeta =
        typeof document !== 'undefined'
            ? document.querySelector('meta[name="app-name"]')?.getAttribute('content')
            : null;

    return (tenantName || appNameFromMeta || import.meta.env.VITE_APP_NAME || 'Plannerate').trim();
};

const titleSuffix = resolveTitleSuffix();

createInertiaApp({
    title: (title) => (title ? `${title} - ${titleSuffix}` : titleSuffix),
    resolve: (name) => {
        // Primeiro tenta encontrar no pacote Raptor
        const raptorPages = import.meta.glob<DefineComponent>(
            '../../vendor/callcocam/laravel-raptor/resources/js/pages/**/*.vue',
        );
        const raptorPagePath = `../../vendor/callcocam/laravel-raptor/resources/js/pages/${name}.vue`;

        if (raptorPages[raptorPagePath]) {
            return raptorPages[raptorPagePath]();
        }
        return resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        );
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.use(RaptorPlugin);
        ActionRegistry.register(
            'action-dropdown',
            defineAsyncComponent(() => import('@/components/actions/types/ActionDropdown.vue')),
        );

        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Initialize theme system (colors, fonts, rounded, variants)
initializeThemeSystem();
