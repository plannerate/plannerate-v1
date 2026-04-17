// import { wayfinder } from '@laravel/vite-plugin-wayfinder'; // Comentado temporariamente
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig(() => ({
    server: { 
        hmr: {
            host: 'localhost',
        }, 
    },  
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: [
                'resources/**',
                'vendor/callcocam/laravel-raptor/resources/**/*.vue',
                'vendor/callcocam/laravel-raptor/resources/**/*.ts',
                'vendor/callcocam/laravel-raptor-flow/resources/**/*.vue',
                'vendor/callcocam/laravel-raptor-flow/resources/**/*.ts',
                'vendor/callcocam/laravel-raptor-plannerate/resources/**/*.vue',
                'vendor/callcocam/laravel-raptor-plannerate/resources/**/*.ts',
            ],
        }),
        tailwindcss(),
        // Wayfinder only in development - types are pre-generated in production builds
        // Desabilitado temporariamente devido a problemas de permissão
        // Para gerar tipos manualmente: php artisan wayfinder:generate --with-form
        // ...(mode === 'development' ? [wayfinder({
        //     formVariants: true,
        // })] : []),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    optimizeDeps: {
        include: [
            'vendor/callcocam/laravel-raptor/resources/js/**/*.vue',
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
            '@flow': path.resolve(__dirname, './vendor/callcocam/laravel-raptor-flow/resources/js'),
            '~': path.resolve(__dirname, './vendor/callcocam/laravel-raptor/resources/js'),
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
}));
