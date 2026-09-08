import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Separate from vite.config.ts on purpose: the Laravel, Wayfinder and font
 * plugins there all reach for a running app, and a component test needs none
 * of them. The React plugin carries the compiler, so what is under test is
 * compiled the way the shipped bundle is.
 */
export default defineConfig({
    plugins: [react({ babel: { plugins: ['babel-plugin-react-compiler'] } })],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.tsx'],
    },
});
