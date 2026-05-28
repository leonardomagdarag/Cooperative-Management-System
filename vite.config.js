import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const hmrHost = process.env.VITE_HMR_HOST || 'localhost';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/login.js',
            ],
            refresh: true,
        }),

        tailwindcss(),

    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: hmrHost,
            clientPort: 5173,
        }
    }
});
