import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const hmrHost = process.env.VITE_HMR_HOST || 'localhost';
const backendUrl = process.env.VITE_BACKEND_URL || 'http://127.0.0.1:8000';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/react/main.tsx',
            ],
            refresh: true,
        }),

        react(),

        tailwindcss(),

    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: hmrHost,
            clientPort: 5173,
        },
        proxy: {
            '/api': backendUrl,
            '/sanctum': backendUrl,
            '/login': backendUrl,
            '/logout': backendUrl,
            '/forgot-password': backendUrl,
            '/reset-password': backendUrl,
            '/register': backendUrl,
            '/contact': backendUrl,
            '/security': backendUrl,
            '/verify-email': backendUrl,
            '/confirm-password': backendUrl,
            '/profile': backendUrl,
            '/password': backendUrl,
            '/notifications': backendUrl,
            '/dashboard': backendUrl,
            '/psgc': backendUrl,
        },
    }
});
