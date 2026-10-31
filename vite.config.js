import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// Bootstrap 5 servi depuis l'application (aucun CDN : CSP stricte, connexions lentes, hors-ligne).
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
