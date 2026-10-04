import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/shell.css', 'resources/js/shell.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        hmr: {
            host: process.env.VITE_HMR_HOST ?? 'localhost',
            port: Number(process.env.VITE_PORT ?? 5173),
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
            ...(process.env.DOCKER_DEV === 'true' ? { usePolling: true, interval: 1000 } : {}),
        },
    },
});
