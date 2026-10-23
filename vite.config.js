import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/admin/panel.js', 'resources/js/admin/modulos/auth.js', 'resources/css/admin/menu.css', 'resources/css/admin/panel.css', 'resources/css/admin/acceso.css'],
            refresh: true,
        }),
    ],
});
