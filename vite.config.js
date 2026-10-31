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
        watch: { ignored: ['**/storage/framework/views/**', '**/.agents/**', '**/.codex/**'] },
    },
});
