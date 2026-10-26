import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Aucune ressource tierce (polices système, pas de service de polices) : direction artistique, §2 et §3.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/portail.css', 'resources/js/portail.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // Budget de performance (architecture §9) : alerte au-delà de 30 Ko de JavaScript.
        chunkSizeWarningLimit: 30,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
