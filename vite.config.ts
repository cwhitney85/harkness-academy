import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            // Spec 8.3.2. Body, display and the tutor's hand; the fallback
            // stacks that stand in for them live in resources/css/app.css.
            fonts: [
                bunny('Newsreader', {
                    weights: [300, 400, 500],
                    styles: ['normal', 'italic'],
                    fallbacks: ['Georgia', 'Times New Roman', 'serif'],
                }),
                bunny('Instrument Serif', {
                    weights: [400],
                    styles: ['normal', 'italic'],
                    fallbacks: ['Georgia', 'Times New Roman', 'serif'],
                }),
                // Spec 8.4.2: the face the photocopied practice sheet is
                // printed in. Bold for the sheet's head, italic for its
                // directions line.
                bunny('Courier Prime', {
                    weights: [400, 700],
                    styles: ['normal', 'italic'],
                    fallbacks: ['Courier New', 'Courier', 'monospace'],
                }),
                bunny('Caveat', {
                    weights: [500, 600],
                    fallbacks: ['Segoe Script', 'Bradley Hand', 'cursive'],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
});
