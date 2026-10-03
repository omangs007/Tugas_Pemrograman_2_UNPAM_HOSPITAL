import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss', // CSS publik + pasien + auth
                'resources/js/app.js', // JS publik + pasien + auth (dimuat juga oleh admin.js)
                'resources/js/admin.js', // JS admin; mengimpor resources/scss/admin.scss
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 masih memakai @import; sembunyikan peringatan deprecation dari dependensi.
                quietDeps: true,
                silenceDeprecations: ['import', 'mixed-decls', 'color-functions', 'global-builtin'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
