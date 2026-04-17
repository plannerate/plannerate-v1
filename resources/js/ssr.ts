import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => {
                const props = page.props as Record<string, unknown>;
                const tenant = props.tenant as Record<string, unknown> | null;
                const tenantName = typeof tenant?.name === 'string' ? tenant.name : null;
                const appName = typeof props.name === 'string' ? props.name : null;
                const suffix = (tenantName || appName || import.meta.env.VITE_APP_NAME || 'Plannerate').trim();

                return title ? `${title} - ${suffix}` : suffix;
            },
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
        }),
    { cluster: true },
);
