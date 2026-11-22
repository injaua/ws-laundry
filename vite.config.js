import { globSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

// One entry per page script / stylesheet: views load them with @vite(...).
const entries = (pattern) => globSync(pattern).map((path) => path.replaceAll('\\', '/')).sort();

export default defineConfig({
    plugins: [
        laravel({
            input: [
                // Bootstrap + icons from npm (partials/bootstrap)
                'resources/css/vendor/bootstrap.css',
                'resources/css/vendor/bootstrap-rtl.css',
                // Layouts
                'resources/css/admin.css',
                'resources/js/admin.js',
                'resources/css/guest.css',
                'resources/js/guest.js',
                'resources/css/pay.css',
                'resources/css/landing.css',
                'resources/js/landing.js',
                // Printed documents (A4, thermal tickets, labels)
                'resources/css/document.css',
                'resources/css/ticket.css',
                'resources/css/label.css',
                'resources/css/piece-tags.css',
                'resources/js/print.js',
                ...entries('resources/js/pages/*.js'),
                ...entries('resources/css/pages/*.css'),
            ],
            refresh: true,
        }),
    ],
});
