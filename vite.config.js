import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import inertia from '@inertiajs/vite';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
    plugins: [
        laravel({ input: ['resources/js/app.tsx'], refresh: true }),
        inertia({ ssr: false }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: { host: 'localhost' },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            ignored: ['**/storage/framework/views/**', '**/.agents/**', '**/.codex/**'],
        },
    },
});
