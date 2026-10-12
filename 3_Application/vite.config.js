import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // Même adresse IPv4 que « php artisan serve » (127.0.0.1) : sans cela, Vite n'écoute qu'en IPv6 ([::1])
    // et le navigateur échoue par moments à charger les scripts (ERR_NETWORK_ACCESS_DENIED, WebSocket refusé).
    server: {
        host: '127.0.0.1',
    },
    optimizeDeps: {
        exclude: ['maplibre-gl']
    }
});
