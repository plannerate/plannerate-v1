import path from 'path';
import { defineConfig } from 'vitest/config';

/**
 * Config standalone do Vitest (não importa o vite.config.ts do app de propósito:
 * o laravel-vite-plugin e o wayfinder não fazem sentido no runner de testes).
 *
 * Os aliases espelham os do vite.config.ts/tsconfig.json — apontando para
 * packages/ (fonte) em vez de vendor/ (symlink) para funcionar mesmo sem
 * composer install completo.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@/components/plannerate': path.resolve(__dirname, './packages/callcocam/laravel-raptor-plannerate/resources/js/components/plannerate'),
            '@/components/planogram-templates': path.resolve(__dirname, './packages/callcocam/laravel-raptor-plannerate/resources/js/components/planogram-templates'),
            '@/composables/plannerate': path.resolve(__dirname, './packages/callcocam/laravel-raptor-plannerate/resources/js/composables/plannerate'),
            '@/types/planogram': path.resolve(__dirname, './packages/callcocam/laravel-raptor-plannerate/resources/js/types/planogram.ts'),
            '@plannerate': path.resolve(__dirname, './packages/callcocam/laravel-raptor-plannerate/resources/js'),
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.ts'],
    },
});
