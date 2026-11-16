import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/auth.css',
                'resources/css/admin.css',
                'resources/css/frontend.css',
                'resources/js/app.js',
                'resources/css/builder.css',
                'resources/css/builder-canvas.css',
                'resources/js/builder/editor.js',
                'resources/js/builder-frontend.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
