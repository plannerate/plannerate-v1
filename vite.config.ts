import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    optimizeDeps: {
        include: [ 
            'vendor/callcocam/laravel-raptor-flow/resources/js/**/*.vue',
            'vendor/callcocam/laravel-raptor-plannerate/resources/js/**/*.vue',
        ],
    },
    resolve: {
        alias: {
            '@/components/plannerate': path.resolve(__dirname, './vendor/callcocam/laravel-raptor-plannerate/resources/js/components/plannerate'),
            '@/composables/plannerate': path.resolve(__dirname, './vendor/callcocam/laravel-raptor-plannerate/resources/js/composables/plannerate'),
            '@/types/planogram': path.resolve(__dirname, './vendor/callcocam/laravel-raptor-plannerate/resources/js/types/planogram.ts'),
            '@plannerate': path.resolve(__dirname, './vendor/callcocam/laravel-raptor-plannerate/resources/js'), 
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
});
